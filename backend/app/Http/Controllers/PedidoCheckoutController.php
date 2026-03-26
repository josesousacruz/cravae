<?php

namespace App\Http\Controllers;

use App\Http\Requests\CriarPedidoCheckoutRequest;
use App\Models\PedidoCheckout;
use App\Services\ServicoCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedidoCheckoutController extends Controller
{
    public function __construct(
        private readonly ServicoCheckout $servicoCheckout,
    ) {
    }

    public function store(CriarPedidoCheckoutRequest $request): JsonResponse
    {
        $pedido = $this->servicoCheckout->criarPedido(
            $request->user(),
            $request->filled('valor') ? (float) $request->input('valor') : null,
        );

        return response()->json([
            'pedido' => $pedido,
        ], 201);
    }

    public function simularPagamento(Request $request, PedidoCheckout $pedidoCheckout): JsonResponse
    {
        abort_unless($pedidoCheckout->usuario_id === $request->user()->id, 403);

        $cupom = $this->servicoCheckout->simularPagamento($pedidoCheckout);

        return response()->json([
            'pedido' => $pedidoCheckout->fresh(),
            'cupom' => $cupom,
        ]);
    }
}
