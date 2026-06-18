# Admin define o prazo de fechamento do pódio — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que o admin defina manualmente quando o palpite de pódio fecha, mantendo o cálculo automático atual como padrão quando nenhum prazo manual estiver definido.

**Architecture:** Coluna nullable `data_fechamento_podio` em `torneios` funciona como override opcional. O `ServicoFechamentoApostas` usa esse valor quando presente; senão, mantém a lógica automática (1h antes do 1º mata-mata; fallback 1h antes do início). Um endpoint admin grava/limpa o campo; a UI admin usa um `datetime-local` com conversão local↔UTC. No cupom, `prazoPodioMs` passa a considerar o override e propaga para o banner/contador já existentes.

**Tech Stack:** Laravel 13 (PHP 8.3), MySQL/SQLite (testes), Vue 3 + TypeScript, Pinia, Tailwind. Backend na porta 8888, frontend na 5173.

---

## File Structure

- `backend/database/migrations/2026_06_18_110000_add_data_fechamento_podio_to_torneios_table.php` (criar) — adiciona a coluna.
- `backend/app/Models/Torneio.php` (modificar) — `$fillable` + `casts()`.
- `backend/app/Services/ServicoFechamentoApostas.php` (modificar) — ramo `podio` respeita o override.
- `backend/app/Http/Requests/AtualizarFechamentoPodioRequest.php` (criar) — validação do payload.
- `backend/app/Http/Controllers/PainelAdministradorController.php` (modificar) — método `atualizarFechamentoPodio`.
- `backend/routes/api.php` (modificar) — rota PUT no grupo admin.
- `backend/tests/Feature/FechamentoApostasTest.php` (modificar) — testes da regra (override e automático).
- `backend/tests/Feature/AdminFechamentoPodioTest.php` (criar) — testes do endpoint admin.
- `frontend/src/tipos.ts` (modificar) — campo no tipo `Torneio`.
- `frontend/src/views/CupomView.vue` (modificar) — `prazoPodioMs` considera o override.
- `frontend/src/views/AdminPainelView.vue` (modificar) — UI + helpers de timezone + handler.

---

## Task 1: Migration e Model (coluna `data_fechamento_podio`)

**Files:**
- Create: `backend/database/migrations/2026_06_18_110000_add_data_fechamento_podio_to_torneios_table.php`
- Modify: `backend/app/Models/Torneio.php`

- [ ] **Step 1: Criar a migration**

Create `backend/database/migrations/2026_06_18_110000_add_data_fechamento_podio_to_torneios_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            // Override opcional do fechamento do palpite de podio (campeao/vice/3o).
            // NULL = automatico (1h antes do 1o jogo do mata-mata).
            $table->dateTime('data_fechamento_podio')->nullable()->after('data_fim');
        });
    }

    public function down(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->dropColumn('data_fechamento_podio');
        });
    }
};
```

- [ ] **Step 2: Adicionar ao `$fillable` e `casts()` do model**

In `backend/app/Models/Torneio.php`, add `'data_fechamento_podio'` to `$fillable` (after `'data_fim'`) and to `casts()`:

```php
    protected $fillable = [
        'nome',
        'edicao',
        'status',
        'data_inicio',
        'data_fim',
        'data_fechamento_podio',
        'valor_cupom',
        'compras_abertas',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'data_fechamento_podio' => 'datetime',
            'valor_cupom' => 'decimal:2',
            'compras_abertas' => 'boolean',
        ];
    }
```

- [ ] **Step 3: Rodar a migration e confirmar**

Run: `cd backend && php artisan migrate`
Expected: migration `..._add_data_fechamento_podio_to_torneios_table` roda com sucesso (`DONE`).

