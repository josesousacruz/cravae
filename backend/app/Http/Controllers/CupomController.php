<?php

namespace App\Http\Controllers;

use App\Models\Cupom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CupomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cupons = Cupom::query()
            ->with(['pedidoCheckout', 'pontuacao'])
            ->where('usuario_id', $request->user()->id)
            ->latest('id')
            ->get();

        return response()->json([
            'cupons' => $cupons,
        ]);
    }

    public function show(Request $request, Cupom $cupom): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        $cupom->load([
            'pedidoCheckout',
            'pontuacao',
            'eventosPontuacao' => fn ($query) => $query->latest('id'),
        ]);

        return response()->json([
            'cupom' => $cupom,
        ]);
    }
}
