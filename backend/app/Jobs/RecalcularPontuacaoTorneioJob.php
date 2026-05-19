<?php

namespace App\Jobs;

use App\Models\Torneio;
use App\Services\ServicoPontuacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalcularPontuacaoTorneioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $torneioId,
    ) {
    }

    public function handle(ServicoPontuacao $servicoPontuacao): void
    {
        $torneio = Torneio::query()->find($this->torneioId);

        if (! $torneio) {
            return;
        }

        $servicoPontuacao->recalcularTorneio($torneio);
    }
}
