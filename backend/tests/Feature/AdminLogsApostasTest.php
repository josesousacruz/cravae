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

class AdminLogsApostasTest extends TestCase
{
    use RefreshDatabase;

    private function prepararCenario(): array
    {
        $this->seed();

        $admin = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        $torneio = Torneio::query()->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->whereNotNull('selecao_mandante_id')->firstOrFail();

        $usuario = Usuario::query()->create([
            'nome' => 'Participante Logs',
            'email' => 'logs@teste.local',
            'telefone' => '71988887777',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        $cupom = Cupom::query()->create([
            'usuario_id' => $usuario->id,
            'torneio_id' => $torneio->id,
            'codigo' => 'LOGTESTE01',
            'status' => 'ativo',
        ]);

        $aposta = $cupom->apostas()->create([
            'tipo' => 'placar_jogo_grupos',
            'torneio_id' => $torneio->id,
            'jogo_id' => $jogo->id,
            'conteudo' => ['placar_mandante' => 2, 'placar_visitante' => 1],
        ]);

        // Edicao real (2x1 -> 3x1), re-save sem mudanca e um log orfao (aposta apagada).
        LogAposta::query()->create([
            'cupom_id' => $cupom->id, 'aposta_id' => $aposta->id, 'usuario_id' => $usuario->id,
            'acao' => 'editada',
            'conteudo_anterior' => ['placar_mandante' => 2, 'placar_visitante' => 1],
            'conteudo_novo' => ['placar_mandante' => 3, 'placar_visitante' => 1],
            'created_at' => '2026-07-02 13:00:00', // 10:00 BRT
        ]);
        LogAposta::query()->create([
            'cupom_id' => $cupom->id, 'aposta_id' => $aposta->id, 'usuario_id' => $usuario->id,
            'acao' => 'editada',
            'conteudo_anterior' => ['placar_mandante' => 3, 'placar_visitante' => 1],
            'conteudo_novo' => ['placar_mandante' => 3, 'placar_visitante' => 1],
            'created_at' => '2026-07-02 13:05:00',
        ]);
        LogAposta::query()->create([
            'cupom_id' => $cupom->id, 'aposta_id' => null, 'usuario_id' => $usuario->id,
            'acao' => 'removida',
            'conteudo_anterior' => ['placar_mandante' => 1, 'placar_visitante' => 0],
            'conteudo_novo' => null,
            'created_at' => '2026-07-03 12:00:00',
        ]);

        return [$admin, $usuario, $cupom, $jogo];
    }

    public function test_admin_lista_logs_com_filtros(): void
    {
        [$admin, $usuario, , $jogo] = $this->prepararCenario();

        Sanctum::actingAs($admin);

        // Sem filtros: as 3 linhas do cenario aparecem (mais recentes primeiro).
        $resposta = $this->getJson('/api/admin/logs-apostas?usuario_id='.$usuario->id)->assertOk();
        $this->assertSame(3, $resposta->json('total'));
        $this->assertSame('removida', $resposta->json('logs.0.acao'));
        $this->assertTrue($resposta->json('logs.0.orfao'));

        // Apenas mudancas reais: o re-save identico some.
        $resposta = $this->getJson('/api/admin/logs-apostas?usuario_id='.$usuario->id.'&apenas_mudancas=1')->assertOk();
        $this->assertSame(2, $resposta->json('total'));

        // Escopo orfaos: so o palpite apagado.
        $resposta = $this->getJson('/api/admin/logs-apostas?usuario_id='.$usuario->id.'&escopo=orfaos')->assertOk();
        $this->assertSame(1, $resposta->json('total'));
        $this->assertSame('removida', $resposta->json('logs.0.acao'));
        $this->assertNull($resposta->json('logs.0.jogo'));

        // Filtro por jogo: so as linhas vinculadas aquele jogo.
        $resposta = $this->getJson('/api/admin/logs-apostas?jogo_id='.$jogo->id)->assertOk();
        $this->assertSame(2, $resposta->json('total'));
        $this->assertSame($jogo->id, $resposta->json('logs.0.jogo.id'));

        // Filtro por dia (BRT): 02/07 tem 2 linhas; 03/07 tem 1.
        $resposta = $this->getJson('/api/admin/logs-apostas?usuario_id='.$usuario->id.'&de=2026-07-02&ate=2026-07-02')->assertOk();
        $this->assertSame(2, $resposta->json('total'));
        $resposta = $this->getJson('/api/admin/logs-apostas?usuario_id='.$usuario->id.'&de=2026-07-03&ate=2026-07-03')->assertOk();
        $this->assertSame(1, $resposta->json('total'));

        // Resumo legivel do placar.
        $resposta = $this->getJson('/api/admin/logs-apostas?usuario_id='.$usuario->id.'&apenas_mudancas=1&acao=editada')->assertOk();
        $this->assertSame('2 x 1', $resposta->json('logs.0.resumo_antes'));
        $this->assertSame('3 x 1', $resposta->json('logs.0.resumo_depois'));
    }

    public function test_usuario_comum_nao_acessa_logs_nem_lista_de_usuarios(): void
    {
        [, $usuario] = $this->prepararCenario();

        Sanctum::actingAs($usuario);

        $this->getJson('/api/admin/logs-apostas')->assertForbidden();
        $this->getJson('/api/admin/usuarios')->assertForbidden();
    }
}
