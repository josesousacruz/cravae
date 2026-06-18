<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarApostasRequest;
use App\Models\Cupom;
use App\Services\ServicoApostas;
use App\Services\ServicoPontuacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApostaController extends Controller
{
    public function __construct(
        private readonly ServicoApostas $servicoApostas,
        private readonly ServicoPontuacao $servicoPontuacao,
    ) {
    }

    public function index(Request $request, Cupom $cupom): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        return response()->json([
            'apostas' => $cupom->apostas()->with(['jogo', 'grupo', 'jogador', 'selecao'])->get(),
        ]);
    }

    public function salvarLote(SalvarApostasRequest $request, Cupom $cupom): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        $this->servicoApostas->salvarLote($cupom, $request->user(), $request->validated('apostas'));
        $this->servicoPontuacao->recalcularCupom($cupom->fresh('apostas'));

        return response()->json([
            'cupom' => $cupom->fresh(['pontuacao']),
        ]);
    }

    public function removerLote(Request $request, Cupom $cupom): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        $dados = $request->validate([
            'jogos' => ['required', 'array', 'min:1'],
            'jogos.*' => ['integer', 'exists:jogos,id'],
        ]);

        $this->servicoApostas->removerLote($cupom, $request->user(), $dados['jogos']);
        $this->servicoPontuacao->recalcularCupom($cupom->fresh('apostas'));

        return response()->json([
            'cupom' => $cupom->fresh(['pontuacao']),
        ]);
    }
}
