# Segundo Bolão (Mata-Mata) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar um 2º bolão independente (só mata-mata, 32avos→final) e fazer o bracket real do mata-mata dos dois bolões ser populado/resultado pela TheSportsDB, com seletor de bolão no header (usuário) e entrada admin por bolão.

**Architecture:** O banco já é multi-torneio. Um seeder cria o torneio mata-mata (48 seleções com `id_externo`, esqueleto de 32 jogos placeholder, regras de knockout). O sync de mata-mata persiste os participantes reais na linha do `Jogo` e casa eventos por **par de times** usando os endpoints de liga (`eventspastleague`/`eventsnextleague`), sem depender de código de rodada. A trava de aposta por jogo já existe implicitamente (participantes nulos → item ignorado); reforçamos com validação explícita. Frontend ganha um store de "bolão ativo" + seletor no header; o admin passa a entrar por bolão em rota escopada.

**Tech Stack:** Laravel 12 (PHP 8.3), PHPUnit/Pest (feature tests), MySQL; Vue 3 + TS + Pinia + Vue Router + Tailwind.

**Spec:** `docs/superpowers/specs/2026-06-25-segundo-bolao-mata-mata-design.md`

**Convenções do projeto:** domínio em PT; schema só por migration (este plano **não** altera schema — só seeder/serviço/UI); validação de prazo só no backend; testes em `backend/tests/Feature`. Rodar testes: `cd backend && php artisan test --filter <Classe>`.

---

## Mapa de arquivos

**Criar (backend):**
- `backend/database/seeders/BolaoMataMataSeeder.php` — cria o torneio mata-mata.
- `backend/app/Services/ServicoMataMata.php` — persiste participantes reais nas linhas de `Jogo` de mata-mata (origem: evento da API casado por par de times; fallback: derivação `ServicoResultadosTorneio`).
- `backend/app/Console/Commands/ResolverMataMata.php` — command `jogos:resolver-mata-mata` que chama o serviço por torneio.
- `backend/tests/Feature/BolaoMataMataSeederTest.php`
- `backend/tests/Feature/ResolverMataMataTest.php`
- `backend/tests/Feature/SyncMataMataResultadoTest.php`
- `backend/tests/Feature/TravaApostaMataMataTest.php`

**Modificar (backend):**
- `backend/config/thesportsdb.php` — nova chave `rodadas_mata_mata` = `[32, 16, 8, 4, 2]` (R32 confirmado em 2026-06-26; demais ajustáveis).
- `backend/app/Services/ServicoTheSportsDb.php` — novo método `eventosDeMataMata()` (via `eventsround` com os códigos de knockout, dedup por idEvent).
- `backend/app/Console/Commands/VincularEventosJogos.php` — incluir `eventosDeMataMata()` no pool de eventos.
- `backend/app/Console/Commands/SincronizarResultadosJogos.php` — incluir `eventosDeMataMata()` no pool de eventos.
- `backend/routes/console.php` — agendar `jogos:resolver-mata-mata`.
- `backend/database/seeders/DatabaseSeeder.php` — chamar `BolaoMataMataSeeder` (depois do torneio principal).
- `backend/app/Services/ServicoApostas.php` — validação explícita: bloquear aposta de mata-mata com jogo sem participantes (já ignora; transformar em rejeição clara quando enviado individualmente — ver Task B3).

**Criar (frontend):**
- `frontend/src/stores/bolaoAtivo.ts` — store Pinia do bolão ativo (persiste em localStorage).
- `frontend/src/components/SeletorBolao.vue` — dropdown de bolão no header.
- `frontend/src/views/AdminBoloesView.vue` — lista de bolões (entrada do admin).

**Modificar (frontend):**
- `frontend/src/components/AppHeader.vue` — montar `SeletorBolao` quando autenticado.
- `frontend/src/views/PainelView.vue` — filtrar cupons pelo bolão ativo.
- `frontend/src/stores/torneio.ts` — suportar carregar torneio por id (bolão ativo).
- `frontend/src/views/CupomView.vue` — modo "mata-mata puro": consumir o torneio do cupom e renderizar bracket real compartilhado.
- `frontend/src/router/index.ts` — rota `/admin` → lista de bolões; `/admin/boloes/:torneio` → painel escopado.
- `frontend/src/views/AdminPainelView.vue` — receber `torneio` da rota em vez do seletor interno.

---

# PARTE A — Backend: estrutura do 2º bolão (seeder)

### Task A1: Seeder do bolão mata-mata

