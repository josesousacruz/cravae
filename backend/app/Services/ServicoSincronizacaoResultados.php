<?php

namespace App\Services;

use App\Jobs\RecalcularPontuacaoTorneioJob;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\ResultadoTorneio;
use App\Models\Torneio;
use Illuminate\Support\Carbon;

/**
 * Aplica/remove o resultado de um Jogo de forma centralizada — usado tanto pelo
 * admin (manual) quanto pela sincronização automática com a TheSportsDB.
 *
 * Mantém o mesmo efeito colateral em ambos os caminhos: grava ResultadoJogo,
 * marca o jogo como encerrado, ressincroniza o pódio e dispara o recálculo de
 * pontuação.
 */
class ServicoSincronizacaoResultados
{
    public function __construct(
        private readonly ServicoResultadosTorneio $servicoResultadosTorneio,
    ) {
    }

    public function aplicarResultado(
        Jogo $jogo,
        int $placarMandante,
        int $placarVisitante,
        ?int $classificadoId,
        ?Carbon $encerradoEm = null,
    ): ResultadoJogo {
        $jogo->loadMissing('fase', 'torneio');

        $resultado = ResultadoJogo::query()->updateOrCreate(
            ['jogo_id' => $jogo->id],
            [
                'placar_mandante' => $placarMandante,
                'placar_visitante' => $placarVisitante,
                'selecao_classificada_id' => $classificadoId,
                'encerrado_at' => $encerradoEm ?? now(),
            ],
        );

        $jogo->forceFill(['status' => 'encerrado'])->save();

        $torneio = $jogo->torneio()->firstOrFail();
        $this->sincronizarResultadoTorneio($torneio);
        RecalcularPontuacaoTorneioJob::dispatchAfterResponse($torneio->id);

        return $resultado;
    }

    public function limparResultado(Jogo $jogo): void
    {
        $jogo->loadMissing('torneio');

        ResultadoJogo::query()->where('jogo_id', $jogo->id)->delete();
        $jogo->forceFill(['status' => 'agendado'])->save();

        $torneio = $jogo->torneio()->firstOrFail();
        $this->sincronizarResultadoTorneio($torneio);
        RecalcularPontuacaoTorneioJob::dispatchAfterResponse($torneio->id);
    }

    public function sincronizarResultadoTorneio(Torneio $torneio): void
    {
        $torneio->loadMissing(['jogos.fase', 'jogos.resultado', 'resultadoTorneio']);
        $podio = $this->servicoResultadosTorneio->resolverPodio($torneio);

        ResultadoTorneio::query()->updateOrCreate(
            ['torneio_id' => $torneio->id],
            [
                'campeao_selecao_id' => $podio['campeao_selecao_id'],
                'vice_campeao_selecao_id' => $podio['vice_campeao_selecao_id'],
                'terceiro_colocado_selecao_id' => $podio['terceiro_colocado_selecao_id'],
                'artilheiro_jogador_id' => $torneio->resultadoTorneio?->artilheiro_jogador_id,
            ],
        );
    }

    /**
     * Decide o classificado a partir do placar (caminho automático, sem admin).
     *
     * @return array{ok:bool,classificado:?int}
     *   ok=false quando é mata-mata empatado ou sem participantes definidos —
     *   nesses casos a decisão (pênaltis) fica para o admin.
     */
    public function resolverClassificadoPorPlacar(Jogo $jogo, int $placarMandante, int $placarVisitante): array
    {
        $jogo->loadMissing('fase');

        if ($jogo->fase?->tipo === 'grupos') {
            return ['ok' => true, 'classificado' => null];
        }

        $participantes = $this->servicoResultadosTorneio->participantesDoJogo($jogo);
        $mandante = $participantes['mandante']?->id;
        $visitante = $participantes['visitante']?->id;

        if (! $mandante || ! $visitante) {
            return ['ok' => false, 'classificado' => null];
        }

        if ($placarMandante === $placarVisitante) {
            return ['ok' => false, 'classificado' => null];
        }

        return [
            'ok' => true,
            'classificado' => $placarMandante > $placarVisitante ? (int) $mandante : (int) $visitante,
        ];
    }
}
