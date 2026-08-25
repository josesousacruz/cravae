<?php

namespace App\Console\Commands;

use App\Models\Jogador;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Torneio;
use App\Services\ServicoPontuacao;
use App\Services\ServicoTheSportsDb;
use Illuminate\Console\Command;

/**
 * Sincroniza os resultados de uma rodada do Brasileirão: placares via
 * eventsround e autores dos gols via lookuptimeline (um request por jogo
 * encerrado). Ao final recalcula a pontuação do torneio.
 *
 * ATENÇÃO: com chave free a timeline vem cortada (~5 entradas) e os gols
 * ficam incompletos — o comando NÃO grava goleadores parciais nesse caso
 * (grava apenas quando a timeline traz a mesma quantidade de gols do placar).
 */
class SincronizarRodadaBrasileirao extends Command
{
    protected $signature = 'cravae:sincronizar-rodada
        {rodada : Número da rodada (1..38)}
        {--forcar : Rebusca timeline mesmo de jogos que já têm goleadores}';

    protected $description = 'Sincroniza placares e goleadores de uma rodada do Brasileirão';

    public function __construct(
        private readonly ServicoTheSportsDb $servico,
        private readonly ServicoPontuacao $pontuacao,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $idLiga = (int) config('thesportsdb.id_liga_brasileirao');
        $temporada = (string) config('thesportsdb.temporada_brasileirao');
        $rodada = (int) $this->argument('rodada');

        $torneio = Torneio::query()
            ->where('liga_externa_id', $idLiga)
            ->where('temporada_externa', $temporada)
            ->first();

        if (! $torneio) {
            $this->error('Torneio do Brasileirão não encontrado. Rode cravae:importar-brasileirao antes.');

            return self::FAILURE;
        }

        $eventos = collect($this->servico->eventosDaRodada($rodada, $temporada, $idLiga))
            ->keyBy(fn (array $evento) => (int) ($evento['idEvent'] ?? 0));

        $jogos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('rodada', fn ($query) => $query->where('ordem', $rodada))
            ->whereNotNull('id_evento_externo')
            ->with('resultado')
            ->get();

        $placares = 0;
        $timelines = 0;

        foreach ($jogos as $jogo) {
            $evento = $eventos->get((int) $jogo->id_evento_externo);

            if (! $evento || ($evento['strStatus'] ?? '') !== 'FT') {
                continue;
            }

            $placarMandante = (int) $evento['intHomeScore'];
            $placarVisitante = (int) $evento['intAwayScore'];

            $resultado = ResultadoJogo::query()->updateOrCreate(
                ['jogo_id' => $jogo->id],
                [
                    'placar_mandante' => $placarMandante,
                    'placar_visitante' => $placarVisitante,
                    'encerrado_at' => $jogo->resultado?->encerrado_at ?? now(),
                ],
            );
            $jogo->forceFill(['status' => 'encerrado'])->save();
            $placares++;

            $precisaTimeline = $this->option('forcar')
                || $resultado->goleadores_mandante === null
                || $resultado->goleadores_visitante === null;

            $temGols = ($placarMandante + $placarVisitante) > 0;

            if (! $precisaTimeline || ! $temGols) {
                if (! $temGols) {
                    // 0x0: goleadores conhecidos por definição (listas vazias).
                    $resultado->forceFill([
                        'goleadores_mandante' => [],
                        'goleadores_visitante' => [],
                    ])->save();
                }

                continue;
            }

            $gols = $this->golsDaTimeline((int) $jogo->id_evento_externo);

            // Free tier corta a timeline: só persiste se a quantidade de gols
            // bater com o placar (timeline completa). Parcial seria pontuado errado.
            if ($gols !== null
                && count($gols['mandante']) === $placarMandante
                && count($gols['visitante']) === $placarVisitante) {
                $resultado->forceFill([
                    'goleadores_mandante' => $gols['mandante'],
                    'goleadores_visitante' => $gols['visitante'],
                ])->save();
                $timelines++;
            } else {
                $this->warn("  Jogo {$jogo->id} (evento {$jogo->id_evento_externo}): timeline incompleta, goleadores não gravados.");
            }

            usleep(4_000_000);
        }

        $this->info("Rodada {$rodada}: {$placares} placares, {$timelines} jogos com goleadores.");

        $this->pontuacao->recalcularTorneio($torneio->fresh());
        $this->info('Pontuação recalculada.');

        return self::SUCCESS;
    }

    /**
     * Extrai os gols da timeline, separados por lado do placar. Gol contra
     * (strTimelineDetail "Own Goal") conta para o lado adversário do jogador
     * e é marcado com contra=true.
     *
     * @return array{mandante: array<int,array<string,mixed>>, visitante: array<int,array<string,mixed>>}|null
     */
    private function golsDaTimeline(int $idEvento): ?array
    {
        $timeline = $this->servico->timelineDoEvento($idEvento);

        if ($timeline === []) {
            return null;
        }

        $gols = ['mandante' => [], 'visitante' => []];

        $entradas = collect($timeline)
            ->filter(fn (array $item) => ($item['strTimeline'] ?? '') === 'Goal')
            ->sortBy(fn (array $item) => (int) ($item['intTime'] ?? 0))
            ->values();

        foreach ($entradas as $item) {
            $detalhe = (string) ($item['strTimelineDetail'] ?? '');

            if ($detalhe === 'Penalty Missed') {
                continue;
            }

            $contra = $detalhe === 'Own Goal';

            // strHome indica o lado CREDITADO com o gol no placar.
            $lado = ($item['strHome'] ?? '') === 'Yes' ? 'mandante' : 'visitante';

            $jogador = Jogador::query()
                ->where('id_externo', (int) ($item['idPlayer'] ?? 0))
                ->first();

            $gols[$lado][] = [
                'jogador_id' => $jogador?->id,
                'contra' => $contra,
                'id_externo' => (int) ($item['idPlayer'] ?? 0),
                'nome' => $item['strPlayer'] ?? null,
                'minuto' => (int) ($item['intTime'] ?? 0),
            ];
        }

        return $gols;
    }
}
