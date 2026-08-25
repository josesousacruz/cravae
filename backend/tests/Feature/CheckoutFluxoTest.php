<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\PedidoCheckout;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoAssinatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutFluxoTest extends TestCase
{
    use RefreshDatabase;

    public function test_compra_de_cupom_responde_410_no_modelo_de_assinatura(): void
    {
        $this->seed();

        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();

        $usuario = Usuario::factory()->create([
            'nome' => 'Checkout User',
            'email' => 'checkout@example.com',
            'telefone' => '11999990000',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])
            ->assertStatus(410);
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

        $pedido = PedidoCheckout::query()->create([
            'usuario_id' => $usuario->id,
            'valor' => 10,
            'status' => 'pendente',
            'forma_pagamento' => 'pix',
            'referencia_checkout' => 'pedido-sem-simulacao',
        ]);

        $this->postJson("/api/pedidos-checkout/{$pedido->id}/simular-pagamento")
            ->assertNotFound();
    }

    public function test_confirmar_pagamento_sandbox_ativa_cupom(): void
    {
        $this->seed();
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();

        $usuario = Usuario::factory()->create([
            'nome' => 'Pagamento User',
            'email' => 'pag@example.com',
            'telefone' => '11999990001',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $cupom = Cupom::query()->create([
            'usuario_id' => $usuario->id,
            'torneio_id' => $torneio->id,
            'codigo' => strtoupper(Str::random(10)),
            'status' => 'aguardando_pagamento',
        ]);

        $pedidoId = $this->postJson("/api/cupons/{$cupom->id}/pagamento")
            ->assertOk()
            ->json('pedido.id');

        $response = $this->postJson("/api/pedidos-checkout/{$pedidoId}/confirmar-sandbox");

        $response
            ->assertOk()
            ->assertJsonPath('pedido.status', 'pago')
            ->assertJsonPath('cupom.status', 'ativo');
    }

    public function test_cupom_gratuito_e_unico_por_usuario_e_torneio(): void
    {
        $this->seed();
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();

        $usuario = Usuario::factory()->create([
            'nome' => 'Multi Cupom',
            'email' => 'multi@example.com',
            'telefone' => '11999990002',
            'perfil' => 'usuario',
        ]);

        $servico = app(ServicoAssinatura::class);
        $primeiro = $servico->garantirCupomGratuito($usuario, $torneio);
        $segundo = $servico->garantirCupomGratuito($usuario, $torneio);

        $this->assertSame($primeiro->id, $segundo->id);
        $this->assertSame('ativo', $primeiro->status);
        $this->assertSame(1, Cupom::query()
            ->where('usuario_id', $usuario->id)
            ->where('torneio_id', $torneio->id)
            ->count());
    }

    public function test_pagamento_duplicado_retorna_cupom_existente(): void
    {
        $this->seed();
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();

        $usuario = Usuario::factory()->create([
            'nome' => 'Idempotente User',
            'email' => 'idemp@example.com',
            'telefone' => '11999990003',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $cupom = Cupom::query()->create([
            'usuario_id' => $usuario->id,
            'torneio_id' => $torneio->id,
            'codigo' => strtoupper(Str::random(10)),
            'status' => 'aguardando_pagamento',
        ]);

        $pedidoId = $this->postJson("/api/cupons/{$cupom->id}/pagamento")
            ->assertOk()
            ->json('pedido.id');

        $primeiro = $this->postJson("/api/pedidos-checkout/{$pedidoId}/confirmar-sandbox");
        $cupomId1 = $primeiro->json('cupom.id');

        $segundo = $this->postJson("/api/pedidos-checkout/{$pedidoId}/confirmar-sandbox");
        $cupomId2 = $segundo->json('cupom.id');

        $this->assertEquals($cupomId1, $cupomId2);
    }
}
