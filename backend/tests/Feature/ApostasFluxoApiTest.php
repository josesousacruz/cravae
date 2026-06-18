<?php

namespace Tests\Feature;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\Jogador;
use App\Models\LogAposta;
use App\Models\Rodada;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoBracketCupom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApostasFluxoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_consegue_criar_e_editar_aposta_no_proprio_cupom_com_log(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('dono@teste.local');
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

        $aposta = Aposta::query()->where('cupom_id', $cupom->id)->firstOrFail();
        $this->assertSame(1, $aposta->conteudo['placar_mandante']);
        $this->assertDatabaseCount('logs_apostas', 1);
        $this->assertSame('criada', LogAposta::query()->firstOrFail()->acao);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 2,
                'placar_visitante' => 1,
            ]],
        ])->assertOk();

        $aposta->refresh();
        $this->assertSame(2, $aposta->conteudo['placar_mandante']);
        $this->assertDatabaseCount('logs_apostas', 2);
        $this->assertDatabaseHas('logs_apostas', [
            'aposta_id' => $aposta->id,
            'acao' => 'editada',
        ]);
    }

    public function test_backend_bloqueia_salvar_aposta_em_cupom_de_outro_usuario(): void
    {
        $this->seed();

        [$dono, $cupom] = $this->criarUsuarioComCupom('dono-outro@teste.local');
        [$intruso] = $this->criarUsuarioComCupom('intruso@teste.local');
        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();

        Sanctum::actingAs($intruso);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 1,
            ]],
        ])->assertForbidden();

        $this->assertDatabaseCount('apostas', 0);
        $this->assertDatabaseCount('logs_apostas', 0);
    }

    public function test_usuario_consegue_salvar_multiplos_tipos_de_aposta_no_mesmo_cupom(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('multi@teste.local');
        $torneio = Torneio::query()->firstOrFail();
        $jogador = Jogador::query()->firstOrFail();
        $jogoGrupos = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();
        $jogoEliminatoria = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'round_of_32'))->firstOrFail();

        // Mantem o teste deterministico mesmo apos o inicio real da Copa: garante
        // que o jogo de grupos e o artilheiro ainda estejam dentro do prazo.
        Rodada::query()->whereKey($jogoGrupos->rodada_id)->update(['data_fechamento' => now()->addDay()]);
        Torneio::query()->whereKey($torneio->id)->update(['data_inicio' => now()->addDay()]);

        foreach (Jogo::query()->whereHas('fase', fn ($query) => $query->where('tipo', 'grupos'))->get() as $jogo) {
            Aposta::query()->create([
                'cupom_id' => $cupom->id,
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

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [
                [
                    'tipo' => 'placar_jogo_grupos',
                    'jogo_id' => $jogoGrupos->id,
                    'placar_mandante' => 2,
                    'placar_visitante' => 1,
                ],
                [
                    'tipo' => 'artilheiro',
                    'torneio_id' => $torneio->id,
                    'jogador_id' => $jogador->id,
                ],
                [
                    'tipo' => 'placar_jogo_eliminatoria',
                    'jogo_id' => $jogoEliminatoria->id,
                    'placar_mandante' => 1,
                    'placar_visitante' => 1,
                    'penal_mandante' => 4,
                    'penal_visitante' => 3,
                ],
            ],
        ])->assertOk();

        $apostaEliminatoria = Aposta::query()
            ->where('cupom_id', $cupom->id)
            ->where('tipo', 'placar_jogo_eliminatoria')
            ->where('jogo_id', $jogoEliminatoria->id)
            ->firstOrFail();
        $participantes = collect(app(ServicoBracketCupom::class)->gerar($cupom))->keyBy('id');
        $classificadoEsperado = $participantes[$jogoEliminatoria->id]['selecao_mandante']['id'] ?? null;

        $this->assertSame(4, $apostaEliminatoria->conteudo['penal_mandante']);
        $this->assertSame($classificadoEsperado, $apostaEliminatoria->conteudo['selecao_classificada_id']);
        $this->assertDatabaseCount('logs_apostas', 3);
    }

    public function test_request_rejeita_payload_invalido_por_tipo_e_recusa_tipos_removidos(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('invalido@teste.local');

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'artilheiro',
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'apostas.0.torneio_id',
                'apostas.0.jogador_id',
            ]);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'campeao',
                'torneio_id' => 1,
                'selecao_id' => 1,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apostas.0.tipo']);
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
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $pedido = $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])->assertCreated()->json('pedido');
        $cupom = $this->postJson("/api/pedidos-checkout/{$pedido['id']}/confirmar-sandbox", [])->assertOk()->json('cupom');

        return [$usuario, Cupom::query()->findOrFail($cupom['id'])];
    }
}
