<?php

namespace Tests\Feature;

use App\Services\ServicoTheSportsDb;
use Tests\TestCase;

class EventosMataMataTest extends TestCase
{
    public function test_busca_e_deduplica_eventos_das_rodadas_de_knockout(): void
    {
        config(['thesportsdb.rodadas_mata_mata' => [32, 16]]);

        $this->eventosTheSportsDb = [
            32 => [['idEvent' => '111', 'idHomeTeam' => '136482', 'idAwayTeam' => '140073', 'dateEvent' => '2026-06-28']],
            16 => [['idEvent' => '222', 'idHomeTeam' => '134496', 'idAwayTeam' => '134503', 'dateEvent' => '2026-07-06']],
        ];

        $eventos = app(ServicoTheSportsDb::class)->eventosDeMataMata();

        $ids = array_map(fn ($e) => (int) $e['idEvent'], $eventos);
        sort($ids);
        $this->assertSame([111, 222], $ids);
    }
}
