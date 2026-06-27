<?php

namespace App\Console\Commands;

use App\Models\Jogo;
use App\Models\Torneio;
use App\Services\ServicoTheSportsDb;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nível 2 — casa cada Jogo com o idEvent da TheSportsDB, escopado por torneio.
 *
 * Chave de casamento: o par de seleções (por id_externo, independente de quem é
 * mandante/visitante) + a data mais próxima. Itera os torneios que têm referência
 * externa (liga_externa_id) e usa a liga/temporada de cada um. O conjunto de
 * eventos já usados é por torneio (o mesmo idEvent pode valer em bolões distintos
 * que apontam para a mesma Copa).
 *
 * Jogos de mata-mata sem seleções definidas (id_externo nulo) são ignorados;
 * serão casados em execuções futuras, assim que os classificados entrarem
 * (ver jogos:resolver-mata-mata).
 *
 *   php artisan jogos:vincular-eventos
 *   php artisan jogos:vincular-eventos --revincular   (refaz todos)
 */
class VincularEventosJogos extends Command
{
    protected $signature = 'jogos:vincular-eventos
        {--revincular : Reprocessa inclusive jogos já vinculados}';

    protected $description = 'Vincula os jogos do bolão aos eventos correspondentes da TheSportsDB.';

    public function handle(ServicoTheSportsDb $api): int
    {
        $torneios = Torneio::query()->whereNotNull('liga_externa_id')->get();

        if ($torneios->isEmpty()) {
            $this->warn('Nenhum torneio com referência externa (liga_externa_id). Nada a fazer.');

            return self::SUCCESS;
        }

        $total = ['vinculados' => 0, 'sem_evento' => 0, 'sem_selecoes' => 0, 'ja_usado' => 0];

        foreach ($torneios as $torneio) {
            $idLiga = (int) $torneio->liga_externa_id;
            $temporada = $torneio->temporada_externa;

            $jogos = Jogo::query()
                ->where('torneio_id', $torneio->id)
                ->with(['selecaoMandante', 'selecaoVisitante'])
                ->when(! $this->option('revincular'), fn ($q) => $q->whereNull('id_evento_externo'))
                ->get();

            if ($jogos->isEmpty()) {
                continue;
            }

            // Pool de eventos: rodadas de grupos + rodadas de mata-mata da liga/temporada do torneio.
            $eventos = [
                ...$api->eventosDasRodadas(config('thesportsdb.rodadas', []), $temporada, $idLiga),
                ...$api->eventosDeMataMata($temporada, $idLiga),
            ];

            if ($eventos === []) {
                continue;
            }

            // Índice: "menorIdTeam-maiorIdTeam" => lista de eventos com data.
            $indice = [];
            foreach ($eventos as $evento) {
                $casa = (int) ($evento['idHomeTeam'] ?? 0);
                $fora = (int) ($evento['idAwayTeam'] ?? 0);
                if ($casa === 0 || $fora === 0) {
                    continue;
                }
                $indice[$this->chavePar($casa, $fora)][] = $evento;
            }

            // Eventos já usados NESTE torneio (índice único composto torneio_id+id_evento_externo).
            $usados = Jogo::query()
                ->where('torneio_id', $torneio->id)
                ->whereNotNull('id_evento_externo')
                ->pluck('id_evento_externo')
                ->map(fn ($id) => (int) $id)
                ->all();
            $usados = array_flip($usados);

            foreach ($jogos as $jogo) {
                $idCasa = $jogo->selecaoMandante?->id_externo;
                $idFora = $jogo->selecaoVisitante?->id_externo;

                if (! $idCasa || ! $idFora) {
                    $total['sem_selecoes']++;

                    continue;
                }

                $candidatos = $indice[$this->chavePar((int) $idCasa, (int) $idFora)] ?? [];
                $evento = $this->melhorCandidato($candidatos, $jogo->data_hora_inicio);

                if ($evento === null) {
                    $total['sem_evento']++;

                    continue;
                }

                $idEvento = (int) $evento['idEvent'];

                if (isset($usados[$idEvento]) && (int) $jogo->id_evento_externo !== $idEvento) {
                    $total['ja_usado']++;
                    $this->warn("Torneio {$torneio->id} / Jogo {$jogo->id}: evento {$idEvento} já usado por outro jogo.");

                    continue;
                }

                $atualizacao = ['id_evento_externo' => $idEvento];

                // Sincroniza a data real do evento (UTC) como wall-clock de Brasília,
                // convenção usada em todo o app (ver config/calendario_mata_mata.php).
                if ($dataBrt = $this->dataBrtDoEvento($evento)) {
                    $atualizacao['data_hora_inicio'] = $dataBrt;
                }

                $jogo->forceFill($atualizacao)->save();
                $usados[$idEvento] = true;
                $total['vinculados']++;

                $this->line(sprintf(
                    '  [T%d] Jogo %d: %s x %s -> evento %d (%s)',
                    $torneio->id,
                    $jogo->id,
                    $jogo->selecaoMandante->sigla,
                    $jogo->selecaoVisitante->sigla,
                    $idEvento,
                    $evento['dateEvent'] ?? '?',
                ));
            }
        }

        $this->info(sprintf(
            'Vinculados: %d | Sem evento na API: %d | Sem seleções definidas: %d | Evento já usado: %d',
            $total['vinculados'],
            $total['sem_evento'],
            $total['sem_selecoes'],
            $total['ja_usado'],
        ));

        return self::SUCCESS;
    }

    private function chavePar(int $a, int $b): string
    {
        return min($a, $b).'-'.max($a, $b);
    }

    /**
     * Converte a data do evento (TheSportsDB, em UTC) para wall-clock de Brasília
     * (UTC-3), no formato "Y-m-d H:i:s" gravado naive em data_hora_inicio.
     * Usa strTimestamp quando disponível; senão dateEvent + strTime.
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
            return Carbon::parse($iso, 'UTC')
                ->setTimezone('America/Sao_Paulo')
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Escolhe o evento cuja data está mais próxima da data do jogo.
     *
     * @param  array<int,array<string,mixed>>  $candidatos
     */
    private function melhorCandidato(array $candidatos, ?Carbon $dataJogo): ?array
    {
        if ($candidatos === []) {
            return null;
        }

        if (count($candidatos) === 1 || $dataJogo === null) {
            return $candidatos[0];
        }

        usort($candidatos, function (array $x, array $y) use ($dataJogo) {
            $dx = abs(Carbon::parse($x['dateEvent'] ?? '2000-01-01')->diffInDays($dataJogo));
            $dy = abs(Carbon::parse($y['dateEvent'] ?? '2000-01-01')->diffInDays($dataJogo));

            return $dx <=> $dy;
        });

        return $candidatos[0];
    }
}