**Files:**
- Create: `backend/database/seeders/BolaoMataMataSeeder.php`
- Create: `backend/tests/Feature/BolaoMataMataSeederTest.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Escrever o teste que falha**

Crie `backend/tests/Feature/BolaoMataMataSeederTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\Selecao;
use App\Models\Torneio;
use Database\Seeders\BolaoMataMataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BolaoMataMataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_torneio_mata_mata_com_estrutura_esperada(): void
    {
        $this->seed(BolaoMataMataSeeder::class);

        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();

        // 48 seleções, todas com id_externo, sem grupo
        $selecoes = Selecao::query()->where('torneio_id', $torneio->id)->get();
        $this->assertCount(48, $selecoes);
        $this->assertTrue($selecoes->every(fn (Selecao $s) => $s->id_externo !== null));
        $this->assertTrue($selecoes->every(fn (Selecao $s) => $s->grupo_id === null));

        // 6 fases de mata-mata, nenhuma de grupos
        $fases = Fase::query()->where('torneio_id', $torneio->id)->get();
        $this->assertSame(6, $fases->count());
        $this->assertFalse($fases->contains(fn (Fase $f) => $f->tipo === 'grupos'));

        // 32 jogos placeholder (times nulos)
        $jogos = Jogo::query()->where('torneio_id', $torneio->id)->get();
        $this->assertSame(32, $jogos->count());
        $this->assertTrue($jogos->every(fn (Jogo $j) => $j->selecao_mandante_id === null && $j->selecao_visitante_id === null));

        // regras só de knockout (sem chaves de grupos)
        $chaves = RegraPontuacao::query()->where('torneio_id', $torneio->id)->pluck('chave')->unique();
        $this->assertTrue($chaves->contains('classificado_mata_mata'));
        $this->assertFalse($chaves->contains('placar_exato_fase_grupos'));
        $this->assertFalse($chaves->contains('artilheiro'));
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `cd backend && php artisan test --filter BolaoMataMataSeederTest`
Expected: FAIL — `Class "Database\Seeders\BolaoMataMataSeeder" not found`.

- [ ] **Step 3: Criar o seeder**

Crie `backend/database/seeders/BolaoMataMataSeeder.php`. Espelha `TorneioMockadoSeeder` mas só com mata-mata. As 48 siglas vêm das chaves de `config('thesportsdb.selecoes')` (que já tem exatamente 48 entradas com `id_externo`).

```php
<?php

namespace Database\Seeders;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\ResultadoTorneio;
use App\Models\Selecao;
use App\Models\Torneio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BolaoMataMataSeeder extends Seeder
{
    public function run(): void
    {
        $torneio = Torneio::query()->updateOrCreate(
            ['nome' => 'Inter World Cup — Mata-Mata', 'edicao' => '2026-MM'],
            [
                'status' => 'publicado',
                'data_inicio' => '2026-06-28',
                'data_fim' => '2026-07-19',
                'valor_cupom' => 10.00,
            ],
        );

        // ── Fases (só mata-mata) ───────────────────────────────
        $roundOf32 = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'round_of_32'],
            ['nome' => 'Round of 32', 'ordem' => 1, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-06-28 12:00:00'],
        );
        $oitavas = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'oitavas_de_final'],
            ['nome' => 'Oitavas de Final', 'ordem' => 2, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-07-03 12:00:00'],
        );
        $quartas = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'quartas_de_final'],
            ['nome' => 'Quartas de Final', 'ordem' => 3, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-07-09 12:00:00'],
        );
        $semifinais = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'semifinais'],
            ['nome' => 'Semifinais', 'ordem' => 4, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-07-14 12:00:00'],
        );
        $terceiroLugar = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'terceiro_lugar'],
            ['nome' => 'Terceiro Lugar', 'ordem' => 5, 'tipo' => 'final', 'data_fechamento' => '2026-07-18 12:00:00'],
        );
        $final = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'final'],
            ['nome' => 'Final', 'ordem' => 6, 'tipo' => 'final', 'data_fechamento' => '2026-07-19 12:00:00'],
        );

        // ── 48 seleções (sem grupo, com id_externo) ────────────
        // O nome legível por sigla reaproveita o nome usado no torneio principal
        // quando existir; senão usa a própria sigla.
        $nomesPorSigla = $this->nomesPorSigla();
        foreach (config('thesportsdb.selecoes') as $sigla => $idExterno) {
            $nome = $nomesPorSigla[$sigla] ?? $sigla;
            Selecao::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'sigla' => $sigla],
                [
                    'grupo_id' => null,
                    'nome' => $nome,
                    'id_externo' => $idExterno,
                    'slug' => Str::slug($nome),
                    'ativo' => true,
                ],
            );
        }

        // ── 32 jogos placeholder (times nulos) ─────────────────
        $jogos = [];
        foreach (range(1, 16) as $i) {
            $jogos[] = ['fase' => $roundOf32, 'ordem' => $i];
        }
        foreach (range(1, 8) as $i) {
            $jogos[] = ['fase' => $oitavas, 'ordem' => $i];
        }
        foreach (range(1, 4) as $i) {
            $jogos[] = ['fase' => $quartas, 'ordem' => $i];
        }
        foreach (range(1, 2) as $i) {
            $jogos[] = ['fase' => $semifinais, 'ordem' => $i];
        }
        $jogos[] = ['fase' => $terceiroLugar, 'ordem' => 1];
        $jogos[] = ['fase' => $final, 'ordem' => 1];

        // datas: knockout começa em 28/06; distribui de forma estável e crescente.
        $base = strtotime('2026-06-28 16:00');
        $passo = 0;
        foreach ($jogos as $jogo) {
            Jogo::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'fase_id' => $jogo['fase']->id, 'ordem_na_fase' => $jogo['ordem']],
                [
                    'rodada_id' => null,
                    'grupo_id' => null,
                    'selecao_mandante_id' => null,
                    'selecao_visitante_id' => null,
                    'data_hora_inicio' => date('Y-m-d H:i:s', $base + ($passo++ * 4 * 3600)),
                    'status' => 'agendado',
                ],
            );
        }

        // ── Regras de pontuação (só knockout) ──────────────────
        $regras = [
            ['fase' => $roundOf32, 'chave' => 'classificado_mata_mata', 'nome' => 'Classificado Round of 32', 'descricao' => 'Acertou quem avançou no Round of 32', 'pontos' => 4],
            ['fase' => $oitavas, 'chave' => 'classificado_mata_mata', 'nome' => 'Classificado oitavas', 'descricao' => 'Acertou quem avançou nas oitavas', 'pontos' => 6],
            ['fase' => $quartas, 'chave' => 'classificado_mata_mata', 'nome' => 'Classificado quartas', 'descricao' => 'Acertou quem avançou nas quartas', 'pontos' => 8],
            ['fase' => $semifinais, 'chave' => 'classificado_mata_mata', 'nome' => 'Classificado semifinal', 'descricao' => 'Acertou quem avançou na semifinal', 'pontos' => 10],
            ['fase' => $terceiroLugar, 'chave' => 'classificado_mata_mata', 'nome' => 'Vencedor terceiro lugar', 'descricao' => 'Acertou quem venceu a disputa de terceiro lugar', 'pontos' => 8],
            ['fase' => $final, 'chave' => 'classificado_mata_mata', 'nome' => 'Campeão da final', 'descricao' => 'Acertou o campeão', 'pontos' => 10],
            ['fase' => null, 'chave' => 'campeao', 'nome' => 'Campeão', 'descricao' => 'Acertou o campeão da Copa', 'pontos' => 25],
            ['fase' => null, 'chave' => 'vice_campeao', 'nome' => 'Vice-campeão', 'descricao' => 'Acertou o vice-campeão', 'pontos' => 15],
            ['fase' => null, 'chave' => 'terceiro_colocado', 'nome' => 'Terceiro colocado', 'descricao' => 'Acertou o terceiro colocado', 'pontos' => 12],
        ];
        foreach ($regras as $regra) {
            RegraPontuacao::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'fase_id' => $regra['fase']?->id, 'chave' => $regra['chave']],
                ['nome' => $regra['nome'], 'descricao' => $regra['descricao'], 'pontos' => $regra['pontos'], 'ativo' => true],
            );
        }

        ResultadoTorneio::query()->updateOrCreate(
            ['torneio_id' => $torneio->id],
            ['campeao_selecao_id' => null, 'vice_campeao_selecao_id' => null, 'terceiro_colocado_selecao_id' => null, 'artilheiro_jogador_id' => null],
        );
    }

    /** @return array<string,string> sigla => nome legível (do torneio principal, se existir). */
    private function nomesPorSigla(): array
    {
        return Selecao::query()
            ->whereNotNull('nome')
            ->pluck('nome', 'sigla')
            ->all();
    }
}
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `cd backend && php artisan test --filter BolaoMataMataSeederTest`
Expected: PASS.

- [ ] **Step 5: Registrar o seeder no DatabaseSeeder**

Em `backend/database/seeders/DatabaseSeeder.php`, dentro de `run()`, adicione a chamada **após** o seeder do torneio principal (procure a linha `$this->call(TorneioMockadoSeeder::class);` ou equivalente e adicione logo abaixo):

```php
$this->call(BolaoMataMataSeeder::class);
```

- [ ] **Step 6: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add backend/database/seeders/BolaoMataMataSeeder.php backend/tests/Feature/BolaoMataMataSeederTest.php backend/database/seeders/DatabaseSeeder.php && git commit -m "feat(copa): seeder do bolao mata-mata (estrutura + regras)"
```

---

# PARTE B — Backend: sync de mata-mata (participantes + resultado via API)

### Task B1: config `rodadas_mata_mata` + `ServicoTheSportsDb::eventosDeMataMata()`

**Files:**
- Modify: `backend/config/thesportsdb.php`
- Modify: `backend/app/Services/ServicoTheSportsDb.php`
- Create: `backend/tests/Feature/EventosMataMataTest.php`

- [ ] **Step 1: Adicionar a config dos códigos de rodada de knockout**

Em `backend/config/thesportsdb.php`, após a chave `'rodadas' => [1, 2, 3],`, adicione:

```php
    /*
    | Rodadas de mata-mata (eventsround, sem teto no free tier).
    | Convenção "times restantes": Round of 32 = 32 (CONFIRMADO 2026-06-26),
    | Round of 16 = 16, Quartas = 8, Semi = 4, Final = 2. Ajuste conforme a API
    | publicar as fases. r=2 colide com a rodada 2 de grupos, mas o casamento é
    | por par de times — sem efeito colateral.
    */
    'rodadas_mata_mata' => [32, 16, 8, 4, 2],
```

- [ ] **Step 2: Teste que falha**

Crie `backend/tests/Feature/EventosMataMataTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Services\ServicoTheSportsDb;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EventosMataMataTest extends TestCase
{
    public function test_busca_e_deduplica_eventos_das_rodadas_de_knockout(): void
    {
        config(['thesportsdb.rodadas_mata_mata' => [32, 16]]);

        Http::fake([
            '*eventsround.php?id=4429&r=32*' => Http::response(['events' => [
                ['idEvent' => '111', 'idHomeTeam' => '136482', 'idAwayTeam' => '140073', 'dateEvent' => '2026-06-28'],
            ]]),
            '*eventsround.php?id=4429&r=16*' => Http::response(['events' => [
                ['idEvent' => '222', 'idHomeTeam' => '134496', 'idAwayTeam' => '134503', 'dateEvent' => '2026-07-06'],
            ]]),
        ]);

        $eventos = app(ServicoTheSportsDb::class)->eventosDeMataMata();

        $ids = array_map(fn ($e) => (int) $e['idEvent'], $eventos);
        sort($ids);
        $this->assertSame([111, 222], $ids);
    }
}
```

- [ ] **Step 3: Rodar e confirmar falha**

Run: `cd backend && php artisan test --filter EventosMataMataTest`
Expected: FAIL — `Call to undefined method ...::eventosDeMataMata()`.

- [ ] **Step 4: Implementar o método**

Em `backend/app/Services/ServicoTheSportsDb.php`, adicione (após `eventosDasRodadas`):

```php
    /**
     * Eventos de mata-mata via eventsround (sem teto no free tier), usando os
     * códigos de rodada do knockout (config rodadas_mata_mata). Round of 32 = 32
     * (confirmado 2026-06-26). Dedup por idEvent.
     *
     * @return array<int,array<string,mixed>>
     */
    public function eventosDeMataMata(): array
    {
        return $this->eventosDasRodadas((array) config('thesportsdb.rodadas_mata_mata', []));
    }
```

- [ ] **Step 5: Rodar e confirmar PASS**

Run: `cd backend && php artisan test --filter EventosMataMataTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add backend/config/thesportsdb.php backend/app/Services/ServicoTheSportsDb.php backend/tests/Feature/EventosMataMataTest.php && git commit -m "feat(copa): eventos de mata-mata via eventsround (r=32 confirmado)"
```

### Task B2: `ServicoMataMata` — persistir participantes reais na linha do Jogo

**Objetivo:** Para o bracket real ser casado por par de times, os jogos de mata-mata precisam ter `selecao_mandante_id`/`selecao_visitante_id` preenchidos. Este serviço resolve os participantes reais via `ServicoResultadosTorneio` (que já calcula a partir dos resultados reais) e **persiste** nas linhas dos jogos. É a fonte concreta dos times; quando a API publicar os confrontos, o casamento por par de times (Task B5) já funciona.

**Files:**
- Create: `backend/app/Services/ServicoMataMata.php`
- Create: `backend/tests/Feature/ResolverMataMataTest.php`

- [ ] **Step 1: Teste que falha**

Crie `backend/tests/Feature/ResolverMataMataTest.php`. Usa o torneio principal (com fase de grupos), lança resultados de grupos e verifica que os jogos do Round of 32 recebem `selecao_mandante_id`/`visitante_id` persistidos.

```php
<?php

namespace Tests\Feature;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Torneio;
use App\Services\ServicoMataMata;
use App\Services\ServicoResultadosTorneio;
use Database\Seeders\TorneioMockadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolverMataMataTest extends TestCase
{
    use RefreshDatabase;

    public function test_persiste_participantes_reais_nos_jogos_de_mata_mata(): void
    {
        $this->seed(TorneioMockadoSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026')->firstOrFail();

        // Lança um placar para TODOS os jogos de grupos (necessário para a derivação).
        $faseGrupos = Fase::query()->where('torneio_id', $torneio->id)->where('tipo', 'grupos')->firstOrFail();
        $jogosGrupos = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $faseGrupos->id)->get();
        foreach ($jogosGrupos as $i => $jogo) {
            ResultadoJogo::query()->create([
                'jogo_id' => $jogo->id,
                'placar_mandante' => ($i % 3),     // varia para gerar classificação
                'placar_visitante' => (($i + 1) % 2),
                'selecao_classificada_id' => null,
                'encerrado_at' => now(),
            ]);
            $jogo->forceFill(['status' => 'encerrado'])->save();
        }

        // Sanidade: a derivação enxerga participantes do Round of 32.
        $participantes = app(ServicoResultadosTorneio::class)->participantesPorJogo($torneio->fresh());
        $algumResolvido = collect($participantes)->contains(fn ($p) => $p['mandante'] !== null && $p['visitante'] !== null);
        $this->assertTrue($algumResolvido, 'pré-condição: derivação resolveu ao menos um confronto');

        app(ServicoMataMata::class)->persistirParticipantes($torneio->fresh());

        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();
        $jogosR32 = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $r32->id)->get();
        $this->assertTrue(
            $jogosR32->contains(fn (Jogo $j) => $j->selecao_mandante_id !== null && $j->selecao_visitante_id !== null),
            'ao menos um jogo do Round of 32 deve ter participantes persistidos'
        );
    }

    public function test_2o_bolao_preenche_confrontos_direto_da_api_por_rodada(): void
    {
        config(['thesportsdb.rodadas_mata_mata' => [32]]);
        $this->seed(\Database\Seeders\BolaoMataMataSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();

        // r=32: Brazil (134496) x Japan (134503), confronto real publicado pela API.
        \Illuminate\Support\Facades\Http::fake([
            '*eventsround.php?id=4429&r=32*' => \Illuminate\Support\Facades\Http::response(['events' => [
                ['idEvent' => '900100', 'idHomeTeam' => '134496', 'idAwayTeam' => '134503', 'dateEvent' => '2026-06-29'],
            ]]),
            '*eventsround.php*' => \Illuminate\Support\Facades\Http::response(['events' => []]),
        ]);

        app(ServicoMataMata::class)->persistirParticipantes($torneio->fresh());

        $bra = \App\Models\Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'BRA')->firstOrFail();
        $jpn = \App\Models\Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'JPN')->firstOrFail();
        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();

        $this->assertTrue(
            Jogo::query()->where('fase_id', $r32->id)
                ->where('selecao_mandante_id', $bra->id)
                ->where('selecao_visitante_id', $jpn->id)
                ->exists(),
            'o confronto BRA x JPN do R32 deve ser preenchido a partir da API'
        );
    }
}
```

- [ ] **Step 2: Rodar e confirmar falha**

Run: `cd backend && php artisan test --filter ResolverMataMataTest`
Expected: FAIL — `Class "App\Services\ServicoMataMata" not found`.

- [ ] **Step 3: Implementar o serviço**

Crie `backend/app/Services/ServicoMataMata.php`. Dois caminhos: **derivação** (torneio com fase de grupos = bolão atual) e **espelho da API por rodada** (torneio mata-mata puro = 2º bolão).

```php
<?php

namespace App\Services;

use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;

/**
 * Persiste os participantes reais dos jogos de mata-mata nas linhas de Jogo
 * (selecao_mandante_id / selecao_visitante_id). Com os times persistidos, o
 * pipeline de vinculação/sincronização casa cada jogo ao evento da TheSportsDB
 * por par de times e traz o resultado.
 *
 * Duas origens conforme o torneio:
 *  - COM fase de grupos (bolão atual): participantes derivados dos resultados
 *    reais (ServicoResultadosTorneio).
 *  - SEM fase de grupos (2º bolão, mata-mata puro): confrontos espelhados direto
 *    da API, fase a fase (eventsround por código de rodada), mapeando idTeam->
 *    id_externo, ordenando por data.
 *
 * Idempotente: só grava quando o par muda; nunca apaga um par já definido.
 */
class ServicoMataMata
{
    /** fase.slug => código de rodada da TheSportsDB (mata-mata). */
    private const CODIGO_RODADA_POR_FASE = [
        'round_of_32' => 32,
        'oitavas_de_final' => 16,
        'quartas_de_final' => 8,
        'semifinais' => 4,
        'final' => 2,
        // 'terceiro_lugar': sem código confiável ainda — fica para o admin.
    ];

    public function __construct(
        private readonly ServicoResultadosTorneio $servicoResultadosTorneio,
        private readonly ServicoTheSportsDb $api,
    ) {
    }

    public function persistirParticipantes(Torneio $torneio): int
    {
        $torneio->loadMissing('fases', 'jogos.fase');

        $temGrupos = $torneio->fases->contains(fn ($f) => $f->tipo === 'grupos');

        return $temGrupos
            ? $this->persistirPorDerivacao($torneio)
            : $this->persistirPorApi($torneio);
    }

    private function persistirPorDerivacao(Torneio $torneio): int
    {
        $participantes = $this->servicoResultadosTorneio->participantesPorJogo($torneio);
        $jogos = $torneio->jogos->filter(fn (Jogo $jogo) => $jogo->fase?->tipo !== 'grupos');

        $gravados = 0;
        foreach ($jogos as $jogo) {
            $mandante = $participantes[$jogo->id]['mandante'] ?? null;
            $visitante = $participantes[$jogo->id]['visitante'] ?? null;
            if ($mandante && $visitante && $this->gravarPar($jogo, (int) $mandante->id, (int) $visitante->id)) {
                $gravados++;
            }
        }

        return $gravados;
    }

    private function persistirPorApi(Torneio $torneio): int
    {
        $porIdExterno = Selecao::query()
            ->where('torneio_id', $torneio->id)
            ->whereNotNull('id_externo')
            ->get()
            ->keyBy(fn (Selecao $s) => (int) $s->id_externo);

        $fases = $torneio->fases->keyBy('slug');
        $gravados = 0;

        foreach (self::CODIGO_RODADA_POR_FASE as $slug => $codigoRodada) {
            $fase = $fases->get($slug);
            if (! $fase) {
                continue;
            }

            $eventos = collect($this->api->eventosDaRodada($codigoRodada))
                ->sortBy(fn ($e) => $e['dateEvent'] ?? '')
                ->values();

            $jogos = $torneio->jogos
                ->where('fase_id', $fase->id)
                ->sortBy('data_hora_inicio')
                ->values();

            foreach ($eventos as $i => $evento) {
                $jogo = $jogos->get($i);
                if (! $jogo) {
                    break;
                }
                $mandante = $porIdExterno->get((int) ($evento['idHomeTeam'] ?? 0));
                $visitante = $porIdExterno->get((int) ($evento['idAwayTeam'] ?? 0));
                if ($mandante && $visitante && $this->gravarPar($jogo, (int) $mandante->id, (int) $visitante->id)) {
                    $gravados++;
                }
            }
        }

        return $gravados;
    }

    private function gravarPar(Jogo $jogo, int $mandanteId, int $visitanteId): bool
    {
        $mudou = (int) $jogo->selecao_mandante_id !== $mandanteId
            || (int) $jogo->selecao_visitante_id !== $visitanteId;
        if (! $mudou) {
            return false;
        }
        $jogo->forceFill(['selecao_mandante_id' => $mandanteId, 'selecao_visitante_id' => $visitanteId])->save();

        return true;
    }
}
```

> **NOTA DE INTEGRAÇÃO (ler antes de executar):** no bolão atual, persistir os participantes nas linhas de `Jogo` muda o que `ServicoResultadosTorneio::participantesPorJogo` relê depois. A derivação **recomputa** os participantes a partir dos resultados, então persistir o mesmo valor é inócuo — mas o **bracket previsto por cupom** (`ServicoBracketReal`) também consome `participantesPorJogo`. **Task B4 (regressão) é o gate:** se acusar mudança, restringir `persistirPorDerivacao` a só popular jogos cujo par derivado seja estável (fase anterior encerrada) e validar novamente. O caminho `persistirPorApi` (2º bolão) não toca a derivação.

- [ ] **Step 4: Rodar e confirmar PASS**

Run: `cd backend && php artisan test --filter ResolverMataMataTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add backend/app/Services/ServicoMataMata.php backend/tests/Feature/ResolverMataMataTest.php && git commit -m "feat(copa): persistir participantes reais do mata-mata na linha do jogo"
```

### Task B4: Teste de regressão — bracket previsto por cupom intacto

**Files:**
- Create: `backend/tests/Feature/BracketPrevistoRegressaoTest.php`

- [ ] **Step 1: Escrever o teste**

Garante que, após `ServicoMataMata::persistirParticipantes`, o bracket previsto de um cupom (derivado dos palpites de grupos do usuário) continua coerente — i.e., `ServicoBracketReal::gerar` ainda retorna um bracket e o `podio_palpite` permanece derivado do palpite, não sobrescrito pelos times reais persistidos.

```php
<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Torneio;
use App\Services\ServicoBracketReal;
use App\Services\ServicoMataMata;
use Database\Seeders\TorneioMockadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BracketPrevistoRegressaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_bracket_previsto_segue_valido_apos_persistir_participantes_reais(): void
    {
        $this->seed(TorneioMockadoSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026')->firstOrFail();

        // Cria um cupom mínimo do torneio (sem apostas — bracket vazio mas válido).
        $cupom = Cupom::factory()->create(['torneio_id' => $torneio->id]);

        $servicoBracket = app(ServicoBracketReal::class);
        $antes = $servicoBracket->gerar($cupom->fresh());

        app(ServicoMataMata::class)->persistirParticipantes($torneio->fresh());

        $depois = $servicoBracket->gerar($cupom->fresh());

        // O bracket previsto deve continuar gerando a mesma quantidade de jogos
        // (32 confrontos de mata-mata) — a persistência dos times reais não pode
        // alterar a ESTRUTURA do bracket previsto do cupom.
        $this->assertSameSize($antes, $depois);
    }
}
```

> Se não existir `Cupom::factory()`, criar o cupom via `Cupom::query()->create([...])` com os campos mínimos (ver `database/factories` ou os campos usados em testes existentes como `tests/Feature/ApostasFluxoApiTest.php`). Ajustar a asserção ao formato real de retorno de `ServicoBracketReal::gerar` (array de jogos do bracket).

- [ ] **Step 2: Rodar**

Run: `cd backend && php artisan test --filter BracketPrevistoRegressaoTest`
Expected: PASS. **Se FALHAR** (o bracket previsto mudou), aplicar a mitigação da NOTA DE INTEGRAÇÃO em Task B2 (restringir persistência ao torneio mata-mata puro) e re-rodar B2+B4.

- [ ] **Step 3: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add backend/tests/Feature/BracketPrevistoRegressaoTest.php && git commit -m "test(copa): regressao do bracket previsto apos persistir participantes"
```

### Task B5: Incluir eventos de mata-mata no pool de vinculação e sincronização

**Files:**
- Modify: `backend/app/Console/Commands/VincularEventosJogos.php`
- Modify: `backend/app/Console/Commands/SincronizarResultadosJogos.php`
- Create: `backend/tests/Feature/SyncMataMataResultadoTest.php`

- [ ] **Step 1: Teste que falha**

Crie `backend/tests/Feature/SyncMataMataResultadoTest.php`. Persiste participantes num jogo de mata-mata, faz `Http::fake` dos endpoints de liga com o confronto encerrado e verifica que `jogos:vincular-eventos` + `jogos:sincronizar-resultados` gravam o resultado.

```php
<?php

namespace Tests\Feature;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;
use Database\Seeders\BolaoMataMataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMataMataResultadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_vincula_e_sincroniza_resultado_de_jogo_de_mata_mata_por_par_de_times(): void
    {
        $this->seed(BolaoMataMataSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();

        // Define manualmente os times de um jogo do Round of 32 (BRA x FRA).
        $bra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'BRA')->firstOrFail();
        $fra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'FRA')->firstOrFail();
        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $r32->id)->orderBy('ordem_na_fase')->firstOrFail();
        $jogo->forceFill(['selecao_mandante_id' => $bra->id, 'selecao_visitante_id' => $fra->id])->save();

        // API: rodadas de grupos vazias; mata-mata (past/next) com o confronto encerrado 3x1.
        Http::fake([
            '*eventsround.php*' => Http::response(['events' => []]),
            '*eventspastleague.php*' => Http::response(['events' => [[
                'idEvent' => '900001',
                'idHomeTeam' => (string) $bra->id_externo,
                'idAwayTeam' => (string) $fra->id_externo,
                'intHomeScore' => '3', 'intAwayScore' => '1',
                'strStatus' => 'FT', 'dateEvent' => '2026-06-28',
            ]]]),
            '*eventsnextleague.php*' => Http::response(['events' => []]),
        ]);

        $this->artisan('jogos:vincular-eventos')->assertExitCode(0);
        $jogo->refresh();
        $this->assertSame(900001, (int) $jogo->id_evento_externo);

        $this->artisan('jogos:sincronizar-resultados')->assertExitCode(0);
        $jogo->refresh();
        $this->assertSame('encerrado', $jogo->status);
        $this->assertSame(3, (int) $jogo->resultado->placar_mandante);
        $this->assertSame(1, (int) $jogo->resultado->placar_visitante);
        $this->assertSame($bra->id, (int) $jogo->resultado->selecao_classificada_id);
    }
}
```

- [ ] **Step 2: Rodar e confirmar falha**

Run: `cd backend && php artisan test --filter SyncMataMataResultadoTest`
Expected: FAIL — o evento de mata-mata não está no pool (só `eventosDasRodadas`), então o jogo fica `sem_evento`.

- [ ] **Step 3: Incluir o pool de mata-mata na vinculação**

Em `backend/app/Console/Commands/VincularEventosJogos.php`, troque a linha:

```php
        $eventos = $api->eventosDasRodadas(config('thesportsdb.rodadas', []));
