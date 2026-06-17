# Admin Toggle de Compra de Cupom — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que o administrador abra/feche a compra de cupons em tempo real pelo painel (flag por torneio), sem deploy.

**Architecture:** A flag `compras_abertas` vira coluna em `torneios`. O gate de checkout passa a lê-la do torneio publicado (em vez de `config/env`). Um endpoint admin alterna a flag; o frontend reflete o estado (botão de compra no painel + toggle no admin).

**Tech Stack:** Laravel 13 (PHPUnit), Vue 3 + TS, MySQL.

---

## Arquivos afetados

- Criar: `backend/database/migrations/2026_06_17_000000_add_compras_abertas_to_torneios_table.php`
- Criar: `backend/app/Http/Requests/AtualizarComprasCupomRequest.php`
- Modificar: `backend/app/Models/Torneio.php` (fillable + cast)
- Modificar: `backend/app/Http/Controllers/PedidoCheckoutController.php` (gate lê do torneio)
- Modificar: `backend/app/Http/Controllers/PainelAdministradorController.php` (método + expor flag em `dados`)
- Modificar: `backend/routes/api.php` (rota admin)
- Remover: `backend/config/checkout.php`
- Modificar: `backend/tests/TestCase.php` (override `seed()` abre compras; remove config)
- Modificar: `backend/tests/Feature/CheckoutFluxoTest.php` (teste de compra encerrada usa flag)
- Modificar: `frontend/src/tipos.ts` (Torneio.compras_abertas)
- Modificar: `frontend/src/views/PainelView.vue` (botão de compra condicional)
- Modificar: `frontend/src/views/AdminPainelView.vue` (toggle)

---

## Task 1: Coluna `compras_abertas` no torneio

**Files:**
- Create: `backend/database/migrations/2026_06_17_000000_add_compras_abertas_to_torneios_table.php`
- Modify: `backend/app/Models/Torneio.php`

- [ ] **Step 1: Criar a migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->boolean('compras_abertas')->default(false)->after('valor_cupom');
        });
    }

    public function down(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->dropColumn('compras_abertas');
        });
    }
};
```

- [ ] **Step 2: Atualizar o model `Torneio`**

Em `backend/app/Models/Torneio.php`: adicionar `'compras_abertas'` ao `$fillable` e
`'compras_abertas' => 'boolean'` ao array de `casts()`.

- [ ] **Step 3: Rodar a migration**

Run: `cd backend && php artisan migrate`
Expected: migration `add_compras_abertas_to_torneios_table` aplicada sem erro.

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations backend/app/Models/Torneio.php
git commit -m "feat: coluna compras_abertas no torneio"
```

---

## Task 2: Gate de checkout lê a flag do torneio

**Files:**
- Modify: `backend/app/Http/Controllers/PedidoCheckoutController.php`
- Modify: `backend/tests/TestCase.php`
- Modify: `backend/tests/Feature/CheckoutFluxoTest.php`
- Remove: `backend/config/checkout.php`

- [ ] **Step 1: Atualizar o teste de compra encerrada (falha primeiro)**

Em `CheckoutFluxoTest::test_compra_encerrada_bloqueia_criacao_de_pedido`, trocar a linha
`config(['checkout.compras_abertas' => false]);` por:

```php
Torneio::query()->where('status', 'publicado')->update(['compras_abertas' => false]);
```

- [ ] **Step 2: Centralizar a abertura nos testes (TestCase)**

Em `backend/tests/TestCase.php`: remover a linha `'checkout.compras_abertas' => true,` do
`config([...])` e adicionar o override de `seed()` (importando `App\Models\Torneio` e
`Database\Seeders\DatabaseSeeder`):

```php
protected function seed($class = \Database\Seeders\DatabaseSeeder::class): void
{
    parent::seed($class);
    \App\Models\Torneio::query()->where('status', 'publicado')->update(['compras_abertas' => true]);
}
```

- [ ] **Step 3: Rodar o teste e ver falhar**

Run: `cd backend && php artisan test --filter=test_compra_encerrada_bloqueia_criacao_de_pedido`
Expected: FALHA — o gate ainda lê `config`, então a flag do torneio não bloqueia.

- [ ] **Step 4: Trocar o gate para ler do torneio**

Em `PedidoCheckoutController.php`: adicionar `use App\Models\Torneio;` e substituir o corpo de
`garantirComprasAbertas()`:

```php
private function garantirComprasAbertas(): void
{
    $aberto = (bool) Torneio::query()
        ->where('status', 'publicado')
        ->latest('id')
        ->value('compras_abertas');

    abort_unless($aberto, 403, 'A compra de cupons esta encerrada.');
}
```

- [ ] **Step 5: Remover o config obsoleto**

Run: `cd backend && rm config/checkout.php`
Conferir que não há mais referências: `grep -rn "checkout.compras_abertas" backend/app backend/config backend/tests`
Expected: nenhum resultado.

