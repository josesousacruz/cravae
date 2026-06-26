<?php

namespace App\Services;

use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;

/**
 * Persiste os participantes reais dos jogos de mata-mata nas linhas de Jogo
 * (selecao_mandante_id / selecao_visitante_id). Com os times persistidos, o
 * pipeline de vinculação/sincronização casa cada jogo ao evento da TheSportsDB
 * por par de times e traz o resultado.
 *
 * Duas origens conforme o torneio:
 *  - COM fase de grupos (bolão atual): participantes derivados dos resultados
 *    reais (ServicoResultadosTorneio).
 *  - SEM fase de grupos (2º bolão, mata-mata puro): confrontos espelhados direto
 *    da API, fase a fase (eventsround por código de rodada), mapeando idTeam->
 *    id_externo, ordenando por data.
 *
 * Idempotente: só grava quando o par muda; nunca apaga um par já definido.
 */
class ServicoMataMata
{
    /** fase.slug => código de rodada da TheSportsDB (mata-mata). */
    private const CODIGO_RODADA_POR_FASE = [
        'round_of_32' => 32,
        'oitavas_de_final' => 16,
        'quartas_de_final' => 8,
        'semifinais' => 4,
        'final' => 2,
        // 'terceiro_lugar': sem código confiável ainda — fica para o admin.
    ];

    public function __construct(
        private readonly ServicoResultadosTorneio $servicoResultadosTorneio,
        private readonly ServicoTheSportsDb $api,
    ) {
    }

    public function persistirParticipantes(Torneio $torneio): int
    {
        $torneio->loadMissing('fases', 'jogos.fase');

        $temGrupos = $torneio->fases->contains(fn ($fase) => $fase->tipo === 'grupos');

        return $temGrupos
            ? $this->persistirPorDerivacao($torneio)
            : $this->persistirPorApi($torneio);
    }

    private function persistirPorDerivacao(Torneio $torneio): int
    {
        $participantes = $this->servicoResultadosTorneio->participantesPorJogo($torneio);
        $jogos = $torneio->jogos->filter(fn (Jogo $jogo) => $jogo->fase?->tipo !== 'grupos');

        $gravados = 0;
        foreach ($jogos as $jogo) {
            $mandante = $participantes[$jogo->id]['mandante'] ?? null;
            $visitante = $participantes[$jogo->id]['visitante'] ?? null;
            if ($mandante && $visitante && $this->gravarPar($jogo, (int) $mandante->id, (int) $visitante->id)) {
                $gravados++;
            }
        }

        return $gravados;
    }

    private function persistirPorApi(Torneio $torneio): int
    {
        $temporada = $torneio->temporada_externa;
        $idLiga = $torneio->liga_externa_id ? (int) $torneio->liga_externa_id : null;

        $porIdExterno = Selecao::query()
            ->where('torneio_id', $torneio->id)
            ->whereNotNull('id_externo')
            ->get()
            ->keyBy(fn (Selecao $selecao) => (int) $selecao->id_externo);

        // idEvents das rodadas de grupos: alguns códigos de mata-mata colidem com
        // rodadas de grupos (ex.: r=2 = Final e também rodada 2 de grupos). Excluímos
        // esses eventos para o espelho não pegar jogo de grupo num slot de knockout.
        $idsGrupos = [];
        foreach ($this->api->eventosDasRodadas((array) config('thesportsdb.rodadas', []), $temporada, $idLiga) as $evento) {
            $idsGrupos[(int) ($evento['idEvent'] ?? 0)] = true;
        }

        $fases = $torneio->fases->keyBy('slug');
        $gravados = 0;

        foreach (self::CODIGO_RODADA_POR_FASE as $slug => $codigoRodada) {
            $fase = $fases->get($slug);
            if (! $fase) {
                continue;
            }

            $eventos = collect($this->api->eventosDaRodada($codigoRodada, $temporada, $idLiga))
                ->reject(fn ($evento) => isset($idsGrupos[(int) ($evento['idEvent'] ?? 0)]))
                ->sortBy(fn ($evento) => $evento['dateEvent'] ?? '')
                ->values();

            $jogos = $torneio->jogos
                ->where('fase_id', $fase->id)
                ->sortBy('data_hora_inicio')
                ->values();

            foreach ($eventos as $indice => $evento) {
                $jogo = $jogos->get($indice);
                if (! $jogo) {
                    break;
                }

                $mandante = $porIdExterno->get((int) ($evento['idHomeTeam'] ?? 0));
                $visitante = $porIdExterno->get((int) ($evento['idAwayTeam'] ?? 0));

                if ($mandante && $visitante && $this->gravarPar($jogo, (int) $mandante->id, (int) $visitante->id)) {
                    $gravados++;
                }
            }
        }

        return $gravados;
    }

    private function gravarPar(Jogo $jogo, int $mandanteId, int $visitanteId): bool
    {
        $mudou = (int) $jogo->selecao_mandante_id !== $mandanteId
            || (int) $jogo->selecao_visitante_id !== $visitanteId;

        if (! $mudou) {
            return false;
        }

        $jogo->forceFill(['selecao_mandante_id' => $mandanteId, 'selecao_visitante_id' => $visitanteId])->save();

        return true;
    }
}
