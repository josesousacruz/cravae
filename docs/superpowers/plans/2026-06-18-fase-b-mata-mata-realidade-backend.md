# Fase B — Mata-mata pela realidade (backend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Mata-mata deixa de ser derivado dos palpites de grupo (fantasia) e passa a ser pela realidade: os confrontos são os times reais (já computados por `ServicoResultadosTorneio`), o usuário palpita placar dos jogos reais, e o campeão/vice/3º vira um palpite explícito (`tipo='podio'`). Aposenta o `ServicoBracketCupom`.

**Architecture:** O "motor da realidade" já existe — `ServicoResultadosTorneio::participantesDoJogo(Jogo): {mandante:?Selecao, visitante:?Selecao}` deriva os times reais de cada jogo eliminatório a partir dos `ResultadoJogo` reais (round_of_32 pelos grupos reais, fases seguintes pelos vencedores reais, melhores terceiros pela regra da Copa). A Fase B troca os 3 acoplamentos da fantasia para esse motor, adiciona a aposta `podio`, desacopla grupos×mata-mata, e troca o endpoint de bracket para a visão real.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit (Feature tests, `RefreshDatabase` + `$this->seed()`). Rodar de `backend/`.

**Decisões (do spec):** aposta única `tipo='podio'` (conteudo `{campeao_selecao_id, vice_selecao_id, terceiro_selecao_id}`), fecha em `torneio.data_inicio - 1h`; grupos e mata-mata desacoplados (mata-mata abre quando os participantes reais existem, sem exigir palpites de grupo).

**Estado atual relevante (já verificado):**
- 32 jogos de mata-mata têm `data_hora_inicio` (ex.: `2026-06-28 16:00`); `selecao_mandante_id`/`visitante_id` ficam NULL (times derivados on-the-fly).
- `ServicoResultadosTorneio::participantesDoJogo(Jogo)` e `resolverPodio(Torneio)` já entregam realidade.
- Fechamento eliminatória já usa `jogo.data_hora_inicio` ([ServicoFechamentoApostas.php:47](../../../backend/app/Services/ServicoFechamentoApostas.php)).
- Pódio hoje é derivado da fantasia em [ServicoPontuacao::pontuarPodioDerivado](../../../backend/app/Services/ServicoPontuacao.php).

---

## Estrutura de arquivos

**Criar:**
- `backend/app/Services/ServicoBracketReal.php` — monta a visão de bracket real do cupom (substitui `ServicoBracketCupom` no endpoint).

**Modificar:**
- `backend/app/Http/Requests/SalvarApostasRequest.php` — aceitar `tipo='podio'`.
- `backend/app/Services/ServicoApostas.php` — usar participantes reais; tratar `podio`; remover acoplamento de fantasia.
- `backend/app/Services/ServicoFechamentoApostas.php` — desacoplar (remover desbloqueio progressivo); fechamento do `podio`.
- `backend/app/Services/ServicoPontuacao.php` — pontuar `podio` explícito; remover derivação de fantasia.
- `backend/app/Http/Controllers/CupomController.php` — endpoint `bracket` usa `ServicoBracketReal`.
- `backend/tests/Feature/BracketCupomApiTest.php` — reescrever para realidade.

**Remover:**
- `backend/app/Services/ServicoBracketCupom.php` — aposentado.
- `backend/tests/Feature/LoteChainCompletaTest.php` e `backend/tests/Feature/LoteApostasMataMataTest.php` — testavam a cadeia de fantasia; substituídos por testes de realidade.

---

## Task 1: Request — aceitar `tipo='podio'`

**Files:**
- Modify: `backend/app/Http/Requests/SalvarApostasRequest.php`

- [ ] **Step 1: Adicionar 'podio' aos tipos e campos de seleção**

Em `SalvarApostasRequest.php`, adicionar `'podio'` à const:

```php
    private const TIPOS_SUPORTADOS = [
        'placar_jogo_grupos',
        'placar_jogo_eliminatoria',
        'artilheiro',
        'podio',
    ];
```

No `rules()`, adicionar (após `apostas.*.selecao_id`):

