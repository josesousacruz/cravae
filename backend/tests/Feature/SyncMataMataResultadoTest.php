<?php

namespace Tests\Feature;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;
use Database\Seeders\BolaoMataMataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncMataMataResultadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_vincula_e_sincroniza_resultado_de_mata_mata_por_par_de_times(): void
    {
        config(['thesportsdb.rodadas_mata_mata' => [32]]);
        $this->seed(BolaoMataMataSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();

        $bra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'BRA')->firstOrFail();
        $fra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'FRA')->firstOrFail();
        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $r32->id)->orderBy('ordem_na_fase')->firstOrFail();
        $jogo->forceFill(['selecao_mandante_id' => $bra->id, 'selecao_visitante_id' => $fra->id])->save();

        // API r=32: BRA x FRA encerrado 3x1.
        $this->eventosTheSportsDb = [
            32 => [[
                'idEvent' => '900001',
                'idHomeTeam' => (string) $bra->id_externo,
                'idAwayTeam' => (string) $fra->id_externo,
                'intHomeScore' => '3', 'intAwayScore' => '1',
                'strStatus' => 'FT', 'dateEvent' => '2026-06-28',
            ]],
        ];

        $this->artisan('jogos:vincular-eventos')->assertExitCode(0);
        $jogo->refresh();
        $this->assertSame(900001, (int) $jogo->id_evento_externo);

        $this->artisan('jogos:sincronizar-resultados')->assertExitCode(0);
        $jogo->refresh();
        $this->assertSame('encerrado', $jogo->status);
        $this->assertSame(3, (int) $jogo->resultado->placar_mandante);
        $this->assertSame(1, (int) $jogo->resultado->placar_visitante);
        $this->assertSame($bra->id, (int) $jogo->resultado->selecao_classificada_id);
    }

    public function test_sincroniza_mata_mata_decidido_nos_penaltis_pela_api(): void
    {
        config(['thesportsdb.rodadas_mata_mata' => [32]]);
        $this->seed(BolaoMataMataSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();

        $bra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'BRA')->firstOrFail();
        $fra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'FRA')->firstOrFail();
        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $r32->id)->orderBy('ordem_na_fase')->firstOrFail();
        $jogo->forceFill(['selecao_mandante_id' => $bra->id, 'selecao_visitante_id' => $fra->id])->save();

        // API r=32: empate 1x1, decidido nos pênaltis 2x4 (FRA passa). Status AP (After Penalties),
        // com a disputa nos campos intHomeScoreExtra/intAwayScoreExtra.
        $this->eventosTheSportsDb = [
            32 => [[
                'idEvent' => '900002',
                'idHomeTeam' => (string) $bra->id_externo,
                'idAwayTeam' => (string) $fra->id_externo,
                'intHomeScore' => '1', 'intAwayScore' => '1',
                'intHomeScoreExtra' => '2', 'intAwayScoreExtra' => '4',
                'strStatus' => 'AP', 'dateEvent' => '2026-06-28',
            ]],
        ];

        $this->artisan('jogos:vincular-eventos')->assertExitCode(0);
        $this->artisan('jogos:sincronizar-resultados')->assertExitCode(0);

        $jogo->refresh();
        $this->assertSame('encerrado', $jogo->status);
        // O placar gravado é o do tempo normal (1x1); o classificado vem dos pênaltis.
        $this->assertSame(1, (int) $jogo->resultado->placar_mandante);
        $this->assertSame(1, (int) $jogo->resultado->placar_visitante);
        $this->assertSame($fra->id, (int) $jogo->resultado->selecao_classificada_id);
    }
}