```

por:

```php
        $eventos = [...$api->eventosDasRodadas(config('thesportsdb.rodadas', [])), ...$api->eventosDeMataMata()];
```

- [ ] **Step 4: Incluir o pool de mata-mata na sincronização**

Em `backend/app/Console/Commands/SincronizarResultadosJogos.php`, troque a mesma linha:

```php
        $eventos = $api->eventosDasRodadas(config('thesportsdb.rodadas', []));
```

por:

```php
        $eventos = [...$api->eventosDasRodadas(config('thesportsdb.rodadas', [])), ...$api->eventosDeMataMata()];
```

- [ ] **Step 5: Rodar e confirmar PASS**

Run: `cd backend && php artisan test --filter SyncMataMataResultadoTest`
Expected: PASS.

- [ ] **Step 6: Garantir que nada quebrou no sync existente**

Run: `cd backend && php artisan test --filter SincronizarResultados`
(e qualquer teste existente de `VincularEventos`)
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add backend/app/Console/Commands/VincularEventosJogos.php backend/app/Console/Commands/SincronizarResultadosJogos.php backend/tests/Feature/SyncMataMataResultadoTest.php && git commit -m "feat(copa): sync de resultado de mata-mata via eventos de liga"
```

### Task B6: Command `jogos:resolver-mata-mata` + agendamento