```php
            'apostas.*.campeao_selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'apostas.*.vice_selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
            'apostas.*.terceiro_selecao_id' => ['nullable', 'integer', 'exists:selecoes,id'],
```

- [ ] **Step 2: Validar o podio no `after()`**

No `match ($tipo)` dentro de `after()`, adicionar o braço:

```php
                        'podio' => $this->validarPodio($validator, $indice, $aposta),
```

E adicionar o método privado:

```php
    /**
     * @param array<string, mixed> $aposta
     */
    private function validarPodio(Validator $validator, int $indice, array $aposta): void
    {
        foreach (['torneio_id', 'campeao_selecao_id', 'vice_selecao_id', 'terceiro_selecao_id'] as $campo) {
            if (! isset($aposta[$campo])) {
                $validator->errors()->add("apostas.$indice.$campo", 'Campo obrigatorio para o palpite de podio.');
            }
        }

        $ids = array_filter([
            $aposta['campeao_selecao_id'] ?? null,
            $aposta['vice_selecao_id'] ?? null,
            $aposta['terceiro_selecao_id'] ?? null,
        ], fn ($id) => $id !== null);

        if (count($ids) === 3 && count(array_unique($ids)) !== 3) {
            $validator->errors()->add("apostas.$indice.podio", 'Campeao, vice e terceiro devem ser selecoes diferentes.');
        }
    }
```

- [ ] **Step 3: Commit**

```bash
git add backend/app/Http/Requests/SalvarApostasRequest.php
git commit -m "feat: request aceita aposta de podio (campeao/vice/terceiro)"
```

---

## Task 2: ServicoApostas — participantes reais + `podio` + remover fantasia

**Files:**
- Modify: `backend/app/Services/ServicoApostas.php`
- Test: `backend/tests/Feature/MataMataRealidadeTest.php` (criar)

- [ ] **Step 1: Escrever o teste que falha**

Criar `backend/tests/Feature/MataMataRealidadeTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MataMataRealidadeTest extends TestCase
{
    use RefreshDatabase;

    private function criarCupom(string $email): array
    {
        $usuario = Usuario::query()->create([
            'nome' => 'U '.strtok($email, '@'), 'email' => $email,
            'telefone' => '71999999999', 'cpf_cnpj' => '12345678901',
            'password' => '12345678', 'perfil' => 'usuario',
        ]);
        Sanctum::actingAs($usuario);
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $pedido = $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])->assertCreated()->json('pedido');
        $cupom = $this->postJson("/api/pedidos-checkout/{$pedido['id']}/confirmar-sandbox", [])->assertOk()->json('cupom');

        return [$usuario, Cupom::query()->findOrFail($cupom['id']), $torneio];
    }

    public function test_palpite_eliminatoria_usa_participantes_reais_sem_exigir_grupos(): void
    {
        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom('real@teste.local');

        // Define o RESULTADO REAL de TODOS os jogos de grupos -> habilita os participantes
        // reais do round_of_32 (sem o usuario ter palpitado nenhum grupo).
        $this->lancarResultadosDeGrupos($torneio);

        // Um jogo do round_of_32 agora tem participantes reais resolviveis.
        $jogoR32 = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('slug', 'round_of_32'))
            ->orderBy('ordem_na_fase')
            ->firstOrFail();

        Sanctum::actingAs($usuario);
        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogoR32->id,
                'placar_mandante' => 2,
                'placar_visitante' => 1,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('apostas', [
            'cupom_id' => $cupom->id,
            'tipo' => 'placar_jogo_eliminatoria',
            'jogo_id' => $jogoR32->id,
        ]);
        // selecao_classificada_id deve ter sido resolvida a partir dos participantes REAIS.
        $aposta = Aposta::query()->where('cupom_id', $cupom->id)->where('jogo_id', $jogoR32->id)->firstOrFail();
        $this->assertNotNull($aposta->conteudo['selecao_classificada_id']);
    }

    /** Lança resultados reais de todos os jogos de grupos de forma determinística. */
    private function lancarResultadosDeGrupos(Torneio $torneio): void
    {
        $jogos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))
            ->whereNotNull('selecao_mandante_id')
            ->whereNotNull('selecao_visitante_id')
            ->get();

        foreach ($jogos as $i => $jogo) {
            // Placar alternado para gerar classificacao sem empate total.
            $m = ($i % 3) + 1;
            ResultadoJogo::query()->updateOrCreate(
                ['jogo_id' => $jogo->id],
                ['placar_mandante' => $m, 'placar_visitante' => 0, 'selecao_classificada_id' => null, 'encerrado_at' => now()],
            );
            $jogo->update(['status' => 'encerrado']);
        }
    }
}
```

