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
}
