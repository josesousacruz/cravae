# Fase A — Multi-bolão (backend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir múltiplos bolões (torneios) coexistindo, com cupom/pedido vinculados a um bolão específico e o fim das queries hardcoded `latest('id')` no backend.

**Architecture:** "Bolão = Torneio" (tabela `torneios` interna; "Bolão" só na API/UI). Adiciona `torneio_id` em `pedidos_checkout` e `cupons`; checkout e compra passam a operar sobre um torneio explícito; nova rota pública `GET /api/boloes` lista bolões ativos/encerrados; `GET /api/torneios/{torneio}` carrega um bolão específico; admin (`dados`/`criarRegraPontuacao`) aceita `torneio_id`.

**Tech Stack:** Laravel 13, PHP 8.3, MySQL, Sanctum, PHPUnit (Feature tests com `RefreshDatabase` + `$this->seed()`).

**Escopo:** Apenas backend. Frontend (lista de bolões, seletor admin, wiring de checkout) e as Fases B (mata-mata pela realidade) e C (seeder + backfill de produção) têm planos próprios.

**Convenções do projeto:** domínio em português; campos estruturais do Laravel em inglês; schema só via migrations; rodar do diretório `backend/`.

---

## Estrutura de arquivos

**Criar:**
- `backend/database/migrations/2026_06_18_000000_add_torneio_id_to_pedidos_checkout_e_cupons.php` — FK `torneio_id` nas duas tabelas + backfill.
- `backend/app/Http/Controllers/BolaoController.php` — lista pública de bolões.
- `backend/tests/Feature/MultiBolaoTest.php` — testes do isolamento entre bolões e da nova rota.

**Modificar:**
- `backend/app/Models/Cupom.php` — `torneio_id` no fillable + relação `torneio()`.
- `backend/app/Models/PedidoCheckout.php` — `torneio_id` no fillable + relação `torneio()`.
- `backend/app/Services/ServicoCheckout.php` — `criarPedido(Usuario, Torneio, ?Cupom)`; cupom herda `torneio_id`.
- `backend/app/Http/Requests/CriarPedidoCheckoutRequest.php` — `torneio_id` obrigatório.
- `backend/app/Http/Controllers/PedidoCheckoutController.php` — resolve torneio; valida `compras_abertas` do torneio escolhido.
- `backend/app/Http/Controllers/TorneioController.php` — `show(Torneio)`; `publico()` reaproveita carregamento.
- `backend/app/Http/Controllers/BolaoController.php` — (criado acima).
- `backend/app/Http/Controllers/PainelAdministradorController.php` — `dados()` e `criarRegraPontuacao()` aceitam `torneio_id`.
- `backend/routes/api.php` — rotas `GET /boloes` e `GET /torneios/{torneio}`.
- `backend/tests/Feature/CheckoutFluxoTest.php` — passar `torneio_id` nos posts de checkout.

---

## Task 1: Migration — `torneio_id` em pedidos_checkout e cupons

**Files:**
- Create: `backend/database/migrations/2026_06_18_000000_add_torneio_id_to_pedidos_checkout_e_cupons.php`
- Test: `backend/tests/Feature/MultiBolaoTest.php`

- [ ] **Step 1: Escrever o teste que falha**

Criar `backend/tests/Feature/MultiBolaoTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MultiBolaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cupons_e_pedidos_tem_coluna_torneio_id(): void
    {
        $this->assertTrue(Schema::hasColumn('cupons', 'torneio_id'));
        $this->assertTrue(Schema::hasColumn('pedidos_checkout', 'torneio_id'));
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `cd backend && php artisan test --filter=test_cupons_e_pedidos_tem_coluna_torneio_id`
Expected: FAIL — coluna `torneio_id` não existe.

- [ ] **Step 3: Criar a migration**

`backend/database/migrations/2026_06_18_000000_add_torneio_id_to_pedidos_checkout_e_cupons.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_checkout', function (Blueprint $table) {
            $table->foreignId('torneio_id')->nullable()->after('usuario_id')
                ->constrained('torneios')->cascadeOnDelete();
        });

        Schema::table('cupons', function (Blueprint $table) {
            $table->foreignId('torneio_id')->nullable()->after('usuario_id')
                ->constrained('torneios')->cascadeOnDelete();
        });

        // Backfill: registros existentes apontam para o ultimo torneio publicado (a Copa atual).
        $torneioId = DB::table('torneios')->where('status', 'publicado')->orderByDesc('id')->value('id');

        if ($torneioId !== null) {
            DB::table('pedidos_checkout')->whereNull('torneio_id')->update(['torneio_id' => $torneioId]);
            DB::table('cupons')->whereNull('torneio_id')->update(['torneio_id' => $torneioId]);
        }
    }

    public function down(): void
    {
        Schema::table('cupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('torneio_id');
        });
        Schema::table('pedidos_checkout', function (Blueprint $table) {
            $table->dropConstrainedForeignId('torneio_id');
        });
    }
};
```

> Nota: a coluna fica **nullable** nesta entrega (sempre preenchida pelo código daqui pra frente). Tornar `NOT NULL` é passo do plano de produção (Fase C), após validar o backfill.

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `cd backend && php artisan test --filter=test_cupons_e_pedidos_tem_coluna_torneio_id`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_06_18_000000_add_torneio_id_to_pedidos_checkout_e_cupons.php backend/tests/Feature/MultiBolaoTest.php
git commit -m "feat: adiciona torneio_id a cupons e pedidos_checkout com backfill"
```

