<?php

namespace App\Console\Commands;

use App\Models\Jogo;
use App\Models\Torneio;
use App\Services\ServicoSincronizacaoResultados;
use App\Services\ServicoTheSportsDb;
use Illuminate\Console\Command;

/**
 * Nível 2 — puxa os resultados encerrados da TheSportsDB e atualiza os jogos
 * já vinculados (id_evento_externo), disparando o recálculo de pontuação.
 * Escopado por torneio (cada bolão usa sua liga/temporada).
 *
 * Só age em jogos cujo evento está ENCERRADO na API. Mata-mata empatado (decidido
 * nos pênaltis) é deixado para ajuste manual do admin, pois o placar simples não
 * revela o classificado.
 *
 *   php artisan jogos:sincronizar-resultados
 */
class SincronizarResultadosJogos extends Command
{
    protected $signature = 'jogos:sincronizar-resultados';

    protected $description = 'Sincroniza placares/encerramentos dos jogos vinculados a partir da TheSportsDB.';

    /** Status da API considerados "jogo encerrado". */
    private const STATUS_ENCERRADOS = ['FT', 'AET', 'PEN', 'MATCH FINISHED', 'FINISHED'];

    public function handle(ServicoTheSportsDb $api, ServicoSincronizacaoResultados $sincronizacao): int
    {
        $torneios = Torneio::query()->whereNotNull('liga_externa_id')->get();

        $total = [
            'atualizados' => 0,
            'sem_mudanca' => 0,
            'em_andamento' => 0,
            'mata_mata_manual' => 0,
            'sem_dados' => 0,
        ];

        foreach ($torneios as $torneio) {
            $idLiga = (int) $torneio->liga_externa_id;
            $temporada = $torneio->temporada_externa;

            $pendentes = Jogo::query()
                ->where('torneio_id', $torneio->id)
                ->with(['fase', 'selecaoMandante', 'selecaoVisitante', 'resultado'])
                ->whereNotNull('id_evento_externo')
                ->get()
                // Só PREENCHE lacunas — nunca sobrescreve um resultado já existente.
                ->filter(fn ($jogo) => $jogo->status !== 'encerrado' && ! $jogo->resultado);

            if ($pendentes->isEmpty()) {
                continue;
            }

            $eventos = [
                ...$api->eventosDasRodadas(config('thesportsdb.rodadas', []), $temporada, $idLiga),
                ...$api->eventosDeMataMata($temporada, $idLiga),
            ];

            if ($eventos === []) {
                continue;
            }

            $porId = [];
            foreach ($eventos as $evento) {
                $porId[(int) ($evento['idEvent'] ?? 0)] = $evento;
            }

            foreach ($pendentes as $jogo) {
                $evento = $porId[(int) $jogo->id_evento_externo] ?? null;

                if ($evento === null) {
                    $total['sem_dados']++;

                    continue;
                }

                $status = strtoupper(trim((string) ($evento['strStatus'] ?? '')));

                if (! in_array($status, self::STATUS_ENCERRADOS, true)) {
                    $total['em_andamento']++;

                    continue;
                }

                $placar = $this->placaresOrientados($jogo, $evento);

                if ($placar === null) {
                    $total['sem_dados']++;

                    continue;
                }

                [$placarMandante, $placarVisitante] = $placar;

                if ($this->resultadoJaIgual($jogo, $placarMandante, $placarVisitante)) {
                    $total['sem_mudanca']++;

                    continue;
                }

                $classificado = $sincronizacao->resolverClassificadoPorPlacar($jogo, $placarMandante, $placarVisitante);

                if (! $classificado['ok']) {
                    $total['mata_mata_manual']++;
                    $this->warn("Torneio {$torneio->id} / Jogo {$jogo->id}: mata-mata empatado/indefinido — defina o classificado manualmente.");

                    continue;
                }

                $sincronizacao->aplicarResultado($jogo, $placarMandante, $placarVisitante, $classificado['classificado']);
                $total['atualizados']++;

                $this->line(sprintf(
                    '  [T%d] Jogo %d: %s %d x %d %s [%s]',
                    $torneio->id,
                    $jogo->id,
                    $jogo->selecaoMandante->sigla,
                    $placarMandante,
                    $placarVisitante,
                    $jogo->selecaoVisitante->sigla,
                    $status,
                ));
            }
        }

        $this->info(sprintf(
            'Atualizados: %d | Sem mudança: %d | Em andamento/agendados: %d | Mata-mata p/ admin: %d | Sem dados: %d',
            $total['atualizados'],
            $total['sem_mudanca'],
            $total['em_andamento'],
            $total['mata_mata_manual'],
            $total['sem_dados'],
        ));

        return self::SUCCESS;
    }

    /**
     * Retorna [placarMandante, placarVisitante] orientado ao nosso jogo.
     *
     * O casamento foi por par de seleções, então o "home" da API pode ser o
     * nosso visitante — aqui a gente alinha pelo id_externo do mandante.
     *
     * @param  array<string,mixed>  $evento
     * @return array{0:int,1:int}|null
     */
    private function placaresOrientados(Jogo $jogo, array $evento): ?array
    {
        $golsCasa = $evento['intHomeScore'] ?? null;
        $golsFora = $evento['intAwayScore'] ?? null;

        if ($golsCasa === null || $golsFora === null || $golsCasa === '' || $golsFora === '') {
            return null;
        }

        $golsCasa = (int) $golsCasa;
        $golsFora = (int) $golsFora;

        $idHome = (int) ($evento['idHomeTeam'] ?? 0);
        $mandanteExterno = (int) $jogo->selecaoMandante?->id_externo;

        // Se o "home" da API é o nosso mandante, mantém; senão, inverte.
        return $idHome === $mandanteExterno
            ? [$golsCasa, $golsFora]
            : [$golsFora, $golsCasa];
    }

    private function resultadoJaIgual(Jogo $jogo, int $placarMandante, int $placarVisitante): bool
    {
        $resultado = $jogo->resultado;

        return $jogo->status === 'encerrado'
            && $resultado !== null
            && (int) $resultado->placar_mandante === $placarMandante
            && (int) $resultado->placar_visitante === $placarVisitante;
    }
}