**Files:**
- Create: `backend/app/Console/Commands/ResolverMataMata.php`
- Modify: `backend/routes/console.php`

- [ ] **Step 1: Teste que falha**

Adicione ao `backend/tests/Feature/ResolverMataMataTest.php` um método que invoca o command:

```php
    public function test_command_resolver_mata_mata_roda_para_todos_os_torneios(): void
    {
        $this->seed(\Database\Seeders\TorneioMockadoSeeder::class);
        $this->artisan('jogos:resolver-mata-mata')->assertExitCode(0);
    }
```

- [ ] **Step 2: Rodar e confirmar falha**

Run: `cd backend && php artisan test --filter ResolverMataMataTest`
Expected: FAIL — `Command "jogos:resolver-mata-mata" is not defined.`

- [ ] **Step 3: Criar o command**

Crie `backend/app/Console/Commands/ResolverMataMata.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Torneio;
use App\Services\ServicoMataMata;
use Illuminate\Console\Command;

/**
 * Persiste os participantes reais dos jogos de mata-mata em todos os torneios
 * publicados, para que o pipeline de vinculação/sincronização case por par de times.
 *
 *   php artisan jogos:resolver-mata-mata
 */
class ResolverMataMata extends Command
{
    protected $signature = 'jogos:resolver-mata-mata';

    protected $description = 'Persiste os participantes reais do mata-mata nas linhas de Jogo (todos os torneios).';

    public function handle(ServicoMataMata $servico): int
    {
        $torneios = Torneio::query()->where('status', 'publicado')->get();

        foreach ($torneios as $torneio) {
            $gravados = $servico->persistirParticipantes($torneio);
            $this->line("Torneio {$torneio->id} ({$torneio->edicao}): {$gravados} confrontos atualizados.");
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Rodar e confirmar PASS**

Run: `cd backend && php artisan test --filter ResolverMataMataTest`
Expected: PASS (ambos os métodos).

- [ ] **Step 5: Agendar no scheduler**

Em `backend/routes/console.php`, adicione após as linhas de `jogos:vincular-eventos`:

```php
// Persiste os participantes reais do mata-mata (antes de vincular eventos por par de times).
Schedule::command('jogos:resolver-mata-mata')->hourly()->withoutOverlapping();
```

> Ordem lógica: `resolver-mata-mata` (define os times) → `vincular-eventos` (casa por par) → `sincronizar-resultados` (traz placar). Como `vincular` roda hourly e `sincronizar` everyMinute, a convergência acontece ao longo dos minutos seguintes — aceitável para a cadência de jogos.

- [ ] **Step 6: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add backend/app/Console/Commands/ResolverMataMata.php backend/routes/console.php backend/tests/Feature/ResolverMataMataTest.php && git commit -m "feat(copa): command e agendamento de resolucao do mata-mata"
```

