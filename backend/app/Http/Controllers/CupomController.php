<?php

namespace App\Http\Controllers;

use App\Models\Cupom;
use App\Services\ServicoBracketCupom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CupomController extends Controller
{
    public function __construct(
        private readonly ServicoBracketCupom $servicoBracketCupom,
    ) {
    }

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
            'eventosPontuacao' => fn ($query) => $query->latest('id')->with([
                'jogo.selecaoMandante',
                'jogo.selecaoVisitante',
                'jogo.resultado',
            ]),
        ]);

        return response()->json([
            'cupom' => $cupom,
        ]);
    }

    public function bracket(Request $request, Cupom $cupom): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        $cupom->loadMissing('apostas');

        return response()->json([
            'bracket' => $this->servicoBracketCupom->gerar($cupom),
            'resumo' => $this->servicoBracketCupom->resumo($cupom),
        ]);
    }
}
