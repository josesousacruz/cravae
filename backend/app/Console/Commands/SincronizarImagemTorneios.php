<?php

namespace App\Console\Commands;

use App\Models\Torneio;
use App\Services\ServicoTheSportsDb;
use Illuminate\Console\Command;

/**
 * Preenche a imagem (capa) dos torneios a partir da TheSportsDB (lookupleague).
 *
 *   php artisan torneios:sincronizar-imagem
 *   php artisan torneios:sincronizar-imagem --forcar   (refaz inclusive os que já têm)
 */
class SincronizarImagemTorneios extends Command
{
    protected $signature = 'torneios:sincronizar-imagem {--forcar : Reprocessa inclusive torneios que já têm imagem}';

    protected $description = 'Sincroniza a imagem (capa) dos torneios a partir da TheSportsDB.';

    public function handle(ServicoTheSportsDb $api): int
    {
        $torneios = Torneio::query()
            ->whereNotNull('liga_externa_id')
            ->when(! $this->option('forcar'), fn ($q) => $q->whereNull('imagem_url'))
            ->get();

        foreach ($torneios as $torneio) {
            $url = $api->imagemDaLiga((int) $torneio->liga_externa_id);

            if ($url) {
                $torneio->forceFill(['imagem_url' => $url])->save();
                $this->line("Torneio {$torneio->id} ({$torneio->edicao}): {$url}");
            }
        }

        return self::SUCCESS;
    }
}