---

# PARTE C — Backend: trava de aposta por jogo (reforço explícito)

### Task C1: Rejeitar aposta de mata-mata em jogo sem participantes (quando é o único item)

**Contexto:** Hoje `ServicoApostas::normalizarItem` retorna `null` (ignora silenciosamente) para mata-mata sem participantes — bom para o auto-save em lote, mas não comunica o bloqueio. Reforçamos com um teste que documenta/garante o comportamento de não criar aposta nesse caso.

**Files:**
- Create: `backend/tests/Feature/TravaApostaMataMataTest.php`

- [ ] **Step 1: Escrever o teste**

```php
<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Fase;
use App\Models\Jogo;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoApostas;
use Database\Seeders\BolaoMataMataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravaApostaMataMataTest extends TestCase
{
    use RefreshDatabase;

    public function test_nao_cria_aposta_em_jogo_de_mata_mata_sem_participantes(): void
    {
        $this->seed(BolaoMataMataSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();
        $usuario = Usuario::factory()->create();
        $cupom = Cupom::query()->create([
            'torneio_id' => $torneio->id,
            'usuario_id' => $usuario->id,
            'codigo' => 'MM-TESTE-1',
            'status' => 'ativo',
            'pedido_checkout_id' => null,
        ]);

        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $r32->id)->firstOrFail();
        $this->assertNull($jogo->selecao_mandante_id); // placeholder, sem times

        app(ServicoApostas::class)->salvarLote($cupom, $usuario, [[
            'tipo' => 'placar_jogo_eliminatoria',
            'jogo_id' => $jogo->id,
            'placar_mandante' => 2,
            'placar_visitante' => 1,
        ]]);

        $this->assertDatabaseMissing('apostas', ['cupom_id' => $cupom->id, 'jogo_id' => $jogo->id]);
    }
}
```

