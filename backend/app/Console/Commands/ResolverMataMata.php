<?php

namespace App\Console\Commands;

use App\Models\Torneio;
use App\Services\ServicoMataMata;
use Illuminate\Console\Command;

/**
 * Persiste os participantes reais dos jogos de mata-mata em todos os torneios
 * publicados, para que o pipeline de vinculação/sincronização case por par de times.
 *
 *   php artisan jogos:resolver-mata-mata
 */
class ResolverMataMata extends Command
{
    protected $signature = 'jogos:resolver-mata-mata';

    protected $description = 'Persiste os participantes reais do mata-mata nas linhas de Jogo (todos os torneios).';

    public function handle(ServicoMataMata $servico): int
    {
        $torneios = Torneio::query()->where('status', 'publicado')->get();

        foreach ($torneios as $torneio) {
            $gravados = $servico->persistirParticipantes($torneio);
            $this->line("Torneio {$torneio->id} ({$torneio->edicao}): {$gravados} confrontos atualizados.");
        }

        return self::SUCCESS;
    }
}
