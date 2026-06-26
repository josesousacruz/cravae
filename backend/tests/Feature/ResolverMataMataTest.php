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

    public function test_bolao_com_grupos_persiste_participantes_por_derivacao(): void
    {
        $this->seed(TorneioMockadoSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026')->firstOrFail();

        $faseGrupos = Fase::query()->where('torneio_id', $torneio->id)->where('tipo', 'grupos')->firstOrFail();
        foreach (Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $faseGrupos->id)->get() as $jogo) {
            ResultadoJogo::query()->create([
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 0,
                'selecao_classificada_id' => null,
                'encerrado_at' => now(),
            ]);
        }

        app(ServicoMataMata::class)->persistirParticipantes($torneio->fresh());

        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();
        $jogosR32 = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $r32->id)->get();
        $this->assertTrue(
            $jogosR32->contains(fn (Jogo $j) => $j->selecao_mandante_id !== null && $j->selecao_visitante_id !== null),
            'ao menos um jogo do Round of 32 deve ter participantes persistidos'
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