> Ajuste os campos de `Cupom::query()->create([...])` aos obrigatórios reais (ver migration de cupons / testes existentes). Se `Usuario::factory()` não existir, criar via `Usuario::query()->create([...])` com nome/email/password.

- [ ] **Step 2: Rodar e confirmar PASS**

Run: `cd backend && php artisan test --filter TravaApostaMataMataTest`
Expected: PASS (o comportamento atual de ignorar já garante isso — este teste o trava contra regressão).

- [ ] **Step 3: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add backend/tests/Feature/TravaApostaMataMataTest.php && git commit -m "test(copa): trava de aposta de mata-mata sem participantes"
```

---

# PARTE D — Frontend: bolão ativo (hub) no header

### Task D1: Store `bolaoAtivo`

**Files:**
- Create: `frontend/src/stores/bolaoAtivo.ts`

- [ ] **Step 1: Criar o store**

```ts
import { ref } from 'vue'
import { defineStore } from 'pinia'
import { requisicaoApi } from '../services/api'
import type { Bolao, RespostaBoloes } from '../tipos'

const CHAVE = 'bolao_ativo_id'

export const usarBolaoAtivoStore = defineStore('bolaoAtivo', () => {
  const lista = ref<Bolao[]>([])
  const ativoId = ref<number | null>(
    localStorage.getItem(CHAVE) ? Number(localStorage.getItem(CHAVE)) : null,
  )
  const carregando = ref(false)

  function definirAtivo(id: number | null) {
    ativoId.value = id
    if (id === null) localStorage.removeItem(CHAVE)
    else localStorage.setItem(CHAVE, String(id))
  }

  async function carregar() {
    carregando.value = true
    try {
      const resp = await requisicaoApi<RespostaBoloes>('/boloes')
      lista.value = [...resp.ativos, ...resp.encerrados]
      // Default: primeiro ativo, se ainda não houver seleção válida.
      const valido = lista.value.some((b) => b.id === ativoId.value)
      if (!valido) definirAtivo(resp.ativos[0]?.id ?? lista.value[0]?.id ?? null)
    } finally {
      carregando.value = false
    }
  }

  const ativo = () => lista.value.find((b: Bolao) => b.id === ativoId.value) ?? null

  return { lista, ativoId, ativo, carregando, carregar, definirAtivo }
})
```

- [ ] **Step 2: Type-check**

Run: `cd frontend && npm run build`
Expected: build sem erros de tipo relacionados ao novo arquivo.

- [ ] **Step 3: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add frontend/src/stores/bolaoAtivo.ts && git commit -m "feat(front): store de bolao ativo"
```

