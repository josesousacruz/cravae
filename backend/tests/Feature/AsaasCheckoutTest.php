<?php

namespace Tests\Feature;

use App\Exceptions\ExcecaoAsaas;
use App\Models\Cupom;
use App\Models\PedidoCheckout;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoAsaas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AsaasCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_pagamento_pix_com_qr_code(): void
    {
        $this->seed();
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);

        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $response = $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id]);

        $response
            ->assertCreated()
            ->assertJsonPath('pedido.status', 'pendente')
            ->assertJsonPath('pedido.asaas_status', 'PENDING')
            ->assertJsonPath('pedido.pix_copia_cola', 'pix-copia-e-cola')
            ->assertJsonPath('pedido.pix_qr_code_base64', 'base64-qr-code');
    }

    public function test_retorna_erro_controlado_sem_token_asaas(): void
    {
        config(['services.asaas.access_token' => '']);

        $this->seed();
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);

        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Integracao Asaas nao configurada.');
    }

    public function test_webhook_received_ativa_cupom_existente_sem_apagar_apostas(): void
    {
        $this->seed();
        $usuario = Usuario::factory()->create();
        $pedido = PedidoCheckout::query()->create([
            'usuario_id' => $usuario->id,
            'valor' => 10,
            'status' => 'pendente',
            'forma_pagamento' => 'pix',
            'referencia_checkout' => 'cupom-retroativo:1',
            'asaas_pagamento_id' => 'pay_teste',
        ]);
        $cupom = Cupom::query()->create([
            'usuario_id' => $usuario->id,
            'pedido_checkout_id' => $pedido->id,
            'codigo' => 'CUPOMTESTE',
            'status' => 'aguardando_pagamento',
        ]);
        $cupom->apostas()->create([
            'torneio_id' => 1,
            'tipo' => 'palpite_campeao',
            'conteudo' => ['selecao_id' => 1],
        ]);

        $payload = [
            'id' => 'evt_teste_1',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_teste',
                'status' => 'RECEIVED',
                'externalReference' => 'pedido_checkout:'.$pedido->id,
            ],
        ];

        $this->postJson('/api/webhooks/asaas/pagamentos', $payload, [
            'asaas-access-token' => 'test-webhook-token-with-more-than-32-chars',
        ])->assertOk();

        $this->assertSame('pago', $pedido->fresh()->status);
        $this->assertSame('ativo', $cupom->fresh()->status);
        $this->assertSame(1, $cupom->apostas()->count());

        $this->postJson('/api/webhooks/asaas/pagamentos', $payload, [
            'asaas-access-token' => 'test-webhook-token-with-more-than-32-chars',
        ])->assertOk();

        $this->assertSame(1, Cupom::query()->where('pedido_checkout_id', $pedido->id)->count());
    }

    public function test_webhook_com_token_invalido_eh_rejeitado(): void
    {
        $this->postJson('/api/webhooks/asaas/pagamentos', [
            'id' => 'evt_token_invalido',
            'event' => 'PAYMENT_RECEIVED',
        ], [
            'asaas-access-token' => 'invalido',
        ])->assertUnauthorized();
    }

    public function test_usuario_confirma_pagamento_no_sandbox(): void
    {
        $this->seed();
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);

        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $pedido = $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])->assertCreated()->json('pedido');

        $this->postJson("/api/pedidos-checkout/{$pedido['id']}/confirmar-sandbox")
            ->assertOk()
            ->assertJsonPath('pedido.status', 'pago')
            ->assertJsonPath('cupom.status', 'ativo');
    }

    public function test_sincronizacao_pendente_mantem_pedido_pendente(): void
    {
        $this->seed();
        $this->mockConsultaPagamentoAsaas('PENDING');
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $pedido = $this->criarPedidoPendente($usuario);

        $this->getJson("/api/pedidos-checkout/{$pedido->id}?sincronizar=1")
            ->assertOk()
            ->assertJsonPath('pedido.status', 'pendente')
            ->assertJsonPath('pedido.asaas_status', 'PENDING')
            ->assertJsonPath('sincronizacao_erro', null);
    }

    public function test_sincronizacao_received_marca_pago_e_ativa_cupom(): void
    {
        $this->seed();
        $this->mockConsultaPagamentoAsaas('RECEIVED');
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $pedido = $this->criarPedidoPendente($usuario);

        $this->getJson("/api/pedidos-checkout/{$pedido->id}?sincronizar=1")
            ->assertOk()
            ->assertJsonPath('pedido.status', 'pago')
            ->assertJsonPath('pedido.asaas_status', 'RECEIVED')
            ->assertJsonPath('cupom.status', 'ativo');

        $this->assertNotNull($pedido->fresh()->pago_at);
        $this->assertSame(1, Cupom::query()->where('pedido_checkout_id', $pedido->id)->count());
    }

    public function test_sincronizacao_overdue_marca_expirado(): void
    {
        $this->seed();
        $this->mockConsultaPagamentoAsaas('OVERDUE');
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $pedido = $this->criarPedidoPendente($usuario);

        $this->getJson("/api/pedidos-checkout/{$pedido->id}?sincronizar=1")
            ->assertOk()
            ->assertJsonPath('pedido.status', 'expirado')
            ->assertJsonPath('pedido.asaas_status', 'OVERDUE');
    }

    public function test_sincronizacao_refunded_marca_estornado(): void
    {
        $this->seed();
        $this->mockConsultaPagamentoAsaas('REFUNDED');
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $pedido = $this->criarPedidoPendente($usuario);

        $this->getJson("/api/pedidos-checkout/{$pedido->id}?sincronizar=1")
            ->assertOk()
            ->assertJsonPath('pedido.status', 'estornado')
            ->assertJsonPath('pedido.asaas_status', 'REFUNDED');
    }

    public function test_sincronizacao_respeita_autorizacao_do_usuario(): void
    {
        $this->seed();
        $dono = Usuario::factory()->create();
        $intruso = Usuario::factory()->create();
        $pedido = $this->criarPedidoPendente($dono);
        Sanctum::actingAs($intruso);

        $this->getJson("/api/pedidos-checkout/{$pedido->id}?sincronizar=1")
            ->assertForbidden();
    }

    public function test_show_com_erro_de_sincronizacao_retorna_pedido_local(): void
    {
        $this->seed();
        $this->mock(ServicoAsaas::class, function ($mock): void {
            $mock->shouldReceive('consultarPagamento')
                ->once()
                ->andThrow(new ExcecaoAsaas('Asaas: Falha temporaria', 500, 'erro_teste'));
        });
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $pedido = $this->criarPedidoPendente($usuario);

        $this->getJson("/api/pedidos-checkout/{$pedido->id}?sincronizar=1")
            ->assertOk()
            ->assertJsonPath('pedido.status', 'pendente')
            ->assertJsonPath('sincronizacao_erro', 'Asaas: Falha temporaria');
    }

    public function test_comando_sincroniza_pendentes_e_nao_toca_pagos(): void
    {
        $this->seed();
        $this->mockConsultaPagamentoAsaas('RECEIVED');
        $usuario = Usuario::factory()->create();
        $pendente = $this->criarPedidoPendente($usuario, 'pay_pendente');
        $pago = PedidoCheckout::query()->create([
            'usuario_id' => $usuario->id,
            'valor' => 10,
            'status' => 'pago',
            'forma_pagamento' => 'pix',
            'referencia_checkout' => 'pedido-pago',
            'asaas_pagamento_id' => 'pay_pago',
            'asaas_status' => 'RECEIVED',
            'pago_at' => now(),
        ]);

        $this->artisan('asaas:sincronizar-pagamentos', ['--limite' => 100])
            ->expectsOutput('Consultados: 1 | Pagos: 1 | Expirados: 0 | Estornados: 0 | Cancelados: 0 | Pendentes: 0 | Erros: 0')
            ->assertSuccessful();

        $this->assertSame('pago', $pendente->fresh()->status);
        $this->assertSame('pago', $pago->fresh()->status);
    }

    private function criarPedidoPendente(Usuario $usuario, string $asaasPagamentoId = 'pay_teste_sync'): PedidoCheckout
    {
        return PedidoCheckout::query()->create([
            'usuario_id' => $usuario->id,
            'valor' => 10,
            'status' => 'pendente',
            'forma_pagamento' => 'pix',
            'referencia_checkout' => 'pedido-sync',
            'asaas_pagamento_id' => $asaasPagamentoId,
            'asaas_status' => 'PENDING',
        ]);
    }

    private function mockConsultaPagamentoAsaas(string $status, bool $deleted = false): void
    {
        $this->mock(ServicoAsaas::class, function ($mock) use ($status, $deleted): void {
            $mock->shouldReceive('consultarPagamento')
                ->andReturn([
                    'id' => 'pay_teste_sync',
                    'status' => $status,
                    'deleted' => $deleted,
                ]);
        });
    }
}
