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

    public function test_cupom_e_pedido_expoem_relacao_torneio(): void
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

    public function test_pedido_e_cupom_herdam_torneio_do_checkout(): void
    {
        $this->seed();
        $torneio = \App\Models\Torneio::query()->where('status', 'publicado')->firstOrFail();

        $usuario = \App\Models\Usuario::factory()->create([
            'perfil' => 'usuario',
            'cpf_cnpj' => '12345678909',
        ]);

        $servico = app(\App\Services\ServicoCheckout::class);
        $pedido = $servico->criarPedido($usuario, $torneio);

        $this->assertSame($torneio->id, $pedido->torneio_id);

        $cupom = $servico->marcarComoPago($pedido, 'RECEIVED');
        $this->assertSame($torneio->id, $cupom->torneio_id);
    }

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

        $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])->assertForbidden();

        $torneio->forceFill(['compras_abertas' => true])->save();
        $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])
            ->assertCreated()
            ->assertJsonPath('pedido.valor', '25.00')
            ->assertJsonPath('pedido.torneio_id', $torneio->id);
    }

    public function test_lista_boloes_separa_ativos_e_encerrados(): void
    {
        $this->seed();
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

    public function test_mostra_torneio_especifico_por_id(): void
    {
        $this->seed();
        $torneio = \App\Models\Torneio::query()->where('status', 'publicado')->firstOrFail();

        $this->getJson("/api/torneios/{$torneio->id}")
            ->assertOk()
            ->assertJsonPath('torneio.id', $torneio->id)
            ->assertJsonStructure(['torneio' => ['id', 'nome', 'fases', 'jogos', 'grupos']]);
    }
}