---

## Task 2: Models — fillable e relação `torneio()`

**Files:**
- Modify: `backend/app/Models/Cupom.php`
- Modify: `backend/app/Models/PedidoCheckout.php`
- Test: `backend/tests/Feature/MultiBolaoTest.php`

- [ ] **Step 1: Escrever o teste que falha**

Adicionar em `MultiBolaoTest`:

```php
public function test_cupom_e_pedido_expõem_relacao_torneio(): void
{
    $this->seed();
    $torneio = \App\Models\Torneio::query()->where('status', 'publicado')->firstOrFail();

    $pedido = \App\Models\PedidoCheckout::query()->create([
        'usuario_id' => \App\Models\Usuario::factory()->create()->id,
        'torneio_id' => $torneio->id,
        'valor' => 10,
        'status' => 'pendente',
    ]);

    $cupom = \App\Models\Cupom::query()->create([
        'usuario_id' => $pedido->usuario_id,
        'torneio_id' => $torneio->id,
        'pedido_checkout_id' => $pedido->id,
        'codigo' => 'TESTE12345',
        'status' => 'ativo',
    ]);

    $this->assertSame($torneio->id, $cupom->torneio->id);
    $this->assertSame($torneio->id, $pedido->torneio->id);
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `cd backend && php artisan test --filter=test_cupom_e_pedido_expõem_relacao_torneio`
Expected: FAIL — `torneio_id` não está no fillable / relação `torneio` indefinida.

- [ ] **Step 3: Atualizar os models**

Em `backend/app/Models/Cupom.php`, adicionar `'torneio_id'` ao `$fillable` (após `'usuario_id'`) e a relação:

```php
    protected $fillable = [
        'usuario_id',
        'torneio_id',
        'pedido_checkout_id',
        'codigo',
        'status',
    ];

    public function torneio(): BelongsTo
    {
        return $this->belongsTo(Torneio::class, 'torneio_id');
    }
```

Em `backend/app/Models/PedidoCheckout.php`, adicionar `'torneio_id'` ao `$fillable` (após `'usuario_id'`) e a relação:

```php
    public function torneio(): BelongsTo
    {
        return $this->belongsTo(Torneio::class, 'torneio_id');
    }
```

(`BelongsTo` já está importado em ambos os arquivos.)

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `cd backend && php artisan test --filter=test_cupom_e_pedido_expõem_relacao_torneio`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Models/Cupom.php backend/app/Models/PedidoCheckout.php backend/tests/Feature/MultiBolaoTest.php
git commit -m "feat: relacao torneio em Cupom e PedidoCheckout"
```

---

## Task 3: ServicoCheckout — `criarPedido(Usuario, Torneio, ?Cupom)`

**Files:**
- Modify: `backend/app/Services/ServicoCheckout.php`
- Test: `backend/tests/Feature/MultiBolaoTest.php`

- [ ] **Step 1: Escrever o teste que falha**

Adicionar em `MultiBolaoTest`:

