<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Grupo;
use App\Models\Jogo;
use App\Models\Rodada;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoBracketCupom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FechamentoApostasTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_bloqueia_aposta_de_grupos_fora_do_prazo_da_rodada(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('grupo-fechado@teste.local');
        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();

        Rodada::query()->whereKey($jogo->rodada_id)->update([
            'data_fechamento' => now()->subMinute(),
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 0,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apostas']);
    }

    public function test_lote_com_jogo_fechado_inalterado_salva_os_jogos_ainda_abertos(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('lote-misto@teste.local');

        $jogoFechado = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();
        $jogoAberto = Jogo::query()
            ->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))
            ->where('rodada_id', '!=', $jogoFechado->rodada_id)
            ->firstOrFail();

        // Palpite ja existente para o jogo cuja rodada vai fechar.
        $cupom->apostas()->create([
            'tipo' => 'placar_jogo_grupos',
            'torneio_id' => $jogoFechado->torneio_id,
            'fase_id' => $jogoFechado->fase_id,
            'rodada_id' => $jogoFechado->rodada_id,
            'grupo_id' => $jogoFechado->grupo_id,
            'jogo_id' => $jogoFechado->id,
            'selecao_id' => null,
            'jogador_id' => null,
            'conteudo' => [
                'placar_mandante' => 2,
                'placar_visitante' => 1,
                'penal_mandante' => null,
                'penal_visitante' => null,
                'selecao_classificada_id' => null,
            ],
        ]);

        Rodada::query()->whereKey($jogoFechado->rodada_id)->update(['data_fechamento' => now()->subMinute()]);
        Rodada::query()->whereKey($jogoAberto->rodada_id)->update(['data_fechamento' => now()->addDay()]);

        Sanctum::actingAs($usuario);

        // O lote reenvia o jogo fechado (mesmo placar) e altera um jogo ainda aberto.
        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [
                [
                    'tipo' => 'placar_jogo_grupos',
                    'jogo_id' => $jogoFechado->id,
                    'placar_mandante' => 2,
                    'placar_visitante' => 1,
                ],
                [
                    'tipo' => 'placar_jogo_grupos',
                    'jogo_id' => $jogoAberto->id,
                    'placar_mandante' => 3,
                    'placar_visitante' => 0,
                ],
            ],
        ])->assertOk();

        $apostaAberta = $cupom->apostas()->where('jogo_id', $jogoAberto->id)->firstOrFail();
        $this->assertSame(3, $apostaAberta->conteudo['placar_mandante']);

        // Jogo fechado permanece com o palpite original.
        $apostaFechada = $cupom->apostas()->where('jogo_id', $jogoFechado->id)->firstOrFail();
        $this->assertSame(2, $apostaFechada->conteudo['placar_mandante']);
    }

    public function test_lote_recusa_alteracao_de_jogo_ja_fechado(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('altera-fechado@teste.local');
        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();

        Rodada::query()->whereKey($jogo->rodada_id)->update(['data_fechamento' => now()->subMinute()]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 4,
                'placar_visitante' => 2,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apostas']);
    }

    public function test_cupom_aguardando_pagamento_consegue_apostar(): void
    {
        $this->seed();

        $usuario = Usuario::query()->create([
            'nome' => 'Pendente Pagamento',
            'email' => 'pendente@teste.local',
            'telefone' => '71999999999',
            'cpf_cnpj' => '12345678901',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        $cupom = Cupom::query()->create([
            'usuario_id' => $usuario->id,
            'codigo' => 'PENDENTE01',
            'status' => 'aguardando_pagamento',
        ]);

        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();
        Rodada::query()->whereKey($jogo->rodada_id)->update(['data_fechamento' => now()->addDay()]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 0,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('apostas', [
            'cupom_id' => $cupom->id,
            'jogo_id' => $jogo->id,
        ]);
    }

    public function test_backend_bloqueia_aposta_de_mata_mata_no_inicio_do_proprio_jogo(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('mata-fechado@teste.local');
        $this->preencherTodosOsJogosDeGrupos($cupom);
        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'round_of_32'))->firstOrFail();
        $jogo->update(['data_hora_inicio' => now()->subMinute()]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 1,
                'penal_mandante' => 5,
                'penal_visitante' => 4,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apostas']);
    }

    public function test_backend_bloqueia_artilheiro_apos_inicio_do_torneio(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('inicio-torneio@teste.local');
        $torneio = Torneio::query()->firstOrFail();
        $grupo = Grupo::query()->firstOrFail();

        Torneio::query()->whereKey($torneio->id)->update([
            'data_inicio' => now()->subMinute(),
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'artilheiro',
                'torneio_id' => $torneio->id,
                'jogador_id' => $grupo->selecoes()->firstOrFail()->jogadores()->firstOrFail()->id,
            ]],
        ])->assertStatus(422);
    }

    public function test_backend_bloqueia_oitavas_antes_de_completar_todos_os_grupos_do_cupom(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('oitavas-bloqueadas@teste.local');
        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'round_of_32'))->firstOrFail();

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 0,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apostas']);
    }

    public function test_backend_libera_proxima_fase_quando_a_fase_anterior_esta_completa_no_cupom(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('fase-liberada@teste.local');
        $this->preencherTodosOsJogosDeGrupos($cupom);
        $this->preencherTodosOsJogosDaFaseEliminatoria($cupom, 'round_of_32');

        $jogoOitavas = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'oitavas_de_final'))->firstOrFail();

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogoOitavas->id,
                'placar_mandante' => 2,
                'placar_visitante' => 1,
            ]],
        ])->assertOk();
    }

    private function preencherTodosOsJogosDeGrupos(Cupom $cupom): void
    {
        foreach (Jogo::query()->whereHas('fase', fn ($query) => $query->where('tipo', 'grupos'))->get() as $jogo) {
            $cupom->apostas()->create([
                'tipo' => 'placar_jogo_grupos',
                'torneio_id' => $jogo->torneio_id,
                'fase_id' => $jogo->fase_id,
                'rodada_id' => $jogo->rodada_id,
                'grupo_id' => $jogo->grupo_id,
                'jogo_id' => $jogo->id,
                'selecao_id' => null,
                'jogador_id' => null,
                'conteudo' => [
                    'placar_mandante' => 1,
                    'placar_visitante' => 0,
                    'penal_mandante' => null,
                    'penal_visitante' => null,
                    'selecao_classificada_id' => null,
                ],
            ]);
        }
    }

    private function preencherTodosOsJogosDaFaseEliminatoria(Cupom $cupom, string $slugFase): void
    {
        $participantesPorJogo = collect(app(ServicoBracketCupom::class)->gerar($cupom))
            ->keyBy('id');

        foreach (Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', $slugFase))->get() as $jogo) {
            $partida = $participantesPorJogo->get($jogo->id);
            $classificadoId = $partida['selecao_mandante']['id'] ?? $jogo->selecao_mandante_id;

            $cupom->apostas()->create([
                'tipo' => 'placar_jogo_eliminatoria',
                'torneio_id' => $jogo->torneio_id,
                'fase_id' => $jogo->fase_id,
                'rodada_id' => null,
                'grupo_id' => null,
                'jogo_id' => $jogo->id,
                'selecao_id' => null,
                'jogador_id' => null,
                'conteudo' => [
                    'placar_mandante' => 1,
                    'placar_visitante' => 0,
                    'penal_mandante' => null,
                    'penal_visitante' => null,
                    'selecao_classificada_id' => $classificadoId,
                ],
            ]);
        }
    }

    /**
     * @return array{0: Usuario, 1: Cupom}
     */
    private function criarUsuarioComCupom(string $email): array
    {
        $usuario = Usuario::query()->create([
            'nome' => 'Usuario '.strtok($email, '@'),
            'email' => $email,
            'telefone' => '71999999999',
            'cpf_cnpj' => '12345678901',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);
        $pedido = $this->postJson('/api/pedidos-checkout', [])->assertCreated()->json('pedido');
        $cupom = $this->postJson("/api/pedidos-checkout/{$pedido['id']}/confirmar-sandbox", [])->assertOk()->json('cupom');

        return [$usuario, Cupom::query()->findOrFail($cupom['id'])];
    }
}