- [ ] **Step 2: Rodar e confirmar que FALHA**

Run: `cd backend && php artisan test --filter=test_palpite_eliminatoria_usa_participantes_reais_sem_exigir_grupos`
Expected: FAIL — hoje o `ServicoApostas` deriva participantes do bracket de fantasia (que exige palpites de grupo); sem palpites de grupo, o item é ignorado e a aposta não é salva.

- [ ] **Step 3: Trocar o serviço para usar participantes reais**

Em `backend/app/Services/ServicoApostas.php`:

Trocar o construtor (remover `ServicoBracketCupom`, injetar `ServicoResultadosTorneio`):

```php
    public function __construct(
        private readonly ServicoFechamentoApostas $servicoFechamentoApostas,
        private readonly ServicoResultadosTorneio $servicoResultadosTorneio,
    ) {
    }
```

No `normalizarItem`, no braço `placar_jogo_eliminatoria`, substituir o bloco (linhas com `unsetRelation` + `servicoBracketCupom->participantesDoJogo`) por:

```php
            if ($tipo === 'placar_jogo_eliminatoria') {
                $participantes = $this->servicoResultadosTorneio->participantesDoJogo($jogo);

                if (! $participantes['mandante'] || ! $participantes['visitante']) {
                    // Participantes reais ainda nao definidos (fase anterior sem resultado):
                    // ignora o item no lote em vez de derrubar os demais.
                    return null;
                }

                $selecaoClassificadaId = $this->resolverClassificadoEliminatoria(
                    $participantes['mandante'],
                    $participantes['visitante'],
                    $placarMandante,
                    $placarVisitante,
                    $penalMandante,
                    $penalVisitante,
                );
            }
```

Atualizar os imports no topo: remover `use App\Services\ServicoBracketCupom;` se houver import explícito (mesmo namespace, normalmente sem `use`); garantir que `ServicoResultadosTorneio` é resolvível (mesmo namespace `App\Services`, então sem `use` necessário).

- [ ] **Step 4: Tratar o tipo `podio` no `normalizarItem`**

Antes do `throw ValidationException` final do `normalizarItem`, adicionar o braço:

```php
        if ($tipo === 'podio') {
            return [
                'tipo' => 'podio',
                'torneio_id' => (int) $item['torneio_id'],
                'fase_id' => null,
                'rodada_id' => null,
                'grupo_id' => null,
                'jogo_id' => null,
                'selecao_id' => null,
                'jogador_id' => null,
                'conteudo' => [
                    'campeao_selecao_id' => (int) $item['campeao_selecao_id'],
                    'vice_selecao_id' => (int) $item['vice_selecao_id'],
                    'terceiro_selecao_id' => (int) $item['terceiro_selecao_id'],
                ],
            ];
        }
```

(O `localizarAposta` já casa por `tipo` + `torneio_id` + demais nulls, então um cupom tem no máximo uma aposta `podio` por torneio — edições atualizam a mesma linha.)

- [ ] **Step 5: Rodar e confirmar que PASSA**

Run: `cd backend && php artisan test --filter=test_palpite_eliminatoria_usa_participantes_reais_sem_exigir_grupos`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/ServicoApostas.php backend/tests/Feature/MataMataRealidadeTest.php
git commit -m "feat: ServicoApostas usa participantes reais e aceita aposta de podio"
```

---

## Task 3: ServicoFechamentoApostas — desacoplar + fechamento do podio

**Files:**
- Modify: `backend/app/Services/ServicoFechamentoApostas.php`

- [ ] **Step 1: Remover o desbloqueio progressivo (desacoplar grupos × mata-mata)**

Em `validar()`, remover a chamada `$this->validarDesbloqueioProgressivo($cupom, $dados);` (e o método `validarDesbloqueioProgressivo` inteiro). O `validar` fica:

```php
    public function validar(Cupom $cupom, array $dados): void
    {
        if (! in_array($cupom->status, ['ativo', 'aguardando_pagamento'], true)) {
            throw ValidationException::withMessages([
                'cupom' => 'Este cupom nao pode receber apostas.',
            ]);
        }
    }