### Task D2: Componente `SeletorBolao` no header

**Files:**
- Create: `frontend/src/components/SeletorBolao.vue`
- Modify: `frontend/src/components/AppHeader.vue`

- [ ] **Step 1: Criar o componente**

```vue
<template>
  <div class="relative">
    <button
      type="button"
      class="flex items-center gap-2 rounded-lg bg-bg-input px-3 py-1.5 text-sm font-medium text-text-secondary transition hover:text-text"
      @click="aberto = !aberto"
    >
      <span class="max-w-[10rem] truncate">{{ store.ativo()?.nome ?? 'Bolão' }}</span>
      <svg class="h-4 w-4" :class="{ 'rotate-180': aberto }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <div v-if="aberto" class="absolute right-0 z-50 mt-1 w-56 rounded-xl border border-border bg-bg-card p-1 shadow-xl">
      <button
        v-for="b in store.lista"
        :key="b.id"
        type="button"
        class="block w-full rounded-lg px-3 py-2 text-left text-sm transition hover:bg-bg-card-hover"
        :class="b.id === store.ativoId ? 'text-primary' : 'text-text-secondary'"
        @click="selecionar(b.id)"
      >
        {{ b.nome }}
      </button>
    </div>
    <div v-if="aberto" class="fixed inset-0 z-40" @click="aberto = false" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { usarBolaoAtivoStore } from '../stores/bolaoAtivo'

const store = usarBolaoAtivoStore()
const aberto = ref(false)

onMounted(() => {
  if (store.lista.length === 0) store.carregar()
})

function selecionar(id: number) {
  store.definirAtivo(id)
  aberto.value = false
}
</script>
```

- [ ] **Step 2: Montar no AppHeader (desktop, quando autenticado)**

Em `frontend/src/components/AppHeader.vue`, importe e renderize o seletor à esquerda do dropdown de usuário. No `<script setup>` adicione `import SeletorBolao from './SeletorBolao.vue'`. No template, dentro do bloco `<template v-if="autenticacao.estaAutenticado">` da nav desktop, antes do `<!-- User dropdown -->`, insira:

```vue
          <SeletorBolao class="mr-1" />
```

- [ ] **Step 3: Verificar no preview**

Subir o frontend e confirmar que o seletor aparece e troca o bolão ativo (ver Verificação no fim do plano).

- [ ] **Step 4: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add frontend/src/components/SeletorBolao.vue frontend/src/components/AppHeader.vue && git commit -m "feat(front): seletor de bolao no header"
```

### Task D3: Painel filtra cupons pelo bolão ativo

**Files:**
- Modify: `frontend/src/views/PainelView.vue`

- [ ] **Step 1: Filtrar a lista de cupons**

Em `PainelView.vue`, onde a lista de cupons é exibida (carregada de `/cupons`), filtre por `cupom.torneio_id === store.ativoId` quando houver bolão ativo. Importe `usarBolaoAtivoStore`, crie `const bolao = usarBolaoAtivoStore()` e derive um `computed` `cuponsVisiveis` a partir da lista atual:

```ts
import { computed } from 'vue'
import { usarBolaoAtivoStore } from '../stores/bolaoAtivo'

const bolao = usarBolaoAtivoStore()
// `cupons` é a ref já existente com a lista carregada de /cupons.
const cuponsVisiveis = computed(() =>
  bolao.ativoId ? cupons.value.filter((c) => c.torneio_id === bolao.ativoId) : cupons.value,
)
```

Troque a iteração do template de `cupons` para `cuponsVisiveis`. Garanta `bolao.carregar()` no `onMounted` se `bolao.lista` estiver vazio.

- [ ] **Step 2: Build**

Run: `cd frontend && npm run build`
Expected: sem erros de tipo.

- [ ] **Step 3: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add frontend/src/views/PainelView.vue && git commit -m "feat(front): painel filtra cupons pelo bolao ativo"
```

---

# PARTE E — Frontend: CupomView no modo mata-mata + Admin por bolão

### Task E1: CupomView consome o torneio do cupom (não o torneio público fixo)

**Contexto:** Hoje a `CupomView` carrega `/torneio` (sempre o torneio público mais recente). Para um cupom do bolão mata-mata, ela precisa carregar o torneio do próprio cupom (`/torneios/{torneioId}`), e o bracket real compartilhado já vem por `/cupons/{id}/bracket`. O backend já expõe `GET /torneios/{torneio}` e `GET /cupons/{cupom}/bracket`.

**Files:**
- Modify: `frontend/src/views/CupomView.vue`

- [ ] **Step 1: Carregar o torneio pelo torneio_id do cupom**

Na `CupomView`, após carregar o cupom (`GET /cupons/{id}` retorna `cupom.torneio_id`), troque o carregamento do torneio para usar o id do cupom:

```ts
// onde hoje carrega o torneio público:
// const resp = await requisicaoApi<{ torneio: Torneio }>('/torneio')
const torneioId = cupom.value?.torneio_id
const resp = torneioId
  ? await requisicaoApi<{ torneio: Torneio }>(`/torneios/${torneioId}`)
  : await requisicaoApi<{ torneio: Torneio }>('/torneio')
```

> Localize o ponto exato pelo trecho que chama `'/torneio'` no `<script setup>` da `CupomView` e ajuste apenas essa origem. O restante (apostas, bracket, ranking) já usa `torneioId` derivado.

- [ ] **Step 2: Esconder a aba/seção de fase de grupos quando o torneio não tem fase de grupos**

