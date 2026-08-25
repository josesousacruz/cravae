<?php

namespace App\Http\Controllers;

use App\Models\Cupom;
use App\Services\ServicoBracketReal;
use App\Services\ServicoLogsApostas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CupomController extends Controller
{
    public function __construct(
        private readonly ServicoBracketReal $servicoBracketReal,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        // Fase 08: o cupom nasce gratuito — garante o do usuário em cada
        // torneio publicado (cobre contas criadas antes do modelo de assinatura).
        $servicoAssinatura = app(\App\Services\ServicoAssinatura::class);

        foreach (\App\Models\Torneio::query()->where('status', 'publicado')->get() as $torneio) {
            $servicoAssinatura->garantirCupomGratuito($request->user(), $torneio);
        }

        $cupons = Cupom::query()
            ->with(['pedidoCheckout', 'pontuacao', 'torneio:id,nome,edicao,valor_cupom'])
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

        return $this->respostaCupom($cupom);
    }

    /**
     * Fase 09 (Palpite Primeiro): cupom do usuário no torneio principal.
     * Garante o cupom gratuito na primeira chamada e responde com o mesmo
     * payload do show().
     */
    public function meu(Request $request): JsonResponse
    {
        $torneioPrincipal = \App\Models\Torneio::query()
            ->where('status', 'publicado')
            ->where('principal', true)
            ->orderBy('id')
            ->first();

        if (! $torneioPrincipal) {
            return response()->json([
                'mensagem' => 'Nenhum torneio principal publicado no momento.',
            ], 404);
        }

        $cupom = app(\App\Services\ServicoAssinatura::class)
            ->garantirCupomGratuito($request->user(), $torneioPrincipal);

        // O cupom recém-criado só carrega os atributos do insert; recarrega do
        // banco para responder exatamente com a mesma estrutura do show().
        return $this->respostaCupom($cupom->refresh());
    }

    public function bracket(Request $request, Cupom $cupom): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        $cupom->loadMissing('apostas');

        return response()->json([
            'bracket' => $this->servicoBracketReal->gerar($cupom),
            'resumo' => $this->servicoBracketReal->resumo($cupom),
        ]);
    }

    /**
     * Historico de palpites do proprio cupom (auditoria pessoal): mesmo relatorio
     * do admin, porem travado no cupom do usuario autenticado.
     */
    public function logsApostas(Request $request, Cupom $cupom, ServicoLogsApostas $servico): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        $filtros = $request->validate([
            'jogo_id' => ['nullable', 'integer', 'exists:jogos,id'],
            'acao' => ['nullable', Rule::in(['criada', 'editada', 'removida'])],
            'escopo' => ['nullable', Rule::in(['todos', 'orfaos', 'vinculados'])],
            'apenas_mudancas' => ['nullable', 'in:0,1'],
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $filtros['cupom_id'] = $cupom->id;
        $filtros['apenas_mudancas'] = $request->boolean('apenas_mudancas');

        return response()->json($servico->relatorio($filtros));
    }

    /**
     * Payload padrão de um cupom (compartilhado por show() e meu()).
     */
    private function respostaCupom(Cupom $cupom): JsonResponse
    {
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
}