```

> A "abertura por fase" agora é garantida pelo `ServicoApostas`: jogo de mata-mata sem participantes reais retorna `null` (item ignorado). Remover os imports `Fase` e (se não usados em outro ponto) os que ficarem órfãos — conferir antes de remover.

- [ ] **Step 2: Fechamento do podio**

Em `resolverDataFechamento`, adicionar o tratamento do `podio` (fecha junto com o início do torneio, como o artilheiro). Trocar o bloco final:

```php
        $torneio = Torneio::query()->with('fases')->findOrFail($dados['torneio_id']);

        if (in_array($tipo, ['artilheiro', 'podio'], true)) {
            return $torneio->data_inicio?->copy()->subHour();
        }

        return null;
```

- [ ] **Step 3: Typecheck rápido + suíte de fechamento**

Run: `cd backend && php artisan test --filter=FechamentoApostasTest`
Expected: pode haver testes que dependiam do desbloqueio progressivo — se algum quebrar por isso, marque para ajuste na Task 7 (reescrita de testes) e siga. Os testes de prazo por dia/grupos devem continuar verdes.

- [ ] **Step 4: Commit**

```bash
git add backend/app/Services/ServicoFechamentoApostas.php
git commit -m "feat: desacopla grupos do mata-mata e fecha podio no inicio do torneio"
```

---

## Task 4: ServicoPontuacao — pontuar `podio` explícito, remover fantasia

**Files:**
- Modify: `backend/app/Services/ServicoPontuacao.php`
- Test: `backend/tests/Feature/MataMataRealidadeTest.php`

- [ ] **Step 1: Escrever o teste que falha**

Adicionar em `MataMataRealidadeTest`:

```php
public function test_aposta_podio_pontua_contra_resultado_real(): void
{
    $this->seed();
    [$usuario, $cupom, $torneio] = $this->criarCupom('podio@teste.local');

    $selecoes = \App\Models\Selecao::query()->where('torneio_id', $torneio->id)->take(3)->pluck('id')->all();
    [$campeao, $vice, $terceiro] = $selecoes;

    // Garante regras de pontuacao do podio (o seed ja cria; reforca pontos > 0).
    foreach (['campeao' => 50, 'vice_campeao' => 30, 'terceiro_colocado' => 20] as $chave => $pontos) {
        \App\Models\RegraPontuacao::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'fase_id' => null, 'chave' => $chave],
            ['nome' => $chave, 'pontos' => $pontos, 'ativo' => true],
        );
    }

    // Palpite de podio criado direto (o endpoint de lote fecharia pelo prazo, pois o
    // torneio do seed ja comecou; aqui o foco e a PONTUACAO, nao o prazo).
    $cupom->apostas()->create([
        'tipo' => 'podio',
        'torneio_id' => $torneio->id,
        'fase_id' => null, 'rodada_id' => null, 'grupo_id' => null,
        'jogo_id' => null, 'selecao_id' => null, 'jogador_id' => null,
        'conteudo' => [
            'campeao_selecao_id' => $campeao,
            'vice_selecao_id' => $vice,
            'terceiro_selecao_id' => $terceiro,
        ],
    ]);

    // Resultado real do torneio: campeao acerta, vice/terceiro erram.
    \App\Models\ResultadoTorneio::query()->updateOrCreate(
        ['torneio_id' => $torneio->id],
        ['campeao_selecao_id' => $campeao, 'vice_campeao_selecao_id' => 999999, 'terceiro_colocado_selecao_id' => 999998],
    );

    app(\App\Services\ServicoPontuacao::class)->recalcularCupom($cupom->fresh());

    $pontuacao = \App\Models\PontuacaoCupom::query()->where('cupom_id', $cupom->id)->firstOrFail();
    $this->assertSame(50, (int) $pontuacao->pontuacao_total, 'esperava 50 pts (so o campeao correto)');
}
```

- [ ] **Step 2: Rodar e confirmar que FALHA**

Run: `cd backend && php artisan test --filter=test_aposta_podio_pontua_contra_resultado_real`
Expected: FAIL — hoje a pontuação de pódio é derivada da fantasia (ignora a aposta `podio`).

- [ ] **Step 3: Atualizar o serviço de pontuação**

Em `backend/app/Services/ServicoPontuacao.php`:

Remover a dependência de fantasia no construtor:

```php
    public function __construct()
    {
    }