A `CupomView` deriva `fasesRodadas`/`diasComJogos` das fases do torneio. Como o bolão mata-mata não tem fase `tipo === 'grupos'`, a sub-aba de "Jogos" (grupos) ficará naturalmente vazia. Adicione um computed para detectar o modo e ocultar a navegação de grupos:

```ts
const temFaseGrupos = computed(() => torneio.value?.fases?.some((f) => f.tipo === 'grupos') ?? true)
```

No template, condicione a sub-aba de grupos a `v-if="temFaseGrupos"` e abra direto no "Chaveamento" quando `!temFaseGrupos`.

- [ ] **Step 3: Build + verificação manual**

Run: `cd frontend && npm run build`
Expected: sem erros. Verificação visual no preview (um cupom do bolão mata-mata mostra só o chaveamento).

- [ ] **Step 4: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add frontend/src/views/CupomView.vue && git commit -m "feat(front): CupomView consome torneio do cupom e oculta grupos no mata-mata"
```

### Task E2: Admin — entrada por bolão (lista → painel escopado)

**Files:**
- Create: `frontend/src/views/AdminBoloesView.vue`
- Modify: `frontend/src/router/index.ts`
- Modify: `frontend/src/views/AdminPainelView.vue`

- [ ] **Step 1: Criar a lista de bolões do admin**

`frontend/src/views/AdminBoloesView.vue`:

```vue
<template>
  <div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="mb-1 text-2xl font-black text-text">Administração — Bolões</h1>
    <p class="mb-6 text-sm text-text-muted">Selecione um bolão para gerenciar.</p>

    <div v-if="carregando" class="py-12 text-center text-text-muted">Carregando…</div>
    <div v-else class="grid gap-3">
      <RouterLink
        v-for="b in lista"
        :key="b.id"
        :to="{ name: 'admin-bolao', params: { torneio: b.id } }"
        class="flex items-center justify-between rounded-2xl border border-border bg-bg-card p-5 transition hover:border-primary"
      >
        <div>
          <h2 class="text-base font-bold text-text">{{ b.nome }}</h2>
          <p class="text-xs text-text-muted">{{ b.edicao }} · {{ b.status }}</p>
        </div>
        <span class="text-primary">→</span>
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { requisicaoApi } from '../services/api'
import type { Bolao, RespostaBoloes } from '../tipos'

const lista = ref<Bolao[]>([])
const carregando = ref(true)

onMounted(async () => {
  try {
    const resp = await requisicaoApi<RespostaBoloes>('/boloes')
    lista.value = [...resp.ativos, ...resp.encerrados]
  } finally {
    carregando.value = false
  }
})
</script>
```

- [ ] **Step 2: Ajustar rotas do admin**

Em `frontend/src/router/index.ts`, importe `AdminBoloesView` e substitua a rota `/admin` por duas rotas:

```ts
import AdminBoloesView from '../views/AdminBoloesView.vue'
```

```ts
    {
      path: '/admin',
      name: 'admin',
      component: AdminBoloesView,
      meta: { requerAutenticacao: true, requerAdministrador: true },
    },
    {
      path: '/admin/boloes/:torneio',
      name: 'admin-bolao',
      component: AdminPainelView,
      props: true,
      meta: { requerAutenticacao: true, requerAdministrador: true },
    },
```

- [ ] **Step 3: AdminPainelView recebe `torneio` da rota**

Em `frontend/src/views/AdminPainelView.vue`, adicione a prop e use-a como o torneio em contexto em vez do seletor interno:

```ts
const props = defineProps<{ torneio?: string }>()
const torneioId = computed(() => (props.torneio ? Number(props.torneio) : null))
```

Onde o componente hoje chama `GET /admin/dados` (sem escopo) ou usa `trocarBolao()`, passe o `torneio_id` do contexto. O endpoint `GET /admin/dados` aceita filtro por `torneio_id` (ver `PainelAdministradorController::dados`). Ajuste a chamada para `requisicaoApi('/admin/dados?torneio_id=' + torneioId.value, ...)` e remova o seletor interno de bolão (substituído pela entrada por rota). Exiba o nome do bolão em contexto no topo do painel.

> Esta é a tarefa de maior toque no `AdminPainelView`. Mantenha todas as operações (resultado, regras, cupons, compras, pódio, vincular evento) funcionando, apenas trocando a fonte do `torneio_id` para a rota. Verifique cada chamada admin que recebe `{torneio}` na URL — elas já existem em `routes/api.php`.

- [ ] **Step 4: Build**

Run: `cd frontend && npm run build`
Expected: sem erros de tipo.

- [ ] **Step 5: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026" && git add frontend/src/views/AdminBoloesView.vue frontend/src/router/index.ts frontend/src/views/AdminPainelView.vue && git commit -m "feat(front): admin entra por bolao em rota escopada"
```

---

## Verificação final (end-to-end)

- [ ] **Backend:** `cd backend && php artisan migrate:fresh --seed` cria os dois bolões. `php artisan test` passa inteiro.
- [ ] **Sync manual:** `php artisan jogos:resolver-mata-mata && php artisan jogos:vincular-eventos && php artisan jogos:sincronizar-resultados` roda sem erro (com a API real ou faked).
- [ ] **Frontend (preview):** subir `npm run dev` (frontend) + `php artisan serve --port=8888` (backend). Logar como admin (`admin@interworldcup.local` / `12345678`).
  - Header mostra o **SeletorBolao**; trocar o bolão filtra o painel.
  - `/boloes` lista os dois bolões; comprar cupom do mata-mata gera cupom no torneio `2026-MM`.
  - Cupom do mata-mata abre direto no chaveamento real compartilhado; jogo sem times = "A definir".
  - `/admin` lista os bolões; entrar em um abre o painel escopado àquele torneio.

## Notas de risco (ler antes de executar)

1. **Integração `ServicoMataMata` ↔ `ServicoResultadosTorneio` (Task B2/B4):** o teste de regressão B4 é o gate. Se o bracket previsto por cupom mudar, restringir a persistência ao torneio mata-mata puro.
2. **Round codes da API:** **Round of 32 = `r=32`** confirmado em 2026-06-26 (4 jogos já publicados, `idTeam` batendo com `id_externo`). R16/Quartas/Semi/Final = `16`/`8`/`4`/`2` por convenção (ainda vazios; confirmar quando publicarem). Terceiro lugar sem código confiável → admin define. `r=2` colide com grupos rodada 2, mas o casamento por par de times anula o efeito.
3. **`AdminPainelView` (Task E2 Step 3):** maior superfície de mudança no frontend; revisar cada operação admin após trocar a fonte do `torneio_id`.
4. **Factories/campos de teste:** ajustar `Cupom`/`Usuario` nos testes aos campos obrigatórios reais do projeto (conferir `database/factories` e testes existentes).
