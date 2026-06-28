<?php

namespace App\Services;

use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;

/**
 * Persiste os participantes reais dos jogos de mata-mata nas linhas de Jogo
 * (selecao_mandante_id / selecao_visitante_id), espelhando direto da TheSportsDB,
 * fase a fase (eventsround por código de rodada), mapeando idTeam -> id_externo e
 * ordenando por data. Vale para TODOS os bolões (com ou sem fase de grupos): a
 * fonte da verdade do mata-mata é a API.
 *
 * Com os times persistidos, o pipeline de vinculação/sincronização casa cada jogo
 * ao evento da API por par de times e traz o resultado.
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
        // 'final' e 'terceiro_lugar': códigos colidem com grupos (r=2) / não
        // confiáveis ainda — ficam para o admin até observarmos um código válido.
    ];

    public function __construct(
        private readonly ServicoTheSportsDb $api,
    ) {
    }

    public function persistirParticipantes(Torneio $torneio): int
    {
        $torneio->loadMissing('fases', 'jogos.fase');

        return $this->persistirPorApi($torneio);
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
        // Slot já amarrado a um evento real: os times DEVEM vir desse evento (via
        // vincular-eventos). O espelho posicional não pode sobrescrever — senão recria
        // a divergência time≠vínculo (mesmo confronto em 2 slots / vínculo defasado).
        // Aqui só preenchemos slots ainda SEM vínculo.
        if ($jogo->id_evento_externo !== null) {
            return false;
        }

        $mudou = (int) $jogo->selecao_mandante_id !== $mandanteId
            || (int) $jogo->selecao_visitante_id !== $visitanteId;

        if (! $mudou) {
            return false;
        }

        $jogo->forceFill(['selecao_mandante_id' => $mandanteId, 'selecao_visitante_id' => $visitanteId])->save();

        return true;
    }
}