```php
public function test_pedido_e_cupom_herdam_torneio_do_checkout(): void
{
    $this->seed();
    $torneio = \App\Models\Torneio::query()->where('status', 'publicado')->firstOrFail();

    $usuario = \App\Models\Usuario::factory()->create(['perfil' => 'usuario']);

    $servico = app(\App\Services\ServicoCheckout::class);
    $pedido = $servico->criarPedido($usuario, $torneio);

    $this->assertSame($torneio->id, $pedido->torneio_id);

    $cupom = $servico->marcarComoPago($pedido, 'RECEIVED');
    $this->assertSame($torneio->id, $cupom->torneio_id);
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `cd backend && php artisan test --filter=test_pedido_e_cupom_herdam_torneio_do_checkout`
Expected: FAIL — assinatura antiga de `criarPedido` (sem `Torneio`) e pedido sem `torneio_id`.

- [ ] **Step 3: Atualizar o serviço**

Em `backend/app/Services/ServicoCheckout.php`, substituir o método `criarPedido` (linhas ~20-46) por:

```php
    public function criarPedido(Usuario $usuario, Torneio $torneio, ?Cupom $cupom = null): PedidoCheckout
    {
        $pedido = DB::transaction(function () use ($usuario, $torneio, $cupom) {
            if ($cupom?->pedido_checkout_id) {
                return $cupom->pedidoCheckout()->lockForUpdate()->firstOrFail();
            }

            $pedido = PedidoCheckout::query()->create([
                'usuario_id' => $usuario->id,
                'torneio_id' => $torneio->id,
                'valor' => $torneio->valor_cupom ?? 10,
                'status' => 'pendente',
                'forma_pagamento' => 'pix',
                'referencia_checkout' => (string) Str::uuid(),
            ]);

            if ($cupom) {
                $cupom->forceFill([
                    'pedido_checkout_id' => $pedido->id,
                    'torneio_id' => $torneio->id,
                    'status' => 'aguardando_pagamento',
                ])->save();
            }

            return $pedido;
        });

        return $this->prepararPagamentoPix($pedido);
    }
```

No mesmo arquivo, no método `marcarComoPago`, ao **criar** o cupom (bloco `return Cupom::query()->create([...])`, linhas ~110-115), adicionar `torneio_id`:

```php
            return Cupom::query()->create([
                'usuario_id' => $pedido->usuario_id,
                'torneio_id' => $pedido->torneio_id,
                'pedido_checkout_id' => $pedido->id,
                'codigo' => $this->gerarCodigoCupom(),
                'status' => 'ativo',
            ]);
```

(`Torneio` já está importado no topo do serviço.)

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `cd backend && php artisan test --filter=test_pedido_e_cupom_herdam_torneio_do_checkout`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/ServicoCheckout.php backend/tests/Feature/MultiBolaoTest.php
git commit -m "feat: ServicoCheckout cria pedido por torneio e cupom herda torneio_id"
```

---

## Task 4: Request — `torneio_id` obrigatório no checkout

**Files:**
- Modify: `backend/app/Http/Requests/CriarPedidoCheckoutRequest.php`

- [ ] **Step 1: Atualizar as regras de validação**

Substituir o método `rules()` por:

```php
    public function rules(): array
    {
        return [
            'valor' => ['prohibited'],
            'torneio_id' => ['required', 'integer', 'exists:torneios,id'],
            'cupom_id' => ['nullable', 'integer', 'exists:cupons,id'],
        ];
    }
```

- [ ] **Step 2: Rodar a suíte de checkout e confirmar que QUEBRA (esperado)**

Run: `cd backend && php artisan test --filter=CheckoutFluxoTest`
Expected: FAIL em vários testes — os posts atuais não enviam `torneio_id`. Será corrigido na Task 5/Step 5.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Http/Requests/CriarPedidoCheckoutRequest.php
git commit -m "feat: torneio_id obrigatorio no CriarPedidoCheckoutRequest"
```

---

## Task 5: PedidoCheckoutController — checkout e compra por torneio

**Files:**
- Modify: `backend/app/Http/Controllers/PedidoCheckoutController.php`
- Modify: `backend/tests/Feature/CheckoutFluxoTest.php`
- Test: `backend/tests/Feature/MultiBolaoTest.php`

- [ ] **Step 1: Escrever o teste que falha (compra por torneio)**

Adicionar em `MultiBolaoTest`:

```php
public function test_compra_usa_compras_abertas_do_torneio_escolhido(): void
{
    $this->seed();
    $torneio = \App\Models\Torneio::query()->where('status', 'publicado')->firstOrFail();
    $torneio->forceFill(['compras_abertas' => false, 'valor_cupom' => 25.00])->save();

    $usuario = \App\Models\Usuario::factory()->create([
        'perfil' => 'usuario',
        'cpf_cnpj' => '12345678909',
    ]);
    \Laravel\Sanctum\Sanctum::actingAs($usuario);

    // Compra fechada nesse torneio => 403
    $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])->assertForbidden();

    // Abrindo a compra, o valor cobrado vem do torneio
    $torneio->forceFill(['compras_abertas' => true])->save();
    $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])
        ->assertCreated()
        ->assertJsonPath('pedido.valor', '25.00')
        ->assertJsonPath('pedido.torneio_id', $torneio->id);
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `cd backend && php artisan test --filter=test_compra_usa_compras_abertas_do_torneio_escolhido`
Expected: FAIL — controller ainda chama `criarPedido($user, $cupom)` e `garantirComprasAbertas()` sem torneio.