- [ ] **Step 6: Rodar a suíte de checkout**

Run: `cd backend && php artisan test --filter=CheckoutFluxoTest`
Expected: PASS em todos (a abertura vem do override de `seed()`; o teste de encerrada fecha a flag).

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Controllers/PedidoCheckoutController.php backend/tests config/checkout.php
git commit -m "feat: gate de compra le compras_abertas do torneio (remove config/env)"
```

---

## Task 3: Endpoint admin para alternar a flag

**Files:**
- Create: `backend/app/Http/Requests/AtualizarComprasCupomRequest.php`
- Modify: `backend/app/Http/Controllers/PainelAdministradorController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/tests/Feature/CheckoutFluxoTest.php` (novos testes)

- [ ] **Step 1: Escrever os testes (falham primeiro)**

Adicionar em `CheckoutFluxoTest` (importar `Laravel\Sanctum\Sanctum`, `App\Models\Torneio`,
`App\Models\Usuario`):

```php
public function test_admin_abre_e_fecha_a_compra_de_cupons(): void
{
    $this->seed();
    $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();

    $admin = Usuario::factory()->create(['perfil' => 'administrador', 'email' => 'adm-toggle@example.com']);
    Sanctum::actingAs($admin);

    $this->putJson("/api/admin/torneios/{$torneio->id}/compras", ['compras_abertas' => false])
        ->assertOk()
        ->assertJsonPath('torneio.compras_abertas', false);

    $this->assertFalse((bool) $torneio->fresh()->compras_abertas);

    $this->putJson("/api/admin/torneios/{$torneio->id}/compras", ['compras_abertas' => true])
        ->assertOk()
        ->assertJsonPath('torneio.compras_abertas', true);
}

