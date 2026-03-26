<?php

namespace App\Services;

use App\Models\Cupom;
use App\Models\PedidoCheckout;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicoCheckout
{
    public function criarPedido(Usuario $usuario, ?float $valor = null): PedidoCheckout
    {
        return PedidoCheckout::query()->create([
            'usuario_id' => $usuario->id,
            'valor' => $valor ?? 10,
            'status' => 'pendente',
            'referencia_checkout' => (string) Str::uuid(),
        ]);
    }

    public function simularPagamento(PedidoCheckout $pedido): Cupom
    {
        return DB::transaction(function () use ($pedido) {
            if ($pedido->status === 'pago') {
                return Cupom::query()->firstOrCreate(
                    ['pedido_checkout_id' => $pedido->id],
                    [
                        'usuario_id' => $pedido->usuario_id,
                        'codigo' => strtoupper(Str::random(10)),
                        'status' => 'ativo',
                    ],
                );
            }

            $pedido->forceFill([
                'status' => 'pago',
                'pago_at' => now(),
            ])->save();

            return Cupom::query()->create([
                'usuario_id' => $pedido->usuario_id,
                'pedido_checkout_id' => $pedido->id,
                'codigo' => strtoupper(Str::random(10)),
                'status' => 'ativo',
            ]);
        });
    }
}