- [ ] **Step 3: Atualizar o controller**

Em `backend/app/Http/Controllers/PedidoCheckoutController.php`:

Substituir o método `store`:

```php
    public function store(CriarPedidoCheckoutRequest $request): JsonResponse
    {
        $torneio = Torneio::query()->findOrFail($request->integer('torneio_id'));
        $this->garantirComprasAbertas($torneio);

        $cupom = $request->filled('cupom_id')
            ? Cupom::query()->findOrFail($request->integer('cupom_id'))
            : null;

        abort_if($cupom && $cupom->usuario_id !== $request->user()->id, 403);

        try {
            $pedido = $this->servicoCheckout->criarPedido($request->user(), $torneio, $cupom);
        } catch (ExcecaoAsaas $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'codigo' => $exception->codigoAsaas(),
            ], $exception->statusCode());
        } catch (RequestException) {
            return response()->json([
                'message' => 'Nao foi possivel conectar ao Asaas. Tente novamente em instantes.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json([
            'pedido' => $pedido->loadMissing('cupons'),
        ], 201);
    }
```

Substituir o método `pagamentoCupom` (deriva o torneio do cupom):

```php
    public function pagamentoCupom(Request $request, Cupom $cupom): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        $torneio = $cupom->torneio()->firstOrFail();
        $this->garantirComprasAbertas($torneio);

        try {
            $pedido = $this->servicoCheckout->criarPedido($request->user(), $torneio, $cupom);
        } catch (ExcecaoAsaas $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'codigo' => $exception->codigoAsaas(),
            ], $exception->statusCode());
        } catch (RequestException) {
            return response()->json([
                'message' => 'Nao foi possivel conectar ao Asaas. Tente novamente em instantes.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json([
            'pedido' => $pedido->loadMissing('cupons'),
            'cupom' => $cupom->fresh('pedidoCheckout'),
        ]);
    }
```

Substituir o método `garantirComprasAbertas` para receber o torneio:

```php
    private function garantirComprasAbertas(Torneio $torneio): void
    {
        abort_unless((bool) $torneio->compras_abertas, 403, 'A compra de cupons esta encerrada.');
    }
```

(`Torneio`, `Cupom`, `Request` já estão importados.)

- [ ] **Step 4: Rodar o teste novo e confirmar que passa**

Run: `cd backend && php artisan test --filter=test_compra_usa_compras_abertas_do_torneio_escolhido`
Expected: PASS.

- [ ] **Step 5: Atualizar os testes existentes de checkout para enviar `torneio_id`**

Em `backend/tests/Feature/CheckoutFluxoTest.php`, em **cada** chamada `postJson('/api/pedidos-checkout' ...)`, incluir o torneio publicado. Padrão a aplicar em todos os métodos de teste do arquivo:

No início de cada teste que faz checkout, garantir a referência ao torneio (a maioria já tem `$this->seed();`). Onde o teste **não** declara `$torneio`, adicionar antes do primeiro post:

```php
$torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
```

E trocar:

```php
$this->postJson('/api/pedidos-checkout')
```
por
```php
$this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])
```

Aplicar nos testes: `test_criar_pedido_usa_valor_cupom_do_torneio`, `test_compra_encerrada_bloqueia_criacao_de_pedido` (este já tem `$torneio` implícito via update; declarar e passar o id), `test_checkout_rejeita_valor_enviado_pelo_cliente` (mantém o `valor` proibido **e** adiciona `torneio_id`), `test_rota_de_simulacao_de_pagamento_nao_fica_disponivel_para_usuario`, `test_confirmar_pagamento_sandbox_ativa_cupom`, `test_multiplos_cupons_por_usuario` (os dois posts), `test_pagamento_duplicado_retorna_cupom_existente`.