```

No `recalcularCupom`, dentro do `foreach ($cupom->apostas as $aposta)`, adicionar o braço do `podio` (após o `artilheiro`):

```php
                if ($aposta->tipo === 'podio') {
                    $resultadoPodio = $this->pontuarPodio($aposta, $torneio, $regras);
                    $total += $resultadoPodio['pontos'];
                    $palpitesFinaisCorretos += $resultadoPodio['acertos'];
                }
```

Remover as 3 linhas que chamavam a fantasia (`$resultadoDerivado = $this->pontuarPodioDerivado(...)` e os dois `+=`).

Substituir o método `pontuarPodioDerivado` (e remover `resolverPodioPrevisto`) por `pontuarPodio`:

```php
    /**
     * @return array{pontos:int,acertos:int}
     */
    private function pontuarPodio(Aposta $aposta, Torneio $torneio, Collection $regras): array
    {
        $conteudo = $aposta->conteudo;
        $previsto = [
            'campeao' => (int) ($conteudo['campeao_selecao_id'] ?? 0) ?: null,
            'vice' => (int) ($conteudo['vice_selecao_id'] ?? 0) ?: null,
            'terceiro' => (int) ($conteudo['terceiro_selecao_id'] ?? 0) ?: null,
        ];
        $real = $this->resolverPodioReal($torneio);

        $total = 0;
        $acertos = 0;

        foreach ([
            'campeao' => 'campeao',
            'vice' => 'vice_campeao',
            'terceiro' => 'terceiro_colocado',
        ] as $campo => $regra) {
            if (! $previsto[$campo] || ! $real[$campo] || $previsto[$campo] !== $real[$campo]) {
                continue;
            }

            $pontos = $this->obterPontosRegra($regras, $regra);
            $regraId = $this->resolverIdRegra($regras, $regra);
            if ($pontos <= 0 || $regraId <= 0) {
                continue;
            }

            $total += $pontos;
            $acertos++;
            EventoPontuacao::query()->create([
                'cupom_id' => $aposta->cupom_id,
                'regra_pontuacao_id' => $regraId,
                'jogo_id' => null,
                'aposta_id' => $aposta->id,
                'pontos' => $pontos,
                'descricao' => 'Palpite de podio ('.$regra.')',
            ]);
        }

        return ['pontos' => $total, 'acertos' => $acertos];
    }
```

Manter `resolverPodioReal`, `obterPontosRegra`, `resolverIdRegra`. Conferir que não restou nenhuma referência a `servicoBracketCupom` no arquivo.

- [ ] **Step 4: Rodar e confirmar que PASSA**

Run: `cd backend && php artisan test --filter=test_aposta_podio_pontua_contra_resultado_real`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/ServicoPontuacao.php backend/tests/Feature/MataMataRealidadeTest.php
git commit -m "feat: pontuacao do podio vem da aposta explicita vs resultado real"
```

---

## Task 5: Bracket real no endpoint do cupom

**Files:**
- Create: `backend/app/Services/ServicoBracketReal.php`
- Modify: `backend/app/Http/Controllers/CupomController.php`
- Test: `backend/tests/Feature/BracketCupomApiTest.php` (reescrever — feito na Task 7; aqui adicionamos um teste mínimo)

- [ ] **Step 1: Criar `ServicoBracketReal`**

`backend/app/Services/ServicoBracketReal.php`:

```php
<?php

namespace App\Services;

use App\Models\Cupom;
use App\Models\Jogo;

class ServicoBracketReal
{
    public function __construct(
        private readonly ServicoResultadosTorneio $servicoResultadosTorneio,
    ) {
    }

    /**
     * Visao do bracket REAL para o cupom: cada jogo de mata-mata com os times
     * reais (quando ja definidos), o resultado real e o palpite do cupom.
     *
     * @return array<int, array<string, mixed>>
     */
    public function gerar(Cupom $cupom): array
    {
        $torneio = $cupom->torneio()->with([
            'fases' => fn ($q) => $q->orderBy('ordem'),
            'jogos' => fn ($q) => $q->orderBy('ordem_na_fase'),
            'jogos.fase',
            'jogos.resultado',
        ])->first();

        if (! $torneio) {
            return [];
        }

        $participantes = $this->servicoResultadosTorneio->participantesPorJogo($torneio);
        $apostas = $cupom->apostas
            ->where('tipo', 'placar_jogo_eliminatoria')
            ->keyBy('jogo_id');

        return $torneio->jogos
            ->filter(fn (Jogo $jogo) => $jogo->fase && $jogo->fase->tipo !== 'grupos')
            ->sortBy(fn (Jogo $jogo) => [$jogo->fase->ordem, $jogo->ordem_na_fase])
            ->map(function (Jogo $jogo) use ($participantes, $apostas) {
                $par = $participantes[$jogo->id] ?? ['mandante' => null, 'visitante' => null];
                $aposta = $apostas->get($jogo->id);

                return [
                    'id' => $jogo->id,
                    'fase' => ['slug' => $jogo->fase->slug, 'nome' => $jogo->fase->nome, 'ordem' => $jogo->fase->ordem],
                    'data_hora_inicio' => $jogo->data_hora_inicio,
                    'selecao_mandante' => $par['mandante'],
                    'selecao_visitante' => $par['visitante'],
                    'resultado' => $jogo->resultado,
                    'palpite' => $aposta?->conteudo,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{podio_palpite:array{campeao:?int,vice:?int,terceiro:?int},podio_real:array{campeao:?int,vice:?int,terceiro:?int}}
     */
    public function resumo(Cupom $cupom): array
    {
        $torneio = $cupom->torneio()->with('resultadoTorneio')->first();
        $podioAposta = $cupom->apostas->firstWhere('tipo', 'podio');
        $c = $podioAposta?->conteudo ?? [];

        return [
            'podio_palpite' => [
                'campeao' => (int) ($c['campeao_selecao_id'] ?? 0) ?: null,
                'vice' => (int) ($c['vice_selecao_id'] ?? 0) ?: null,
                'terceiro' => (int) ($c['terceiro_selecao_id'] ?? 0) ?: null,
            ],
            'podio_real' => [
                'campeao' => $torneio?->resultadoTorneio?->campeao_selecao_id,
                'vice' => $torneio?->resultadoTorneio?->vice_campeao_selecao_id,
                'terceiro' => $torneio?->resultadoTorneio?->terceiro_colocado_selecao_id,
            ],
        ];
    }
}
```

- [ ] **Step 2: Trocar o `CupomController::bracket` para usar `ServicoBracketReal`**

Em `backend/app/Http/Controllers/CupomController.php`:
- No construtor, trocar `ServicoBracketCupom` por `ServicoBracketReal`:

```php
    public function __construct(
        private readonly ServicoBracketReal $servicoBracketReal,
    ) {
    }
```

- No método `bracket`, garantir que carrega `apostas` e usar o novo serviço:

```php
    public function bracket(Request $request, Cupom $cupom): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        $cupom->loadMissing('apostas');

        return response()->json([
            'bracket' => $this->servicoBracketReal->gerar($cupom),
            'resumo' => $this->servicoBracketReal->resumo($cupom),
        ]);
    }
```

Atualizar o `use App\Services\ServicoBracketCupom;` → `use App\Services\ServicoBracketReal;`.

- [ ] **Step 3: Teste mínimo do endpoint**

Adicionar em `MataMataRealidadeTest`:

```php
public function test_endpoint_bracket_retorna_jogos_de_mata_mata_com_times_reais_ou_nulos(): void
{
    $this->seed();
    [$usuario, $cupom, $torneio] = $this->criarCupom('bracket@teste.local');

    Sanctum::actingAs($usuario);
    $resp = $this->getJson("/api/cupons/{$cupom->id}/bracket")->assertOk();

    $this->assertIsArray($resp->json('bracket'));
    $this->assertNotEmpty($resp->json('bracket'));
    $this->assertArrayHasKey('podio_real', $resp->json('resumo'));
    // Sem resultados de grupos lancados, os times do round_of_32 sao nulos.
    $primeiro = $resp->json('bracket.0');
    $this->assertArrayHasKey('selecao_mandante', $primeiro);
}
```

- [ ] **Step 4: Rodar**

Run: `cd backend && php artisan test --filter="test_endpoint_bracket_retorna_jogos_de_mata_mata_com_times_reais_ou_nulos"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/ServicoBracketReal.php backend/app/Http/Controllers/CupomController.php backend/tests/Feature/MataMataRealidadeTest.php
git commit -m "feat: endpoint de bracket usa visao real (ServicoBracketReal)"
```

---

## Task 6: Aposentar `ServicoBracketCupom`

**Files:**
- Remove: `backend/app/Services/ServicoBracketCupom.php`

- [ ] **Step 1: Garantir que não há mais referências**

Run: `cd backend && grep -rn "ServicoBracketCupom\|BracketJogoCupom" app/ tests/`
Expected: nenhuma referência em `app/` (Tasks 2, 4, 5 já removeram de ServicoApostas, ServicoPontuacao, CupomController). Em `tests/`, apenas os testes a remover na Task 7. Se aparecer alguma referência em `app/`, removê-la antes de prosseguir.

- [ ] **Step 2: Remover o arquivo**

```bash
git rm backend/app/Services/ServicoBracketCupom.php
```

- [ ] **Step 3: Rodar a suíte (vai quebrar nos testes da fantasia — esperado, corrigidos na Task 7)**

Run: `cd backend && php artisan test 2>&1 | tail -15`
Expected: falhas APENAS em `BracketCupomApiTest`, `LoteChainCompletaTest`, `LoteApostasMataMataTest` (referenciam a fantasia). Demais verdes.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor: remove ServicoBracketCupom (bracket de fantasia aposentado)"
```

---

## Task 7: Reescrever/remover testes da fantasia

**Files:**
- Remove: `backend/tests/Feature/LoteChainCompletaTest.php`, `backend/tests/Feature/LoteApostasMataMataTest.php`
- Modify: `backend/tests/Feature/BracketCupomApiTest.php`
- Modify: `backend/tests/Feature/FechamentoApostasTest.php` (se algum teste dependia do desbloqueio progressivo)

- [ ] **Step 1: Remover os testes de cadeia de fantasia**

```bash
git rm backend/tests/Feature/LoteChainCompletaTest.php backend/tests/Feature/LoteApostasMataMataTest.php
```

(A cadeia "um único lote resolve até a final" não existe na realidade: o usuário só palpita um jogo quando os times reais daquele jogo estão definidos. Esse comportamento já é coberto por `MataMataRealidadeTest`.)

- [ ] **Step 2: Reescrever `BracketCupomApiTest`**

Substituir o conteúdo de `backend/tests/Feature/BracketCupomApiTest.php` por testes da realidade:

```php
<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BracketCupomApiTest extends TestCase
{
    use RefreshDatabase;

    private function criarCupom(): array
    {
        $usuario = Usuario::query()->create([
            'nome' => 'Bracket', 'email' => 'bracket-real@teste.local',
            'telefone' => '71999999999', 'cpf_cnpj' => '12345678901',
            'password' => '12345678', 'perfil' => 'usuario',
        ]);
        Sanctum::actingAs($usuario);
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $pedido = $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])->assertCreated()->json('pedido');
        $cupom = $this->postJson("/api/pedidos-checkout/{$pedido['id']}/confirmar-sandbox", [])->assertOk()->json('cupom');

        return [$usuario, Cupom::query()->findOrFail($cupom['id']), $torneio];
    }

    public function test_bracket_lista_jogos_eliminatorios_com_palpite_e_podio(): void
    {
        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom();

        Sanctum::actingAs($usuario);
        $resp = $this->getJson("/api/cupons/{$cupom->id}/bracket")->assertOk();

        $resp->assertJsonStructure([
            'bracket' => [['id', 'fase' => ['slug', 'nome'], 'selecao_mandante', 'selecao_visitante', 'palpite']],
            'resumo' => ['podio_palpite', 'podio_real'],
        ]);
    }

    public function test_aposta_eliminatoria_so_salva_quando_participantes_reais_existem(): void
    {
        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom();

        // Sem resultados de grupos: round_of_32 sem participantes reais -> item ignorado.
        $jogoR32 = Jogo::query()->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('slug', 'round_of_32'))
            ->orderBy('ordem_na_fase')->firstOrFail();

        Sanctum::actingAs($usuario);
        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria', 'jogo_id' => $jogoR32->id,
                'placar_mandante' => 1, 'placar_visitante' => 0,
            ]],
        ])->assertOk();

        $this->assertDatabaseMissing('apostas', [
            'cupom_id' => $cupom->id, 'jogo_id' => $jogoR32->id,
        ]);
    }
}
```

- [ ] **Step 3: Ajustar `FechamentoApostasTest` se necessário**

Run: `cd backend && php artisan test --filter=FechamentoApostasTest 2>&1 | tail -20`
Se algum teste falhar por causa da remoção do desbloqueio progressivo (ex.: um teste que esperava "eliminatórias bloqueadas até preencher grupos"), atualizá-lo/removê-lo: esse comportamento foi intencionalmente removido (desacoplamento). Manter os testes de prazo por dia/grupos. Mostrar o teste afetado e ajustar a expectativa para o novo comportamento (eliminatória abre pela realidade).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "test: reescreve testes do mata-mata para a realidade; remove testes de fantasia"
```

