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

    public function test_simular_pagamento_ativa_cupom(): void
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

        $response = $this->postJson("/api/pedidos-checkout/{$pedidoId}/simular-pagamento");

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

        // Primeiro pedido + pagamento
        $pedido1 = $this->postJson('/api/pedidos-checkout');
        $this->postJson("/api/pedidos-checkout/{$pedido1->json('pedido.id')}/simular-pagamento");

        // Segundo pedido + pagamento
        $pedido2 = $this->postJson('/api/pedidos-checkout');
        $this->postJson("/api/pedidos-checkout/{$pedido2->json('pedido.id')}/simular-pagamento");

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

        $primeiro = $this->postJson("/api/pedidos-checkout/{$pedidoId}/simular-pagamento");
        $cupomId1 = $primeiro->json('cupom.id');

        $segundo = $this->postJson("/api/pedidos-checkout/{$pedidoId}/simular-pagamento");
        $cupomId2 = $segundo->json('cupom.id');

        $this->assertEquals($cupomId1, $cupomId2);
    }
}
