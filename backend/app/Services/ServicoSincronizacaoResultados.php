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
     * Em mata-mata empatado no tempo normal, usa a disputa de pênaltis quando ela
     * vem da API (placares "extra"). Só devolve ok=false — deixando para o admin —
     * quando nem o placar nem os pênaltis revelam o classificado.
     *
     * @return array{ok:bool,classificado:?int}
     */
    public function resolverClassificadoPorPlacar(
        Jogo $jogo,
        int $placarMandante,
        int $placarVisitante,
        ?int $penaltisMandante = null,
        ?int $penaltisVisitante = null,
    ): array {
        $jogo->loadMissing('fase');

        if ($jogo->fase?->tipo === 'grupos') {
            return ['ok' => true, 'classificado' => null];
        }

        // Preferir os times persistidos no jogo (2º bolão espelha a API direto na
        // linha do jogo). Cai na derivação só quando o jogo ainda não tem times.
        $mandante = $jogo->selecao_mandante_id;
        $visitante = $jogo->selecao_visitante_id;

        if (! $mandante || ! $visitante) {
            $participantes = $this->servicoResultadosTorneio->participantesDoJogo($jogo);
            $mandante = $participantes['mandante']?->id;
            $visitante = $participantes['visitante']?->id;
        }

        if (! $mandante || ! $visitante) {
            return ['ok' => false, 'classificado' => null];
        }

        if ($placarMandante === $placarVisitante) {
            // Empate no tempo normal/prorrogação: decide pela disputa de pênaltis
            // (intHomeScoreExtra/intAwayScoreExtra). Sem pênaltis na API → admin decide.
            if ($penaltisMandante === null || $penaltisVisitante === null || $penaltisMandante === $penaltisVisitante) {
                return ['ok' => false, 'classificado' => null];
            }

            return [
                'ok' => true,
                'classificado' => $penaltisMandante > $penaltisVisitante ? (int) $mandante : (int) $visitante,
            ];
        }

        return [
            'ok' => true,
            'classificado' => $placarMandante > $placarVisitante ? (int) $mandante : (int) $visitante,
        ];
    }
}