Exemplo concreto para `test_checkout_rejeita_valor_enviado_pelo_cliente` (o `valor` continua proibido, então o 422 persiste):

```php
$torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();

$this->postJson('/api/pedidos-checkout', [
    'torneio_id' => $torneio->id,
    'valor' => 1,
])->assertStatus(422)
    ->assertJsonValidationErrors(['valor']);
```

- [ ] **Step 6: Rodar a suíte de checkout inteira e confirmar verde**

Run: `cd backend && php artisan test --filter=CheckoutFluxoTest`
Expected: PASS em todos.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Controllers/PedidoCheckoutController.php backend/tests/Feature/CheckoutFluxoTest.php backend/tests/Feature/MultiBolaoTest.php
git commit -m "feat: checkout e compra operam por torneio explicito"
```

---

## Task 6: `GET /api/boloes` — lista pública de bolões

**Files:**
- Create: `backend/app/Http/Controllers/BolaoController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/MultiBolaoTest.php`

- [ ] **Step 1: Escrever o teste que falha**

Adicionar em `MultiBolaoTest`:

```php
public function test_lista_boloes_separa_ativos_e_encerrados(): void
{
    $this->seed();
    // O seed cria um torneio 'publicado'. Cria um segundo 'encerrado'.
    \App\Models\Torneio::query()->create([
        'nome' => 'Bolao Encerrado',
        'edicao' => '2025',
        'status' => 'encerrado',
        'valor_cupom' => 10.00,
        'compras_abertas' => false,
    ]);

    $resp = $this->getJson('/api/boloes')->assertOk();

    $resp->assertJsonPath('ativos.0.status', 'publicado');
    $this->assertNotEmpty($resp->json('ativos'));
    $this->assertSame('encerrado', $resp->json('encerrados.0.status'));
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `cd backend && php artisan test --filter=test_lista_boloes_separa_ativos_e_encerrados`
Expected: FAIL — rota `/api/boloes` inexistente (404).

- [ ] **Step 3: Criar o controller**

`backend/app/Http/Controllers/BolaoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Torneio;
use Illuminate\Http\JsonResponse;

class BolaoController extends Controller
{
    public function index(): JsonResponse
    {
        $boloes = Torneio::query()
            ->whereIn('status', ['publicado', 'encerrado'])
            ->orderByDesc('id')
            ->get(['id', 'nome', 'edicao', 'status', 'valor_cupom', 'compras_abertas', 'data_inicio', 'data_fim']);

        return response()->json([
            'ativos' => $boloes->where('status', 'publicado')->values(),
            'encerrados' => $boloes->where('status', 'encerrado')->values(),
        ]);
    }
}
```

- [ ] **Step 4: Registrar a rota**

Em `backend/routes/api.php`, adicionar o import e a rota pública (junto às outras rotas públicas, antes do grupo `auth:sanctum`):

No topo, com os outros `use`:

```php
use App\Http\Controllers\BolaoController;
```

Após a linha `Route::get('/torneio', [TorneioController::class, 'publico']);`:

```php
Route::get('/boloes', [BolaoController::class, 'index']);
```

- [ ] **Step 5: Rodar o teste e confirmar que passa**

Run: `cd backend && php artisan test --filter=test_lista_boloes_separa_ativos_e_encerrados`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/BolaoController.php backend/routes/api.php backend/tests/Feature/MultiBolaoTest.php
git commit -m "feat: rota GET /api/boloes lista boloes ativos e encerrados"
```

---

## Task 7: `GET /api/torneios/{torneio}` — carregar um bolão específico

**Files:**
- Modify: `backend/app/Http/Controllers/TorneioController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/MultiBolaoTest.php`

- [ ] **Step 1: Escrever o teste que falha**

Adicionar em `MultiBolaoTest`:

```php
public function test_mostra_torneio_especifico_por_id(): void
{
    $this->seed();
    $torneio = \App\Models\Torneio::query()->where('status', 'publicado')->firstOrFail();

    $this->getJson("/api/torneios/{$torneio->id}")
        ->assertOk()
        ->assertJsonPath('torneio.id', $torneio->id)
        ->assertJsonStructure(['torneio' => ['id', 'nome', 'fases', 'jogos', 'grupos']]);
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `cd backend && php artisan test --filter=test_mostra_torneio_especifico_por_id`
Expected: FAIL — rota `/api/torneios/{torneio}` inexistente (404).

- [ ] **Step 3: Refatorar TorneioController e adicionar `show`**

Em `backend/app/Http/Controllers/TorneioController.php`:

Substituir o método `publico()` e o `private function carregarTorneio()` por:

```php
    public function publico(): JsonResponse
    {
        $torneio = Torneio::query()
            ->where('status', 'publicado')
            ->latest('id')
            ->firstOrFail();

        return response()->json([
            'torneio' => $this->carregarRelacionamentos($torneio),
        ]);
    }

    public function show(Torneio $torneio): JsonResponse
    {
        return response()->json([
            'torneio' => $this->carregarRelacionamentos($torneio),
        ]);
    }

    private function carregarRelacionamentos(Torneio $torneio): Torneio
    {
        return $torneio->load([
            'resultadoTorneio',
            'grupos.selecoes.jogadores',
            'fases' => fn ($query) => $query->orderBy('ordem'),
            'fases.rodadas' => fn ($query) => $query->orderBy('ordem'),
            'jogos' => fn ($query) => $query->orderBy('data_hora_inicio'),
            'jogos.fase',
            'jogos.rodada',
            'jogos.grupo',
            'jogos.selecaoMandante',
            'jogos.selecaoVisitante',
            'jogos.resultado',
            'regrasPontuacao' => fn ($query) => $query->orderBy('chave'),
        ]);
    }
```

- [ ] **Step 4: Registrar a rota**

Em `backend/routes/api.php`, após a rota `GET /torneios/{torneio}/ranking`, adicionar:

```php
Route::get('/torneios/{torneio}', [TorneioController::class, 'show']);
```

> Atenção à ordem: a rota mais específica (`/torneios/{torneio}/ranking`) deve continuar **acima** de `/torneios/{torneio}` para não haver ambiguidade. Como `/ranking` é um segmento adicional, não há conflito real, mas mantenha a ordem por clareza.

- [ ] **Step 5: Rodar o teste e confirmar que passa**

Run: `cd backend && php artisan test --filter=test_mostra_torneio_especifico_por_id`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/TorneioController.php backend/routes/api.php backend/tests/Feature/MultiBolaoTest.php
git commit -m "feat: rota GET /api/torneios/{torneio} carrega bolao especifico"
```

---

## Task 8: Admin — `dados` e `criarRegraPontuacao` aceitam `torneio_id`

**Files:**
- Modify: `backend/app/Http/Controllers/PainelAdministradorController.php`
- Test: `backend/tests/Feature/MultiBolaoTest.php`

- [ ] **Step 1: Escrever o teste que falha**

Adicionar em `MultiBolaoTest`:

```php
public function test_admin_dados_carrega_torneio_escolhido(): void
{
    $this->seed();
    $atual = \App\Models\Torneio::query()->where('status', 'publicado')->firstOrFail();

    // Segundo torneio, mais novo (sem torneio_id explicito retornaria este).
    $outro = \App\Models\Torneio::query()->create([
        'nome' => 'Bolao Mata-mata',
        'edicao' => '2026',
        'status' => 'publicado',
        'valor_cupom' => 20.00,
        'compras_abertas' => true,
    ]);

    $admin = \App\Models\Usuario::factory()->create(['perfil' => 'administrador']);
    \Laravel\Sanctum\Sanctum::actingAs($admin);

    // Sem torneio_id => pega o mais novo
    $this->getJson('/api/admin/dados')
        ->assertOk()
        ->assertJsonPath('torneio.id', $outro->id);

    // Com torneio_id => pega o escolhido
    $this->getJson("/api/admin/dados?torneio_id={$atual->id}")
        ->assertOk()
        ->assertJsonPath('torneio.id', $atual->id);
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `cd backend && php artisan test --filter=test_admin_dados_carrega_torneio_escolhido`
Expected: FAIL — `dados()` ignora `torneio_id` e sempre usa `latest('id')`.

- [ ] **Step 3: Atualizar o controller**

Em `backend/app/Http/Controllers/PainelAdministradorController.php`:

Trocar a assinatura e a query inicial de `dados()`:

```php
    public function dados(Request $request): JsonResponse
    {
        $torneio = Torneio::query()
            ->when(
                $request->filled('torneio_id'),
                fn ($query) => $query->whereKey($request->integer('torneio_id')),
            )
            ->with([
                'resultadoTorneio',
                'grupos.selecoes.jogadores',
                'fases' => fn ($query) => $query->orderBy('ordem'),
                'fases.rodadas' => fn ($query) => $query->orderBy('ordem'),
                'jogos' => fn ($query) => $query->orderBy('data_hora_inicio'),
                'jogos.fase',
                'jogos.rodada',
                'jogos.grupo',
                'jogos.selecaoMandante',
                'jogos.selecaoVisitante',
                'jogos.resultado',
                'regrasPontuacao' => fn ($query) => $query->withCount('eventosPontuacao')->orderBy('chave'),
            ])
            ->latest('id')
            ->firstOrFail();

        // ... restante do metodo inalterado (participantesPorJogo, return) ...
```

No `criarRegraPontuacao(Request $request)`, trocar a resolução do torneio (linha ~208) para respeitar `torneio_id` quando enviado:

```php
        $torneio = $request->filled('torneio_id')
            ? Torneio::query()->findOrFail($request->integer('torneio_id'))
            : Torneio::query()->latest('id')->firstOrFail();
```

E adicionar a chave `torneio_id` às regras de validação do mesmo método:

```php
        $dados = $request->validate([
            'torneio_id' => ['nullable', 'integer', 'exists:torneios,id'],
            'chave' => ['required', 'string', Rule::in(array_keys(self::CHAVES_PONTUACAO))],
            'fase_id' => ['nullable', 'integer', Rule::exists('fases', 'id')->where('torneio_id', $torneio->id)],
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'pontos' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);
```

(`Request` e `Torneio` já estão importados.)

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `cd backend && php artisan test --filter=test_admin_dados_carrega_torneio_escolhido`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/PainelAdministradorController.php backend/tests/Feature/MultiBolaoTest.php
git commit -m "feat: admin dados e regras de pontuacao por torneio escolhido"
```

---

## Task 9: Suíte completa verde + verificação manual

**Files:** (nenhum novo)

- [ ] **Step 1: Rodar a suíte inteira**

Run: `cd backend && php artisan test`
Expected: PASS em toda a suíte (incluindo `MvpFluxoApiTest` e `FechamentoApostasTest`). Se algum quebrar por falta de `torneio_id` no checkout, aplicar o mesmo ajuste da Task 5/Step 5 nesse teste.

- [ ] **Step 2: Verificação manual da nova rota**

Run: `cd backend && php artisan migrate:fresh --seed`
Run: `cd backend && php artisan serve --port=8888` (em outro terminal)
Run: `curl -s http://localhost:8888/api/boloes`
Expected: JSON com `ativos` (≥1) e `encerrados` (array).

- [ ] **Step 3: Commit (se houve ajustes em outros testes)**

```bash
git add backend/tests/
git commit -m "test: ajusta suite para checkout por torneio"
```

---

## Self-review (cobertura do spec — Fase A)

- **`cupons.torneio_id` / `pedidos_checkout.torneio_id`** → Task 1, 2, 3. ✓
- **Checkout por torneio (valor + compras_abertas do bolão)** → Task 4, 5. ✓
- **Fim de `latest('id')` em checkout** (`ServicoCheckout`, `PedidoCheckoutController`) → Task 3, 5. ✓
- **Fim de `latest('id')` no admin** (`dados`, `criarRegraPontuacao`) → Task 8. ✓
- **`GET /api/boloes` (ativos + encerrados)** → Task 6. ✓
- **`GET /api/torneios/{torneio}`** (frontend carregar o bolão do cupom) → Task 7. ✓
- **Ranking por torneio** → já existe (`/torneios/{torneio}/ranking`); sem mudança. ✓
- **`compras_abertas` por bolão** → já é coluna do torneio; checkout agora respeita o torneio escolhido (Task 5). ✓

**Fora desta Fase A (planos próprios):** frontend (lista de bolões, aba Encerrados, seletor admin, wiring de checkout, CupomView carregar o torneio do cupom); mata-mata pela realidade (Fase B); seeder do bolão + tornar `torneio_id` NOT NULL em produção (Fase C).

**Nota para a Fase C / seeders locais:** `DemonstracaoSeeder` cria cupons via `Cupom::updateOrCreate` sem `torneio_id`. Como a coluna é nullable nesta fase, não quebra; mas o seeder deve passar `'torneio_id' => $torneio->id` ao criar cupom/pedido para ficar consistente. Incluir esse ajuste no plano da Fase C.