- [ ] **Step 4: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026"
git add backend/database/migrations/2026_06_18_110000_add_data_fechamento_podio_to_torneios_table.php backend/app/Models/Torneio.php
git commit -m "feat(back): coluna data_fechamento_podio em torneios (override do fechamento)"
```

---

## Task 2: Regra de fechamento respeita o override (TDD)

**Files:**
- Test: `backend/tests/Feature/FechamentoApostasTest.php`
- Modify: `backend/app/Services/ServicoFechamentoApostas.php:53-65`

- [ ] **Step 1: Escrever os testes que falham**

In `backend/tests/Feature/FechamentoApostasTest.php`, add two methods (after `test_backend_bloqueia_artilheiro_apos_inicio_do_torneio`):

```php
    public function test_podio_respeita_override_de_fechamento_definido_pelo_admin(): void
    {
        $this->seed();
        $torneio = Torneio::query()->firstOrFail();

        // Override explicito do admin: o podio fecha exatamente nesse horario,
        // independente do calendario do mata-mata.
        Torneio::query()->whereKey($torneio->id)->update([
            'data_fechamento_podio' => '2026-07-01 12:00:00',
        ]);

        $servico = app(ServicoFechamentoApostas::class);
        $dados = ['tipo' => 'podio', 'torneio_id' => $torneio->id];

        Carbon::setTestNow('2026-07-01 11:00:00');
        $this->assertFalse(
            $servico->prazoEncerrado($dados),
            '1h antes do override, o podio deve estar aberto.',
        );

        Carbon::setTestNow('2026-07-01 12:30:00');
        $this->assertTrue(
            $servico->prazoEncerrado($dados),
            'Apos o override, o podio deve estar fechado.',
        );

        Carbon::setTestNow();
    }

    public function test_podio_usa_calculo_automatico_quando_sem_override(): void
    {
        $this->seed();
        $torneio = Torneio::query()->firstOrFail();
        Torneio::query()->whereKey($torneio->id)->update(['data_fechamento_podio' => null]);

        // Define os jogos do mata-mata; o mais cedo fica as 13:00 -> podio fecha as 12:00.
        Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('tipo', '!=', 'grupos'))
            ->update(['data_hora_inicio' => '2026-08-10 16:00:00']);

        $primeiroMataMata = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('tipo', '!=', 'grupos'))
            ->orderBy('id')
            ->firstOrFail();
        Jogo::query()->whereKey($primeiroMataMata->id)->update(['data_hora_inicio' => '2026-08-10 13:00:00']);

        $servico = app(ServicoFechamentoApostas::class);
        $dados = ['tipo' => 'podio', 'torneio_id' => $torneio->id];

        Carbon::setTestNow('2026-08-10 11:00:00');
        $this->assertFalse(
            $servico->prazoEncerrado($dados),
            'Sem override, o podio segue o automatico (1h antes do 1o mata-mata).',
        );

        Carbon::setTestNow('2026-08-10 12:30:00');
        $this->assertTrue(
            $servico->prazoEncerrado($dados),
            'Sem override, fecha 1h antes do 1o jogo do mata-mata (12:00).',
        );

        Carbon::setTestNow();
    }
```

- [ ] **Step 2: Rodar os testes e confirmar que o override falha**

Run: `cd backend && php artisan test --filter=FechamentoApostasTest`
Expected: `test_podio_respeita_override_de_fechamento_definido_pelo_admin` FALHA (o serviço ainda ignora o override e usa o cálculo automático, então o pódio não fecha às 12:30). `test_podio_usa_calculo_automatico_quando_sem_override` deve PASSAR (comportamento atual).

- [ ] **Step 3: Implementar o override no serviço**

In `backend/app/Services/ServicoFechamentoApostas.php`, replace the `if ($tipo === 'podio')` block (currently lines ~53-65):

```php
        if ($tipo === 'podio') {
            // Override opcional definido pelo admin: se presente, fecha exatamente nesse horario.
            if ($torneio->data_fechamento_podio) {
                return $torneio->data_fechamento_podio;
            }

            // Caso contrario, o palpite de campeao/vice/3o fecha no FIM da fase de grupos:
            // 1h antes do primeiro jogo do mata-mata. Para bolao so de mata-mata, e antes do 1o jogo.
            $primeiroMataMata = Jogo::query()
                ->where('torneio_id', $torneio->id)
                ->whereHas('fase', fn ($query) => $query->where('tipo', '!=', 'grupos'))
                ->whereNotNull('data_hora_inicio')
                ->min('data_hora_inicio');

            return $primeiroMataMata
                ? Carbon::parse($primeiroMataMata)->subHour()
                : $torneio->data_inicio?->copy()->subHour();
        }
```

- [ ] **Step 4: Rodar os testes e confirmar que passam**

Run: `cd backend && php artisan test --filter=FechamentoApostasTest`
Expected: PASS (todos, incluindo os dois novos).

- [ ] **Step 5: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026"
git add backend/app/Services/ServicoFechamentoApostas.php backend/tests/Feature/FechamentoApostasTest.php
git commit -m "feat(back): fechamento do podio respeita override do admin (fallback automatico)"
```

---