public function test_usuario_comum_nao_altera_compra_de_cupons(): void
{
    $this->seed();
    $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();

    $usuario = Usuario::factory()->create(['perfil' => 'usuario', 'email' => 'comum-toggle@example.com']);
    Sanctum::actingAs($usuario);

    $this->putJson("/api/admin/torneios/{$torneio->id}/compras", ['compras_abertas' => true])
        ->assertForbidden();
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `cd backend && php artisan test --filter="test_admin_abre_e_fecha_a_compra_de_cupons|test_usuario_comum_nao_altera"`
Expected: FALHA (rota 404 / método inexistente).

- [ ] **Step 3: Criar o FormRequest**

`backend/app/Http/Requests/AtualizarComprasCupomRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarComprasCupomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rota ja protegida por can:acessar-area-admin
    }

    public function rules(): array
    {
        return [
            'compras_abertas' => ['required', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Adicionar o método no controller**

Em `PainelAdministradorController.php`: adicionar `use App\Http\Requests\AtualizarComprasCupomRequest;`
e `use App\Models\Torneio;` (se ausente), e o método:

```php
public function atualizarComprasAbertas(AtualizarComprasCupomRequest $request, Torneio $torneio): JsonResponse
{
    $torneio->forceFill(['compras_abertas' => $request->boolean('compras_abertas')])->save();

    return response()->json(['torneio' => $torneio->fresh()]);
}
```

- [ ] **Step 5: Registrar a rota**

Em `backend/routes/api.php`, dentro do grupo `can:acessar-area-admin`:

```php
Route::put('/admin/torneios/{torneio}/compras', [PainelAdministradorController::class, 'atualizarComprasAbertas']);
```

- [ ] **Step 6: Rodar os testes**

Run: `cd backend && php artisan test --filter=CheckoutFluxoTest`
Expected: PASS (incluindo os dois novos).

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Requests/AtualizarComprasCupomRequest.php backend/app/Http/Controllers/PainelAdministradorController.php backend/routes/api.php backend/tests/Feature/CheckoutFluxoTest.php
git commit -m "feat: endpoint admin para abrir/fechar compra de cupons"
```

---

## Task 4: Expor a flag (API → frontend type)

**Files:**
- Modify: `backend/app/Http/Controllers/PainelAdministradorController.php` (método `dados`)
- Modify: `frontend/src/tipos.ts`

- [ ] **Step 1: Garantir a flag em `/torneio` e `/admin/dados`**

`/torneio` (`TorneioController::carregarTorneio`) já faz `firstOrFail()` sem `select`, então
`compras_abertas` vem automaticamente. Verificar o método `dados` do
`PainelAdministradorController`: se ele retorna o torneio via model (sem `select` restritivo), a
flag já vai junto. Se usar `select`/`only` explícito, incluir `compras_abertas`. Ajustar somente
se necessário.

Run (após backend no ar): `curl -s http://127.0.0.1:8888/api/torneio | grep -o 'compras_abertas'`
Expected: `compras_abertas` presente.

- [ ] **Step 2: Adicionar ao tipo `Torneio`**

Em `frontend/src/tipos.ts`, no tipo `Torneio`, adicionar:

```ts
compras_abertas: boolean
```

- [ ] **Step 3: Commit**

```bash
git add backend/app/Http/Controllers/PainelAdministradorController.php frontend/src/tipos.ts
git commit -m "feat: expor compras_abertas na API e no tipo Torneio"
```

---

## Task 5: Botão de compra condicional no PainelView

**Files:**
- Modify: `frontend/src/views/PainelView.vue`

- [ ] **Step 1: Ler o estado da flag**

No `<script setup>` do `PainelView.vue`, importar/usar o store de torneio
(`import { usarTorneioStore } from '../stores/torneio'`) e expor um computed:

```ts
const torneioStore = usarTorneioStore()
const comprasAbertas = computed(() => torneioStore.torneio?.compras_abertas === true)
```

Garantir que o torneio seja carregado (chamar a ação de carregar do store no `onMounted` se ele
ainda não for carregado globalmente).

- [ ] **Step 2: Tornar o botão do header condicional**

Substituir o `<span ... >Compra de cupons encerrada</span>` (linhas ~12-18) por:

```html
<RouterLink
  v-if="comprasAbertas"
  to="/checkout"
  class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-center text-sm font-semibold text-bg transition hover:bg-primary-hover"
>
  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
  Comprar cupom
</RouterLink>
<span
  v-else
  class="inline-flex cursor-not-allowed items-center gap-2 rounded-xl border border-border bg-bg-input px-6 py-2.5 text-center text-sm font-semibold text-text-muted"
  title="A compra de cupons esta encerrada"
>
  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
  Compra de cupons encerrada
</span>
```

- [ ] **Step 3: Empty-state condicional**

No empty-state (linha ~62-65), quando `comprasAbertas` mostrar um `RouterLink` "Comprar
cupom" para `/checkout`; senão manter o texto "A compra de cupons esta encerrada ate o fim do
campeonato.".

- [ ] **Step 4: Verificar visualmente**

Abrir/fechar a flag no banco (`Torneio::...->update(['compras_abertas'=>true])`) e capturar o
painel via `frontend/scripts/` (Playwright) logado como demo, confirmando botão x aviso.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/views/PainelView.vue
git commit -m "feat: botao Comprar cupom no painel quando a compra esta aberta"
```

---

## Task 6: Toggle no painel admin

**Files:**
- Modify: `frontend/src/views/AdminPainelView.vue`

- [ ] **Step 1: Localizar a seção e a fonte do torneio**

Ler `AdminPainelView.vue` para achar onde os dados (`/admin/dados`) são carregados e uma seção
de configurações onde encaixar o toggle. Identificar o id do torneio disponível nos dados.

- [ ] **Step 2: Adicionar o controle e a chamada**

Adicionar um toggle (botão/switch) "Compra de cupons" que mostra o estado atual
(`torneio.compras_abertas`) e, ao clicar, chama:

```ts
await requisicaoApi(`/admin/torneios/${torneioId}/compras`, {
  metodo: 'PUT',
  corpo: { compras_abertas: !comprasAbertas.value },
})
```

Em seguida recarregar os dados do admin e o store de torneio, e emitir um toast de sucesso
(usar o composable de toast já existente no projeto). Tratar erro com toast de falha.

- [ ] **Step 3: Verificar**

Logado como admin, alternar o toggle e confirmar: estado persiste após reload; PainelView reflete
(botão aparece/some).

- [ ] **Step 4: Commit**

```bash
git add frontend/src/views/AdminPainelView.vue
git commit -m "feat: toggle de compra de cupons no painel admin"
```

---

## Task 7: Verificação final

- [ ] **Step 1: Backend**

Run: `cd backend && php artisan test`
Expected: suíte verde.

- [ ] **Step 2: Frontend build**

Run: `cd frontend && npm run build`
Expected: build sem erros.

- [ ] **Step 3: Fluxo ponta-a-ponta (manual via Playwright/preview)**

Admin abre a compra → participante vê "Comprar cupom" e consegue criar pedido (201). Admin fecha
→ botão vira aviso e `store` retorna 403.

- [ ] **Step 4: Commit final (se houver ajustes)**

```bash
git add -A && git commit -m "chore: verificacao final do toggle de compra de cupom"
```

---

## Self-Review

- **Cobertura do spec:** coluna (T1) ✓; gate lê torneio + remove config (T2) ✓; endpoint admin (T3) ✓; exposição na API + tipo (T4) ✓; botão no painel (T5) ✓; toggle admin (T6) ✓; testes TDD (T2,T3) ✓; verificação (T7) ✓.
- **Default fechado:** migration `default(false)` ✓; testes abrem via override de `seed()`.
- **Resolução do torneio:** gate usa `where('status','publicado')->latest('id')`, igual ao `ServicoCheckout` ✓.
- **Placeholders:** nenhum — código real nos passos de backend; frontend com pontos de inserção e código de chamada concretos (selectors finais lidos na execução, por serem componentes existentes).
