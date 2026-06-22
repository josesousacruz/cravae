<?php

namespace App\Console\Commands;

use App\Jobs\RecalcularPontuacaoTorneioJob;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Torneio;
use App\Services\ServicoSincronizacaoResultados;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remove TODOS os resultados de jogos e zera o pódio/pontuação derivados.
 *
 * NÃO mexe nos vínculos de evento (id_evento_externo) — esses são estruturais.
 * Útil para limpar dados de teste antes de valer.
 *
 *   php artisan jogos:limpar-resultados --force
 */
class LimparResultadosJogos extends Command
{
    protected $signature = 'jogos:limpar-resultados {--force : Pula a confirmação}';

    protected $description = 'Remove todos os resultados de jogos e zera pódio/pontuação (mantém vínculos de evento).';

    public function handle(ServicoSincronizacaoResultados $sincronizacao): int
    {
        $total = ResultadoJogo::query()->count();

        if ($total === 0) {
            $this->info('Nenhum resultado para remover.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Remover {$total} resultados e zerar a pontuação?")) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            ResultadoJogo::query()->delete();
            Jogo::query()->where('status', 'encerrado')->update(['status' => 'agendado']);
        });

        // Recalcula pódio e pontuação (agora sem resultados => tudo zerado).
        Torneio::query()->get()->each(function (Torneio $torneio) use ($sincronizacao): void {
            $sincronizacao->sincronizarResultadoTorneio($torneio);
            RecalcularPontuacaoTorneioJob::dispatchSync($torneio->id);
        });

        $this->info("Removidos {$total} resultados. Pódio e pontuação zerados. Vínculos de evento mantidos.");

        return self::SUCCESS;
    }
}
