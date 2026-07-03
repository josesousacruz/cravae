<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\LogAposta;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HistoricoPalpitesCupomTest extends TestCase
{
    use RefreshDatabase;

    private function prepararCenario(): array
    {
        $this->seed();

        $torneio = Torneio::query()->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->whereNotNull('selecao_mandante_id')->firstOrFail();

        $dono = Usuario::query()->create([
            'nome' => 'Dono do Cupom',
            'email' => 'dono-historico@teste.local',
            'telefone' => '71911112222',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);
        $outro = Usuario::query()->create([
            'nome' => 'Outro Usuario',
            'email' => 'outro-historico@teste.local',
            'telefone' => '71933334444',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        $cupom = Cupom::query()->create([
            'usuario_id' => $dono->id,
            'torneio_id' => $torneio->id,
            'codigo' => 'HISTTESTE1',
            'status' => 'ativo',
        ]);

        $aposta = $cupom->apostas()->create([
            'tipo' => 'placar_jogo_grupos',
            'torneio_id' => $torneio->id,
            'jogo_id' => $jogo->id,
            'conteudo' => ['placar_mandante' => 1, 'placar_visitante' => 1],
        ]);

        LogAposta::query()->create([
            'cupom_id' => $cupom->id, 'aposta_id' => $aposta->id, 'usuario_id' => $dono->id,
            'acao' => 'editada',
            'conteudo_anterior' => ['placar_mandante' => 0, 'placar_visitante' => 0],
            'conteudo_novo' => ['placar_mandante' => 1, 'placar_visitante' => 1],
        ]);
        LogAposta::query()->create([
            'cupom_id' => $cupom->id, 'aposta_id' => null, 'usuario_id' => $dono->id,
            'acao' => 'removida',
            'conteudo_anterior' => ['placar_mandante' => 2, 'placar_visitante' => 2],
            'conteudo_novo' => null,
        ]);

        return [$dono, $outro, $cupom];
    }

    public function test_dono_ve_o_historico_do_proprio_cupom(): void
    {
        [$dono, , $cupom] = $this->prepararCenario();

        Sanctum::actingAs($dono);

        $resposta = $this->getJson("/api/cupons/{$cupom->id}/logs-apostas")->assertOk();
        $this->assertSame(2, $resposta->json('total'));

        // Escopo orfaos: so o palpite removido.
        $resposta = $this->getJson("/api/cupons/{$cupom->id}/logs-apostas?escopo=orfaos")->assertOk();
        $this->assertSame(1, $resposta->json('total'));
        $this->assertSame('removida', $resposta->json('logs.0.acao'));
    }

    public function test_outro_usuario_nao_ve_historico_alheio(): void
    {
        [, $outro, $cupom] = $this->prepararCenario();

        Sanctum::actingAs($outro);

        $this->getJson("/api/cupons/{$cupom->id}/logs-apostas")->assertForbidden();
    }
}
