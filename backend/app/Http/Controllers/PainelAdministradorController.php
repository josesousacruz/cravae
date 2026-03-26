<?php

namespace App\Http\Controllers;

use App\Http\Requests\AtualizarRegraPontuacaoRequest;
use App\Http\Requests\SalvarResultadoJogoRequest;
use App\Http\Requests\SalvarResultadoTorneioRequest;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\ResultadoJogo;
use App\Models\ResultadoTorneio;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoPontuacao;
use Illuminate\Http\JsonResponse;

class PainelAdministradorController extends Controller
{
    public function __construct(
        private readonly ServicoPontuacao $servicoPontuacao,
    ) {
    }

    public function resumo(): JsonResponse
    {
        return response()->json([
            'metricas' => [
                'usuarios' => Usuario::query()->count(),
                'torneios' => Torneio::query()->count(),
                'grupos' => Grupo::query()->count(),
                'selecoes' => Selecao::query()->count(),
                'fases' => Fase::query()->count(),
                'jogos' => Jogo::query()->count(),
                'regras_pontuacao' => RegraPontuacao::query()->count(),
            ],
        ]);
    }

    public function dados(): JsonResponse
    {
        $torneio = Torneio::query()
            ->with([
                'resultadoTorneio',
                'grupos.selecoes.jogadores',
                'fases' => fn ($query) => $query->orderBy('ordem'),
                'fases.rodadas' => fn ($query) => $query->orderBy('ordem'),
                'jogos' => fn ($query) => $query->orderBy('data_hora_inicio'),
                'jogos.fase',
                'jogos.rodada',
                'jogos.grupo',
                'jogos.selecaoMandante',
                'jogos.selecaoVisitante',
                'jogos.resultado',
                'regrasPontuacao' => fn ($query) => $query->orderBy('chave'),
            ])
            ->latest('id')
            ->firstOrFail();

        return response()->json([
            'torneio' => $torneio,
        ]);
    }

    public function salvarResultadoJogo(SalvarResultadoJogoRequest $request, Jogo $jogo): JsonResponse
    {
        $resultado = ResultadoJogo::query()->updateOrCreate(
            ['jogo_id' => $jogo->id],
            [
                'placar_mandante' => $request->integer('placar_mandante'),
                'placar_visitante' => $request->integer('placar_visitante'),
                'selecao_classificada_id' => $request->input('selecao_classificada_id'),
                'encerrado_at' => now(),
            ],
        );

        $jogo->forceFill(['status' => 'encerrado'])->save();
        $this->servicoPontuacao->recalcularTorneio($jogo->torneio()->firstOrFail());

        return response()->json([
            'resultado' => $resultado,
        ]);
    }

    public function salvarResultadoTorneio(SalvarResultadoTorneioRequest $request, Torneio $torneio): JsonResponse
    {
        $resultado = ResultadoTorneio::query()->updateOrCreate(
            ['torneio_id' => $torneio->id],
            $request->validated(),
        );

        $this->servicoPontuacao->recalcularTorneio($torneio);

        return response()->json([
            'resultado_torneio' => $resultado,
        ]);
    }

    public function atualizarRegraPontuacao(AtualizarRegraPontuacaoRequest $request, RegraPontuacao $regraPontuacao): JsonResponse
    {
        $regraPontuacao->fill($request->validated());
        $regraPontuacao->save();

        $this->servicoPontuacao->recalcularTorneio($regraPontuacao->torneio()->firstOrFail());

        return response()->json([
            'regra_pontuacao' => $regraPontuacao,
        ]);
    }
}
