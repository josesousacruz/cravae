<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\Jogador;
use App\Models\RegraPontuacao;
use App\Models\Rodada;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MvpFluxoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_consegue_comprar_cupom_apostar_e_entrar_no_ranking(): void
    {
        $this->seed();

        $usuario = Usuario::query()->create([
            'nome' => 'Jogador Teste',
            'email' => 'jogador@teste.local',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $pedido = $this->postJson('/api/pedidos-checkout', [])
            ->assertCreated()
            ->json('pedido');

        $cupomResposta = $this->postJson("/api/pedidos-checkout/{$pedido['id']}/simular-pagamento", [])
            ->assertOk()
            ->json('cupom');

        $torneio = Torneio::query()->firstOrFail();
        $grupoA = $torneio->grupos()->where('nome', 'Grupo A')->firstOrFail();
        $jogoGrupoA = Jogo::query()->where('grupo_id', $grupoA->id)->firstOrFail();
        $semifinal = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'semifinais'))->firstOrFail();
        $brasil = Selecao::query()->where('sigla', 'BRA')->firstOrFail();
        $japao = Selecao::query()->where('sigla', 'JPN')->firstOrFail();
        $franca = Selecao::query()->where('sigla', 'FRA')->firstOrFail();
        $artilheiro = Jogador::query()->whereHas('selecao', fn ($query) => $query->where('sigla', 'BRA'))->firstOrFail();

        $this->postJson("/api/cupons/{$cupomResposta['id']}/apostas/lote", [
            'apostas' => [
                [
                    'tipo' => 'placar_jogo_grupos',
                    'jogo_id' => $jogoGrupoA->id,
                    'placar_mandante' => 2,
                    'placar_visitante' => 1,
                ],
                [
                    'tipo' => 'classificacao_grupo',
                    'torneio_id' => $torneio->id,
                    'grupo_id' => $grupoA->id,
                    'primeiro_colocado_id' => $brasil->id,
                    'segundo_colocado_id' => $japao->id,
                ],
                [
                    'tipo' => 'artilheiro',
                    'torneio_id' => $torneio->id,
                    'jogador_id' => $artilheiro->id,
                ],
                [
                    'tipo' => 'placar_jogo_eliminatoria',
                    'jogo_id' => $semifinal->id,
                    'placar_mandante' => 1,
                    'placar_visitante' => 0,
                    'selecao_classificada_id' => $brasil->id,
                ],
                [
                    'tipo' => 'campeao',
                    'torneio_id' => $torneio->id,
                    'selecao_id' => $brasil->id,
                ],
                [
                    'tipo' => 'vice_campeao',
                    'torneio_id' => $torneio->id,
                    'selecao_id' => $franca->id,
                ],
                [
                    'tipo' => 'terceiro_colocado',
                    'torneio_id' => $torneio->id,
                    'selecao_id' => $japao->id,
                ],
            ],
        ])->assertOk();

        $administrador = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        Sanctum::actingAs($administrador);

        $this->putJson("/api/admin/jogos/{$jogoGrupoA->id}/resultado", [
            'placar_mandante' => 2,
            'placar_visitante' => 1,
        ])->assertOk();

        $this->putJson("/api/admin/jogos/{$semifinal->id}/resultado", [
            'placar_mandante' => 1,
            'placar_visitante' => 0,
            'selecao_classificada_id' => $brasil->id,
        ])->assertOk();

        $this->putJson("/api/admin/torneios/{$torneio->id}/resultado", [
            'campeao_selecao_id' => $brasil->id,
            'vice_campeao_selecao_id' => $franca->id,
            'terceiro_colocado_selecao_id' => $japao->id,
            'artilheiro_jogador_id' => $artilheiro->id,
        ])->assertOk();

        $ranking = $this->getJson("/api/torneios/{$torneio->id}/ranking")
            ->assertOk()
            ->json('ranking');

        $cupom = Cupom::query()->with('pontuacao')->findOrFail($cupomResposta['id']);

        $this->assertSame('ativo', $cupom->status);
        $this->assertSame('112.00', $cupom->pontuacao?->pontuacao_total);
        $this->assertSame($cupom->codigo, $ranking[0]['cupom']['codigo']);
        $this->assertSame('112.00', $ranking[0]['pontuacao_total']);
    }

    public function test_administrador_consegue_atualizar_regra_de_pontuacao(): void
    {
        $this->seed();

        $administrador = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        $regra = RegraPontuacao::query()->where('chave', 'campeao')->firstOrFail();

        Sanctum::actingAs($administrador);

        $this->putJson("/api/admin/regras-pontuacao/{$regra->id}", [
            'pontos' => 30,
        ])->assertOk();

        $this->assertSame(30, $regra->fresh()->pontos);
    }

    public function test_backend_bloqueia_aposta_fora_do_prazo(): void
    {
        $this->seed();

        $usuario = Usuario::query()->create([
            'nome' => 'Jogador Bloqueado',
            'email' => 'bloqueado@teste.local',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $pedido = $this->postJson('/api/pedidos-checkout', [])->json('pedido');
        $cupom = $this->postJson("/api/pedidos-checkout/{$pedido['id']}/simular-pagamento", [])->json('cupom');

        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();

        Rodada::query()->whereKey($jogo->rodada_id)->update([
            'data_fechamento' => now()->subHour(),
        ]);

        $this->postJson("/api/cupons/{$cupom['id']}/apostas/lote", [
            'apostas' => [
                [
                    'tipo' => 'placar_jogo_grupos',
                    'jogo_id' => $jogo->id,
                    'placar_mandante' => 1,
                    'placar_visitante' => 0,
                ],
            ],
        ])->assertStatus(422);
    }
}
