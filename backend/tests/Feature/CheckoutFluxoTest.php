<?php

namespace Tests\Feature;

use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutFluxoTest extends TestCase
{
    use RefreshDatabase;

    public function test_criar_pedido_usa_valor_cupom_do_torneio(): void
    {
        $this->seed();

        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $torneio->forceFill(['valor_cupom' => 15.00])->save();

        $usuario = Usuario::factory()->create([
            'nome' => 'Checkout User',
            'email' => 'checkout@example.com',
            'telefone' => '11999990000',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/pedidos-checkout');

        $response
            ->assertCreated()
            ->assertJsonPath('pedido.valor', '15.00');
    }

    public function test_compra_encerrada_bloqueia_criacao_de_pedido(): void
    {
        $this->seed();
        Torneio::query()->where('status', 'publicado')->update(['compras_abertas' => false]);

        $usuario = Usuario::factory()->create([
            'nome' => 'Compra Fechada',
            'email' => 'compra-fechada@example.com',
            'telefone' => '11999990009',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/pedidos-checkout')->assertForbidden();
    }

    public function test_admin_abre_e_fecha_a_compra_de_cupons(): void
    {
        $this->seed();
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();

        $admin = Usuario::factory()->create([
            'nome' => 'Admin Toggle',
            'email' => 'adm-toggle@example.com',
            'telefone' => '11999990010',
            'perfil' => 'administrador',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/torneios/{$torneio->id}/compras", ['compras_abertas' => false])
            ->assertOk()
            ->assertJsonPath('torneio.compras_abertas', false);

        $this->assertFalse((bool) $torneio->fresh()->compras_abertas);

        $this->putJson("/api/admin/torneios/{$torneio->id}/compras", ['compras_abertas' => true])
            ->assertOk()
            ->assertJsonPath('torneio.compras_abertas', true);

        $this->assertTrue((bool) $torneio->fresh()->compras_abertas);
    }

    public function test_usuario_comum_nao_altera_compra_de_cupons(): void
    {
        $this->seed();
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();

        $usuario = Usuario::factory()->create([
            'nome' => 'Comum Toggle',
            'email' => 'comum-toggle@example.com',
            'telefone' => '11999990011',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $this->putJson("/api/admin/torneios/{$torneio->id}/compras", ['compras_abertas' => true])
            ->assertForbidden();
    }

    public function test_checkout_rejeita_valor_enviado_pelo_cliente(): void
    {
        $this->seed();

        $usuario = Usuario::factory()->create([
            'nome' => 'Valor Cliente',
            'email' => 'valor-cliente@example.com',
            'telefone' => '11999990004',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/pedidos-checkout', [
            'valor' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['valor']);
    }

    public function test_rota_de_simulacao_de_pagamento_nao_fica_disponivel_para_usuario(): void
    {
        $this->seed();

        $usuario = Usuario::factory()->create([
            'nome' => 'Sem Simulacao',
            'email' => 'sem-simulacao@example.com',
            'telefone' => '11999990005',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $pedido = $this->postJson('/api/pedidos-checkout')->assertCreated()->json('pedido');

        $this->postJson("/api/pedidos-checkout/{$pedido['id']}/simular-pagamento")
            ->assertNotFound();
    }

    public function test_confirmar_pagamento_sandbox_ativa_cupom(): void
    {
        $this->seed();

        $usuario = Usuario::factory()->create([
            'nome' => 'Pagamento User',
            'email' => 'pag@example.com',
            'telefone' => '11999990001',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $pedidoResponse = $this->postJson('/api/pedidos-checkout');
        $pedidoId = $pedidoResponse->json('pedido.id');

        $response = $this->postJson("/api/pedidos-checkout/{$pedidoId}/confirmar-sandbox");

        $response
            ->assertOk()
            ->assertJsonPath('pedido.status', 'pago')
            ->assertJsonPath('cupom.status', 'ativo');
    }

    public function test_multiplos_cupons_por_usuario(): void
    {
        $this->seed();

        $usuario = Usuario::factory()->create([
            'nome' => 'Multi Cupom',
            'email' => 'multi@example.com',
            'telefone' => '11999990002',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $pedido1 = $this->postJson('/api/pedidos-checkout');
        $this->postJson("/api/pedidos-checkout/{$pedido1->json('pedido.id')}/confirmar-sandbox");

        $pedido2 = $this->postJson('/api/pedidos-checkout');
        $this->postJson("/api/pedidos-checkout/{$pedido2->json('pedido.id')}/confirmar-sandbox");

        $cuponsResponse = $this->getJson('/api/cupons');
        $cuponsResponse->assertOk();

        $cupons = $cuponsResponse->json('cupons');
        $this->assertCount(2, $cupons);
        $this->assertNotEquals($cupons[0]['id'], $cupons[1]['id']);
    }

    public function test_pagamento_duplicado_retorna_cupom_existente(): void
    {
        $this->seed();

        $usuario = Usuario::factory()->create([
            'nome' => 'Idempotente User',
            'email' => 'idemp@example.com',
            'telefone' => '11999990003',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $pedido = $this->postJson('/api/pedidos-checkout');
        $pedidoId = $pedido->json('pedido.id');

        $primeiro = $this->postJson("/api/pedidos-checkout/{$pedidoId}/confirmar-sandbox");
        $cupomId1 = $primeiro->json('cupom.id');

        $segundo = $this->postJson("/api/pedidos-checkout/{$pedidoId}/confirmar-sandbox");
        $cupomId2 = $segundo->json('cupom.id');

        $this->assertEquals($cupomId1, $cupomId2);
    }
}