## Task 3: Endpoint admin para gravar/limpar o prazo (TDD)

**Files:**
- Create: `backend/app/Http/Requests/AtualizarFechamentoPodioRequest.php`
- Create: `backend/tests/Feature/AdminFechamentoPodioTest.php`
- Modify: `backend/app/Http/Controllers/PainelAdministradorController.php`
- Modify: `backend/routes/api.php`

- [ ] **Step 1: Escrever o teste do endpoint que falha**

Create `backend/tests/Feature/AdminFechamentoPodioTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFechamentoPodioTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_define_e_limpa_o_prazo_de_fechamento_do_podio(): void
    {
        $this->seed();
        $admin = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        $torneio = Torneio::query()->firstOrFail();

        Sanctum::actingAs($admin);

        // Define o prazo.
        $this->putJson("/api/admin/torneios/{$torneio->id}/fechamento-podio", [
            'data_fechamento_podio' => '2026-07-01T12:00:00Z',
        ])->assertOk();

        $this->assertNotNull($torneio->fresh()->data_fechamento_podio);

        // Limpa o prazo (volta ao automatico).
        $this->putJson("/api/admin/torneios/{$torneio->id}/fechamento-podio", [
            'data_fechamento_podio' => null,
        ])->assertOk();

        $this->assertNull($torneio->fresh()->data_fechamento_podio);
    }

    public function test_usuario_comum_nao_pode_definir_o_fechamento_do_podio(): void
    {
        $this->seed();
        $torneio = Torneio::query()->firstOrFail();

        $usuario = Usuario::query()->create([
            'nome' => 'Comum',
            'email' => 'comum-podio@teste.local',
            'telefone' => '71999999999',
            'cpf_cnpj' => '12345678901',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $this->putJson("/api/admin/torneios/{$torneio->id}/fechamento-podio", [
            'data_fechamento_podio' => '2026-07-01T12:00:00Z',
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `cd backend && php artisan test --filter=AdminFechamentoPodioTest`
Expected: FALHA (rota `admin/torneios/{torneio}/fechamento-podio` ainda não existe → 404).

- [ ] **Step 3: Criar o Form Request**

Create `backend/app/Http/Requests/AtualizarFechamentoPodioRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarFechamentoPodioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rota ja protegida por can:acessar-area-admin
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'data_fechamento_podio' => ['nullable', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Adicionar o método no controller**

In `backend/app/Http/Controllers/PainelAdministradorController.php`, add the import near the other request imports at the top of the file:

```php
use App\Http\Requests\AtualizarFechamentoPodioRequest;
```

Then add the method right after `atualizarComprasAbertas` (after its closing `}`, ~line 107):

```php
    public function atualizarFechamentoPodio(AtualizarFechamentoPodioRequest $request, Torneio $torneio): JsonResponse
    {
        $torneio->forceFill([
            'data_fechamento_podio' => $request->date('data_fechamento_podio'),
        ])->save();

        return response()->json(['torneio' => $torneio->fresh()]);
    }
```

- [ ] **Step 5: Registrar a rota**

In `backend/routes/api.php`, add inside the `can:acessar-area-admin` group, right after the `compras` route (line ~64):

```php
        Route::put('/admin/torneios/{torneio}/fechamento-podio', [PainelAdministradorController::class, 'atualizarFechamentoPodio']);
```

- [ ] **Step 6: Rodar o teste e confirmar que passa**

Run: `cd backend && php artisan test --filter=AdminFechamentoPodioTest`
Expected: PASS (ambos os testes).

- [ ] **Step 7: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026"
git add backend/app/Http/Requests/AtualizarFechamentoPodioRequest.php backend/app/Http/Controllers/PainelAdministradorController.php backend/routes/api.php backend/tests/Feature/AdminFechamentoPodioTest.php
git commit -m "feat(back): endpoint admin para definir/limpar o fechamento do podio"
```

---

## Task 4: Tipo `Torneio` no frontend

**Files:**
- Modify: `frontend/src/tipos.ts:174-188`

- [ ] **Step 1: Adicionar o campo ao tipo `Torneio`**

In `frontend/src/tipos.ts`, add `data_fechamento_podio` to the `Torneio` type, right after `data_fim`:

```ts
export type Torneio = {
  id: number
  nome: string
  edicao: string
  status: string
  data_inicio: string | null
  data_fim: string | null
  data_fechamento_podio: string | null
  valor_cupom: number
  compras_abertas: boolean
  grupos: Grupo[]
  fases: Fase[]
  jogos: Jogo[]
  regras_pontuacao: RegraPontuacao[]
  resultado_torneio?: ResultadoTorneio | null
}
```

- [ ] **Step 2: Type-check**

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: 0 erros.

- [ ] **Step 3: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026"
git add frontend/src/tipos.ts
git commit -m "feat(front): tipo Torneio inclui data_fechamento_podio"
```

---

## Task 5: Cupom — `prazoPodioMs` considera o override

**Files:**
- Modify: `frontend/src/views/CupomView.vue` (computed `prazoPodioMs`)

- [ ] **Step 1: Adicionar o override no computed**

In `frontend/src/views/CupomView.vue`, update `prazoPodioMs` to check the override first. Replace the current computed body:

```ts
const prazoPodioMs = computed<number | null>(() => {
  // Override opcional definido pelo admin tem prioridade.
  if (torneio.value?.data_fechamento_podio) {
    return new Date(torneio.value.data_fechamento_podio).getTime()
  }
  const inicios = (torneio.value?.jogos ?? [])
    .filter((jogo) => jogo.fase.tipo !== 'grupos' && jogo.data_hora_inicio)
    .map((jogo) => new Date(jogo.data_hora_inicio).getTime())
  if (inicios.length) return Math.min(...inicios) - 3600000
  const inicio = torneio.value?.data_inicio
  return inicio ? new Date(inicio).getTime() - 3600000 : null
})
```

- [ ] **Step 2: Type-check**

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: 0 erros.

- [ ] **Step 3: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026"
git add frontend/src/views/CupomView.vue
git commit -m "feat(front): banner/contador do podio respeitam o override do admin"
```

---

## Task 6: Admin — UI para definir o prazo (datetime-local + timezone)

**Files:**
- Modify: `frontend/src/views/AdminPainelView.vue` (template do card de config + script)

- [ ] **Step 1: Adicionar os refs**

In `frontend/src/views/AdminPainelView.vue`, after `const salvandoCompras = ref(false)` (line ~360), add:

```ts
const salvandoFechamentoPodio = ref(false)
const fechamentoPodioInput = ref('')
```

- [ ] **Step 2: Adicionar os helpers de timezone**

In the same `<script setup>`, add these two helpers (e.g., right before `async function alternarCompras`):

```ts
// App roda em UTC; o input datetime-local opera em horario LOCAL sem timezone.
// Converte o ISO (UTC) vindo da API para o formato do input (local).
function isoParaInputLocal(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

// Converte o valor local do input para ISO UTC (ou null se vazio) para enviar ao backend.
function inputLocalParaIso(local: string): string | null {
  if (!local) return null
  const d = new Date(local) // interpretado como horario local do navegador
  return Number.isNaN(d.getTime()) ? null : d.toISOString()
}
```

- [ ] **Step 3: Sincronizar o input ao carregar o torneio**

In `frontend/src/views/AdminPainelView.vue`, find `function preencherFormulario()` (called at line ~602) and add, inside it, a line that seeds the input from the loaded tournament:

```ts
  fechamentoPodioInput.value = isoParaInputLocal(torneio.value?.data_fechamento_podio ?? null)
```

(If `preencherFormulario` early-returns when `torneio.value` is null, place this line after that guard, alongside the other field assignments.)

- [ ] **Step 4: Adicionar os handlers de salvar/limpar**

After `alternarCompras` (line ~638), add:

```ts
async function salvarFechamentoPodio() {
  if (!torneio.value || salvandoFechamentoPodio.value) return
  salvandoFechamentoPodio.value = true
  mensagem.value = ''
  erro.value = ''
  try {
    const resposta = await requisicaoApi<{ torneio: Torneio }>(
      `/admin/torneios/${torneio.value.id}/fechamento-podio`,
      { metodo: 'PUT', corpo: { data_fechamento_podio: inputLocalParaIso(fechamentoPodioInput.value) } },
    )
    torneio.value.data_fechamento_podio = resposta.torneio.data_fechamento_podio
    fechamentoPodioInput.value = isoParaInputLocal(torneio.value.data_fechamento_podio)
    mensagem.value = torneio.value.data_fechamento_podio
      ? 'Prazo de fechamento do pódio atualizado.'
      : 'Fechamento do pódio voltou ao automático.'
  } catch (e) {
    erro.value = e instanceof Error ? e.message : 'Falha ao atualizar o fechamento do pódio.'
  } finally {
    salvandoFechamentoPodio.value = false
  }
}

function limparFechamentoPodio() {
  fechamentoPodioInput.value = ''
  void salvarFechamentoPodio()
}
```

- [ ] **Step 5: Adicionar o bloco no template**

In `frontend/src/views/AdminPainelView.vue`, in the tournament config `<section>`, right after the "Compra de cupons" card (the `</div>` that closes it, line ~48), add:

```html
      <div class="mt-4 rounded-xl border border-border bg-bg-input px-4 py-3">
        <p class="text-sm font-semibold">Fechamento do pódio</p>
        <p class="text-xs text-text-muted">
          Quando o palpite de campeão, vice e 3º fecha. Deixe vazio para usar o automático
          (1h antes do 1º jogo do mata-mata).
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <input
            v-model="fechamentoPodioInput"
            type="datetime-local"
            class="rounded-lg border border-border bg-bg-card px-3 py-2 text-sm text-text [color-scheme:dark]"
          />
          <button
            type="button"
            :disabled="salvandoFechamentoPodio"
            class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-hover disabled:opacity-50"
            @click="salvarFechamentoPodio"
          >
            Salvar
          </button>
          <button
            type="button"
            :disabled="salvandoFechamentoPodio || !torneio.data_fechamento_podio"
            class="rounded-lg border border-border px-4 py-2 text-sm text-text-secondary transition hover:text-text disabled:opacity-50"
            @click="limparFechamentoPodio"
          >
            Limpar
          </button>
        </div>
      </div>
```

- [ ] **Step 6: Type-check**

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: 0 erros.

- [ ] **Step 7: Commit**

```bash
cd "C:/Projetos/Bolão Copa 2026"
git add frontend/src/views/AdminPainelView.vue
git commit -m "feat(front): admin define/limpa o prazo de fechamento do podio"
```

---

## Task 7: Verificação ponta-a-ponta no preview

**Files:** nenhum (verificação).

- [ ] **Step 1: Backend completo + type-check final**

Run: `cd backend && php artisan test`
Expected: PASS (suíte inteira verde).

Run: `cd frontend && npx vue-tsc --noEmit`
Expected: 0 erros.

- [ ] **Step 2: Verificar no preview (admin)**

Garanta backend (`php artisan serve --port=8888`) e frontend (`npm run dev`) rodando. No preview, logado como admin (`admin@interworldcup.local` / `12345678`):
- Abrir `/admin`, localizar o card "Fechamento do pódio".
- Definir uma data/hora, clicar Salvar → mensagem de sucesso; confirmar via `preview_network` que `PUT /api/admin/torneios/{id}/fechamento-podio` retorna 200.
- Recarregar e confirmar que o input volta preenchido com o mesmo horário (round-trip de timezone correto).
- Clicar Limpar → confirmar que o campo volta vazio e a mensagem indica "automático".

Use `preview_eval`/`preview_snapshot`/`preview_network` (NÃO `preview_screenshot` — trava neste projeto).

- [ ] **Step 3: Verificar no preview (cupom)**

Logado como `demo@interworldcup.local` / `demo12345`, em `/cupons/1`:
- Com um override definido pelo admin no futuro próximo, confirmar que o contador "Fecha em ..." do banner reflete o override (e não o cálculo automático).
- Com override no passado, confirmar que o banner some e o pódio aparece como "Fechado".
- Restaurar o estado do demo ao final (limpar o override no admin).

- [ ] **Step 4: Merge para main**

```bash
cd "C:/Projetos/Bolão Copa 2026"
git checkout main
git merge --no-ff feat/admin-fechamento-podio -m "Merge: admin define o prazo de fechamento do podio"
```

(Sem `git push` — só quando o usuário pedir.)

---

## Notas de execução

- **TDD:** Tasks 2 e 3 escrevem o teste antes da implementação; rodar e ver falhar é um passo explícito.
- **Branch:** criar uma branch de feature antes da Task 1 (ex.: `feat/admin-fechamento-podio`) e fazer os commits nela; merge na main na Task 7.
- **Timezone:** o app é UTC e o `datetime-local` é local — os helpers `isoParaInputLocal`/`inputLocalParaIso` são a única fonte de conversão; não enviar o valor cru do input ao backend.
- **`$request->date()`:** retorna `Carbon|null`; payload ausente ou `null` limpa o campo (volta ao automático).
