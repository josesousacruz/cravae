<?php

namespace Tests\Feature;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Fase;
use App\Models\Jogo;
use App\Models\Rodada;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RevelarPalpitesTest extends TestCase
{
    use RefreshDatabase;

    private function jogoComAposta(): array
    {
        $torneio = Torneio::query()->where('edicao', '2026')->firstOrFail();
        $faseGrupos = Fase::query()->where('torneio_id', $torneio->id)->where('tipo', 'grupos')->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $faseGrupos->id)->whereNotNull('rodada_id')->firstOrFail();

        $usuario = Usuario::query()->create([
            'nome' => 'Outro Apostador', 'email' => 'outro@teste.local', 'telefone' => '71999999999',
            'cpf_cnpj' => '12345678901', 'password' => '12345678', 'perfil' => 'usuario',
        ]);
        $cupom = Cupom::query()->create([
            'torneio_id' => $torneio->id, 'usuario_id' => $usuario->id, 'codigo' => 'OUTRO01', 'status' => 'ativo',
        ]);
        Aposta::query()->create([
            'cupom_id' => $cupom->id, 'torneio_id' => $torneio->id, 'jogo_id' => $jogo->id,
            'tipo' => 'placar_jogo_grupos', 'conteudo' => ['placar_mandante' => 2, 'placar_visitante' => 1],
        ]);

        return [$jogo, $usuario];
    }

    public function test_nao_revela_palpite_com_prazo_aberto(): void
    {
        $this->seed();
        [$jogo, $usuario] = $this->jogoComAposta();
        Rodada::query()->whereKey($jogo->rodada_id)->update(['data_fechamento' => now()->addDay()]);

        Sanctum::actingAs($usuario);
        $resp = $this->getJson("/api/jogos/{$jogo->id}/palpiteiros")->assertOk()->json();

        $this->assertFalse($resp['revelado']);
        $this->assertNull($resp['palpiteiros'][0]['palpite']);
    }

    public function test_revela_palpite_com_prazo_fechado(): void
    {
        $this->seed();
        [$jogo, $usuario] = $this->jogoComAposta();
        Rodada::query()->whereKey($jogo->rodada_id)->update(['data_fechamento' => now()->subDay()]);

        Sanctum::actingAs($usuario);
        $resp = $this->getJson("/api/jogos/{$jogo->id}/palpiteiros")->assertOk()->json();

        $this->assertTrue($resp['revelado']);
        $this->assertSame(2, (int) $resp['palpiteiros'][0]['palpite']['placar_mandante']);
        $this->assertSame(1, (int) $resp['palpiteiros'][0]['palpite']['placar_visitante']);
    }
}