---

## Task 8: Suíte completa verde + verificação

**Files:** (nenhum)

- [ ] **Step 1: Suíte inteira**

Run: `cd backend && php artisan test`
Expected: 0 falhas. Se algo quebrar fora do mata-mata, investigar (provável referência residual à fantasia ou a regra de pontuação ausente no seed).

- [ ] **Step 2: Verificação manual do fluxo**

Run: `cd backend && php artisan migrate:fresh --seed`
- `curl -s http://localhost:8888/api/boloes` (continua ok)
- Logar (sandbox) e checar `GET /api/cupons/{id}/bracket` retorna `bracket` (jogos de mata-mata) + `resumo.podio_real`.

- [ ] **Step 3: Commit (se houve ajustes)**

```bash
git add -A
git commit -m "test: suite verde apos mata-mata pela realidade"
```

---

## Self-review (cobertura do spec — Fase B)

- **Mata-mata pela realidade (participantes reais)** → Task 2 (ServicoApostas usa `ServicoResultadosTorneio`). ✓
- **Abertura por fase (sem exigir grupos)** → Task 2 (null quando sem participantes) + Task 3 (remove desbloqueio progressivo). ✓
- **Pontuação direta vs resultado real** → já existia em `pontuarJogoEliminatoria`; mantida. ✓
- **Bônus campeão/vice/3º explícito** → Task 1 (request) + Task 2 (`podio` no normalizarItem) + Task 3 (fechamento) + Task 4 (pontuação). ✓
- **Aba Chaveamento = bracket real** → Task 5 (`ServicoBracketReal` + endpoint). ✓ (frontend na Fase B-frontend)
- **Aposentar a fantasia** → Task 6 (remove `ServicoBracketCupom`). ✓
- **Confrontos confirmados pelo admin** → já existe: admin lança resultados → `ServicoResultadosTorneio` deriva os participantes reais da próxima fase automaticamente. Sem mudança de admin nesta fase. ✓

**Fora desta Fase B (backend):**
- Frontend: aba Chaveamento consumindo o bracket real; UI do palpite de pódio; remover a classificação de fantasia do `CupomView`/`InicioView`. → **Fase B-frontend**.
- Fase C: seeder do bolão de mata-mata + `torneio_id` NOT NULL em produção.
