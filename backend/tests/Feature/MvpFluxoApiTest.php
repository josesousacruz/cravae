<?php

namespace Tests\Feature;

use App\Jobs\RecalcularPontuacaoTorneioJob;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\Jogador;
use App\Models\RegraPontuacao;
use App\Models\Rodada;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoResultadosTorneio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
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
        $jogoEliminatoria = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'round_of_32'))->firstOrFail();
        $artilheiro = Jogador::query()->whereHas('selecao', fn ($query) => $query->where('sigla', 'BRA'))->firstOrFail();

        foreach (Jogo::query()->whereHas('fase', fn ($query) => $query->where('tipo', 'grupos'))->get() as $jogo) {
            $this->postJson("/api/cupons/{$cupomResposta['id']}/apostas/lote", [
                'apostas' => [[
                    'tipo' => 'placar_jogo_grupos',
                    'jogo_id' => $jogo->id,
                    'placar_mandante' => 1,
                    'placar_visitante' => 0,
                ]],
            ])->assertOk();
        }

        $this->postJson("/api/cupons/{$cupomResposta['id']}/apostas/lote", [
            'apostas' => [
                [
                    'tipo' => 'placar_jogo_grupos',
                    'jogo_id' => $jogoGrupoA->id,
                    'placar_mandante' => 2,
                    'placar_visitante' => 1,
                ],
                [
                    'tipo' => 'artilheiro',
                    'torneio_id' => $torneio->id,
                    'jogador_id' => $artilheiro->id,
                ],
                [
                    'tipo' => 'placar_jogo_eliminatoria',
                    'jogo_id' => $jogoEliminatoria->id,
                    'placar_mandante' => 2,
                    'placar_visitante' => 1,
                ],
            ],
        ])->assertOk();

        $administrador = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        Sanctum::actingAs($administrador);

        $this->putJson("/api/admin/jogos/{$jogoGrupoA->id}/resultado", [
            'placar_mandante' => 2,
            'placar_visitante' => 1,
        ])->assertOk();

        $this->preencherResultadosReaisDosGrupos($torneio);
        $participantesRoundOf32 = app(ServicoResultadosTorneio::class)->participantesDoJogo($jogoEliminatoria);
        $classificadoReal = $participantesRoundOf32['mandante']?->id;

        $this->putJson("/api/admin/jogos/{$jogoEliminatoria->id}/resultado", [
            'placar_mandante' => 2,
            'placar_visitante' => 1,
            'selecao_classificada_id' => $classificadoReal,
        ])->assertOk();

        $this->putJson("/api/admin/torneios/{$torneio->id}/resultado", [
            'artilheiro_jogador_id' => $artilheiro->id,
        ])->assertOk();

        $ranking = $this->getJson("/api/torneios/{$torneio->id}/ranking")
            ->assertOk()
            ->json('ranking');

        $cupom = Cupom::query()->with('pontuacao')->findOrFail($cupomResposta['id']);

        $this->assertSame('ativo', $cupom->status);
        $this->assertNotNull($cupom->pontuacao);
        $this->assertGreaterThan(0, (float) $cupom->pontuacao->pontuacao_total);
        $this->assertSame($cupom->codigo, $ranking[0]['cupom']['codigo']);
        $this->assertSame($cupom->pontuacao->pontuacao_total, $ranking[0]['pontuacao_total']);
    }

    public function test_admin_despacha_job_ao_salvar_resultados_e_regras(): void
    {
        $this->seed();
        Queue::fake();

        $administrador = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        $torneio = Torneio::query()->firstOrFail();
        $jogoGrupo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('tipo', 'grupos'))->firstOrFail();
        $regra = RegraPontuacao::query()->where('chave', 'campeao')->firstOrFail();
        $artilheiro = Jogador::query()->firstOrFail();

        Sanctum::actingAs($administrador);

        $this->putJson("/api/admin/jogos/{$jogoGrupo->id}/resultado", [
            'placar_mandante' => 1,
            'placar_visitante' => 0,
        ])->assertOk();

        $this->putJson("/api/admin/torneios/{$torneio->id}/resultado", [
            'artilheiro_jogador_id' => $artilheiro->id,
        ])->assertOk();

        $this->putJson("/api/admin/regras-pontuacao/{$regra->id}", [
            'pontos' => 30,
        ])->assertOk();

        Queue::assertPushed(RecalcularPontuacaoTorneioJob::class);
        Queue::assertPushed(RecalcularPontuacaoTorneioJob::class, fn (RecalcularPontuacaoTorneioJob $job) => $job->torneioId === $torneio->id);
    }

    public function test_resultado_admin_recalcula_pontuacao_mesmo_sem_worker_da_fila(): void
    {
        $this->seed();
        Config::set('queue.default', 'database');

        $usuario = Usuario::query()->create([
            'nome' => 'Jogador Sem Worker',
            'email' => 'sem-worker@teste.local',
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

        $jogoGrupo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('tipo', 'grupos'))->firstOrFail();

        $this->postJson("/api/cupons/{$cupomResposta['id']}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogoGrupo->id,
                'placar_mandante' => 2,
                'placar_visitante' => 1,
            ]],
        ])->assertOk();

        $administrador = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        Sanctum::actingAs($administrador);

        $this->putJson("/api/admin/jogos/{$jogoGrupo->id}/resultado", [
            'placar_mandante' => 2,
            'placar_visitante' => 1,
        ])->assertOk();

        $cupom = Cupom::query()->with(['pontuacao', 'eventosPontuacao'])->findOrFail($cupomResposta['id']);

        $this->assertNotNull($cupom->pontuacao);
        $this->assertGreaterThan(0, (float) $cupom->pontuacao->pontuacao_total);
        $this->assertGreaterThan(0, $cupom->eventosPontuacao->count());
    }

    public function test_admin_rejeita_classificado_que_nao_pertence_ao_confronto_eliminatorio(): void
    {
        $this->seed();

        $administrador = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        $jogoEliminatoria = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'round_of_32'))->firstOrFail();
        $torneio = Torneio::query()->firstOrFail();

        Sanctum::actingAs($administrador);
        $this->preencherResultadosReaisDosGrupos($torneio);

        $participantes = app(ServicoResultadosTorneio::class)->participantesDoJogo($jogoEliminatoria);
        $participantesIds = collect([$participantes['mandante']?->id, $participantes['visitante']?->id])->filter()->values()->all();
        $selecaoInvalida = Selecao::query()
            ->whereNotIn('id', $participantesIds)
            ->firstOrFail();

        $this->putJson("/api/admin/jogos/{$jogoEliminatoria->id}/resultado", [
            'placar_mandante' => 1,
            'placar_visitante' => 0,
            'selecao_classificada_id' => $selecaoInvalida->id,
        ])->assertStatus(422);

        $this->putJson("/api/admin/jogos/{$jogoEliminatoria->id}/resultado", [
            'placar_mandante' => 1,
            'placar_visitante' => 0,
            'selecao_classificada_id' => $participantes['mandante']?->id,
        ])->assertOk();
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

    private function preencherResultadosReaisDosGrupos(Torneio $torneio): void
    {
        foreach (Jogo::query()->where('torneio_id', $torneio->id)->whereHas('fase', fn ($query) => $query->where('tipo', 'grupos'))->get() as $jogo) {
            $jogo->resultado()->firstOrCreate(
                ['jogo_id' => $jogo->id],
                [
                    'placar_mandante' => 1,
                    'placar_visitante' => 0,
                    'selecao_classificada_id' => null,
                    'encerrado_at' => now(),
                ],
            );
        }
    }
}
