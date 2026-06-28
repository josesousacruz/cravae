<?php

namespace App\Services;

use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;
use Illuminate\Support\Carbon;

/**
 * Preenche os jogos de mata-mata a partir da TheSportsDB, fase a fase
 * (eventsround por código de rodada). É EVENT-DRIVEN: itera os eventos da rodada e,
 * para cada um, grava no slot TIME + DATA + VÍNCULO juntos, todos do MESMO evento.
 *
 * Por que event-driven (e não por índice): o espelho posicional antigo casava time
 * por posição e vínculo por par — duas fontes que divergiam quando a lista de eventos
 * mudava no tempo (slot exibindo um confronto e vinculado ao evento de outro). Aqui
 * a identidade é o idEvent: enquanto a fase é completada (R16 → quartas → semi), cada
 * confronto entra no seu slot e não "anda".
 *
 * Regras:
 *  - Slot já vinculado (id_evento_externo) é INTOCÁVEL (o vínculo manda; o admin
 *    corrige exceções pelo painel).
 *  - Um confronto nunca ocupa dois slots (dedup por par).
 *  - Eventos novos vão para slots vazios ("A definir") antes de sobrescrever fantasmas.
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
        // esses eventos para não pegar jogo de grupo num slot de knockout.
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

            $gravados += $this->preencherFase(
                $torneio->jogos->where('fase_id', $fase->id)->sortBy('data_hora_inicio')->values(),
                $this->api->eventosDaRodada($codigoRodada, $temporada, $idLiga),
                $porIdExterno,
                $idsGrupos,
            );
        }

        return $gravados;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Jogo>  $jogos
     * @param  array<int,array<string,mixed>>  $eventosBrutos
     * @param  \Illuminate\Support\Collection<int,Selecao>  $porIdExterno
     * @param  array<int,bool>  $idsGrupos
     */
    private function preencherFase($jogos, array $eventosBrutos, $porIdExterno, array $idsGrupos): int
    {
        // Normaliza os eventos da rodada (resolve os dois times; ignora grupos).
        $eventos = [];
        foreach ($eventosBrutos as $bruto) {
            $idEvento = (int) ($bruto['idEvent'] ?? 0);
            if ($idEvento === 0 || isset($idsGrupos[$idEvento])) {
                continue;
            }
            $mandante = $porIdExterno->get((int) ($bruto['idHomeTeam'] ?? 0));
            $visitante = $porIdExterno->get((int) ($bruto['idAwayTeam'] ?? 0));
            if (! $mandante || ! $visitante) {
                continue;
            }
            $eventos[] = [
                'idEvent' => $idEvento,
                'mandante' => (int) $mandante->id,
                'visitante' => (int) $visitante->id,
                'par' => $this->chavePar((int) $mandante->id, (int) $visitante->id),
                'data' => $this->dataBrtDoEvento($bruto),
            ];
        }

        // O que já está amarrado: idEvents (pular) e pares (dedup — nunca o mesmo
        // confronto em dois slots).
        $idsAmarrados = [];
        $paresAmarrados = [];
        foreach ($jogos as $jogo) {
            if ($jogo->id_evento_externo !== null) {
                $idsAmarrados[(int) $jogo->id_evento_externo] = true;
                $paresAmarrados[$this->chavePar((int) $jogo->selecao_mandante_id, (int) $jogo->selecao_visitante_id)] = true;
            }
        }

        $pendentes = array_values(array_filter(
            $eventos,
            fn ($e) => ! isset($idsAmarrados[$e['idEvent']]) && ! isset($paresAmarrados[$e['par']]),
        ));

        $usados = [];
        $gravados = 0;

        // Passo 1: evento cujo confronto JÁ está num slot sem vínculo → só amarra ali
        // (o time já bate; vira coerente sem mexer no que está certo).
        foreach ($pendentes as $i => $evento) {
            $slot = $jogos->first(fn (Jogo $j) => $j->id_evento_externo === null
                && ! isset($usados[$j->id])
                && $this->chavePar((int) $j->selecao_mandante_id, (int) $j->selecao_visitante_id) === $evento['par']);

            if ($slot) {
                $this->amarrar($slot, $evento);
                $usados[$slot->id] = true;
                $paresAmarrados[$evento['par']] = true;
                unset($pendentes[$i]);
                $gravados++;
            }
        }

        // Passo 2: eventos restantes → slot VAZIO ("A definir") primeiro; senão, qualquer
        // slot sem vínculo (sobrescreve confronto fantasma). Time+data+vínculo do evento.
        foreach ($pendentes as $evento) {
            if (isset($paresAmarrados[$evento['par']])) {
                continue;
            }

            $slot = $jogos->first(fn (Jogo $j) => $j->id_evento_externo === null
                    && ! isset($usados[$j->id])
                    && $j->selecao_mandante_id === null
                    && $j->selecao_visitante_id === null)
                ?? $jogos->first(fn (Jogo $j) => $j->id_evento_externo === null && ! isset($usados[$j->id]));

            if (! $slot) {
                break;
            }

            $this->amarrar($slot, $evento);
            $usados[$slot->id] = true;
            $paresAmarrados[$evento['par']] = true;
            $gravados++;
        }

        return $gravados;
    }

    /**
     * Grava time + data + vínculo no slot, todos do MESMO evento (coerência por
     * construção).
     *
     * @param  array{idEvent:int,mandante:int,visitante:int,par:string,data:?string}  $evento
     */
    private function amarrar(Jogo $jogo, array $evento): void
    {
        $jogo->forceFill([
            'selecao_mandante_id' => $evento['mandante'],
            'selecao_visitante_id' => $evento['visitante'],
            'id_evento_externo' => $evento['idEvent'],
            'data_hora_inicio' => $evento['data'] ?? $jogo->data_hora_inicio,
        ])->save();
    }

    private function chavePar(int $a, int $b): string
    {
        return min($a, $b).'-'.max($a, $b);
    }

    /**
     * Data do evento (UTC) → wall-clock de Brasília "Y-m-d H:i:s".
     *
     * @param  array<string,mixed>  $evento
     */
    private function dataBrtDoEvento(array $evento): ?string
    {
        $iso = $evento['strTimestamp'] ?? null;
        if (! $iso) {
            $dia = $evento['dateEvent'] ?? null;
            if (! $dia) {
                return null;
            }
            $iso = trim($dia.' '.($evento['strTime'] ?? '00:00:00'));
        }

        try {
            return Carbon::parse($iso, 'UTC')->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
