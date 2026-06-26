<?php

namespace Tests\Feature;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Services\ServicoMataMata;
use Database\Seeders\BolaoMataMataSeeder;
use Database\Seeders\TorneioMockadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolverMataMataTest extends TestCase
{
    use RefreshDatabase;

    public function test_bolao_principal_tambem_herda_o_mata_mata_da_api(): void
    {
        // O bolão principal (com grupos) NÃO depende mais da derivação: o mata-mata
        // real é espelhado da API, mesmo com a fase de grupos ainda em andamento.
        config(['thesportsdb.rodadas_mata_mata' => [32]]);
        $this->seed(TorneioMockadoSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026')->firstOrFail();

        // r=32: Brasil (134496) x França (133913), ambos seleções do bolão principal.
        $this->eventosTheSportsDb = [
            32 => [['idEvent' => '910001', 'idHomeTeam' => '134496', 'idAwayTeam' => '133913', 'dateEvent' => '2026-06-28']],
        ];

        app(ServicoMataMata::class)->persistirParticipantes($torneio->fresh());

        $bra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'BRA')->firstOrFail();
        $fra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'FRA')->firstOrFail();
        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();

        $this->assertTrue(
            Jogo::query()->where('fase_id', $r32->id)
                ->where('selecao_mandante_id', $bra->id)
                ->where('selecao_visitante_id', $fra->id)
                ->exists(),
            'o R32 do bolão principal deve ser preenchido a partir da API, sem grupos completos'
        );
    }

    public function test_2o_bolao_preenche_confrontos_direto_da_api_por_rodada(): void
    {
        config(['thesportsdb.rodadas_mata_mata' => [32]]);
        $this->seed(BolaoMataMataSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();

        // r=32: Brazil (134496) x Japan (134503), confronto real publicado pela API.
        $this->eventosTheSportsDb = [
            32 => [['idEvent' => '900100', 'idHomeTeam' => '134496', 'idAwayTeam' => '134503', 'dateEvent' => '2026-06-29']],
        ];

        app(ServicoMataMata::class)->persistirParticipantes($torneio->fresh());

        $bra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'BRA')->firstOrFail();
        $jpn = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'JPN')->firstOrFail();
        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();

        $this->assertTrue(
            Jogo::query()->where('fase_id', $r32->id)
                ->where('selecao_mandante_id', $bra->id)
                ->where('selecao_visitante_id', $jpn->id)
                ->exists(),
            'o confronto BRA x JPN do R32 deve ser preenchido a partir da API'
        );
    }

    public function test_command_resolver_mata_mata_roda_para_todos_os_torneios(): void
    {
        $this->seed();
        $this->artisan('jogos:resolver-mata-mata')->assertExitCode(0);
    }
}
