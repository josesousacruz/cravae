<?php

namespace App\Console\Commands;

use App\Models\Jogo;
use App\Services\ServicoSincronizacaoResultados;
use App\Services\ServicoTheSportsDb;
use Illuminate\Console\Command;

/**
 * Nível 2 — puxa os resultados encerrados da TheSportsDB e atualiza os jogos
 * já vinculados (id_evento_externo), disparando o recálculo de pontuação.
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
        $jogos = Jogo::query()
            ->with(['fase', 'selecaoMandante', 'selecaoVisitante', 'resultado'])
            ->whereNotNull('id_evento_externo')
            ->get();

        // Jogos vinculados que ainda não têm resultado. A automação só PREENCHE
        // lacunas — nunca sobrescreve um resultado já existente (ex.: lançado
        // manualmente pelo admin), independente do status.
        $pendentes = $jogos->filter(fn ($jogo) => $jogo->status !== 'encerrado' && ! $jogo->resultado);

        if ($pendentes->isEmpty()) {
            $this->info('Nenhum jogo pendente de resultado.');

            return self::SUCCESS;
        }

        // Busca por RODADA (eventsround) — sem teto no plano free.
        $eventos = $api->eventosDasRodadas(config('thesportsdb.rodadas', []));

        if ($eventos === []) {
            $this->error('Nenhum evento retornado pela API (rate-limit/rodadas vazias?). Abortando.');

            return self::FAILURE;
        }

        $porId = [];
        foreach ($eventos as $evento) {
            $porId[(int) ($evento['idEvent'] ?? 0)] = $evento;
        }

        $resumo = [
            'atualizados' => 0,
            'sem_mudanca' => 0,
            'em_andamento' => 0,
            'mata_mata_manual' => 0,
            'sem_dados' => 0,
        ];

        foreach ($pendentes as $jogo) {
            $evento = $porId[(int) $jogo->id_evento_externo] ?? null;

            if ($evento === null) {
                $resumo['sem_dados']++;

                continue;
            }

            $status = strtoupper(trim((string) ($evento['strStatus'] ?? '')));

            if (! in_array($status, self::STATUS_ENCERRADOS, true)) {
                $resumo['em_andamento']++;

                continue;
            }

            $placar = $this->placaresOrientados($jogo, $evento);

            if ($placar === null) {
                $resumo['sem_dados']++;

                continue;
            }

            [$placarMandante, $placarVisitante] = $placar;

            if ($this->resultadoJaIgual($jogo, $placarMandante, $placarVisitante)) {
                $resumo['sem_mudanca']++;

                continue;
            }

            $classificado = $sincronizacao->resolverClassificadoPorPlacar($jogo, $placarMandante, $placarVisitante);

            if (! $classificado['ok']) {
                $resumo['mata_mata_manual']++;
                $this->warn("Jogo {$jogo->id}: mata-mata empatado/indefinido — defina o classificado manualmente.");

                continue;
            }

            $sincronizacao->aplicarResultado($jogo, $placarMandante, $placarVisitante, $classificado['classificado']);
            $resumo['atualizados']++;

            $this->line(sprintf(
                '  Jogo %d: %s %d x %d %s [%s]',
                $jogo->id,
                $jogo->selecaoMandante->sigla,
                $placarMandante,
                $placarVisitante,
                $jogo->selecaoVisitante->sigla,
                $status,
            ));
        }

        $this->info(sprintf(
            'Atualizados: %d | Sem mudança: %d | Em andamento/agendados: %d | Mata-mata p/ admin: %d | Sem dados: %d',
            $resumo['atualizados'],
            $resumo['sem_mudanca'],
            $resumo['em_andamento'],
            $resumo['mata_mata_manual'],
            $resumo['sem_dados'],
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
