<?php

namespace Tests\Feature;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\Jogador;
use App\Models\LogAposta;
use App\Models\Rodada;
use App\Models\ResultadoJogo;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoResultadosTorneio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        // Ancora o relogio antes do mata-mata (calendario WC2026 do seed) para que o
        // prazo do palpite de eliminatoria (Round of 32, a partir de 28/06) siga aberto.
        Carbon::setTestNow('2026-06-20 12:00:00');

        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('multi@teste.local');
        $torneio = Torneio::query()->firstOrFail();
        $jogador = Jogador::query()->firstOrFail();
        $jogoGrupos = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();

        // Mantem o teste deterministico mesmo apos o inicio real da Copa: garante
        // que o jogo de grupos e o artilheiro ainda estejam dentro do prazo.
        Rodada::query()->whereKey($jogoGrupos->rodada_id)->update(['data_fechamento' => now()->addDay()]);
        Torneio::query()->whereKey($torneio->id)->update(['data_inicio' => now()->addDay()]);

        // No modelo real, o palpite de eliminatoria so e salvo quando os participantes
        // REAIS do jogo existem -> e necessario lancar resultados reais dos grupos.
        $this->lancarResultadosDeGrupos($torneio);

        // Escolhe um jogo do round_of_32 cujos participantes reais ja existem.
        $participantes = app(ServicoResultadosTorneio::class)->participantesPorJogo($torneio);
        $jogoEliminatoria = Jogo::query()
            ->whereHas('fase', fn ($query) => $query->where('slug', 'round_of_32'))
            ->get()
            ->first(fn (Jogo $jogo) => ($participantes[$jogo->id]['mandante'] ?? null) && ($participantes[$jogo->id]['visitante'] ?? null));
        $this->assertNotNull($jogoEliminatoria, 'Round of 32 deve ter participantes reais apos lancar os grupos.');

        $par = $participantes[$jogoEliminatoria->id];
        // Vitoria do mandante no tempo normal -> classificado e o mandante real.
        $classificadoEsperado = (int) $par['mandante']->id;

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
                    'placar_mandante' => 2,
                    'placar_visitante' => 1,
                ],
            ],
        ])->assertOk();

        $apostaEliminatoria = Aposta::query()
            ->where('cupom_id', $cupom->id)
            ->where('tipo', 'placar_jogo_eliminatoria')
            ->where('jogo_id', $jogoEliminatoria->id)
            ->firstOrFail();

        $this->assertSame(2, $apostaEliminatoria->conteudo['placar_mandante']);
        $this->assertSame($classificadoEsperado, $apostaEliminatoria->conteudo['selecao_classificada_id']);
        $this->assertDatabaseCount('logs_apostas', 3);
    }

    private function lancarResultadosDeGrupos(Torneio $torneio): void
    {
        $jogos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))
            ->whereNotNull('selecao_mandante_id')->whereNotNull('selecao_visitante_id')->get();

        foreach ($jogos as $i => $jogo) {
            ResultadoJogo::query()->updateOrCreate(
                ['jogo_id' => $jogo->id],
                ['placar_mandante' => ($i % 3) + 1, 'placar_visitante' => 0, 'selecao_classificada_id' => null, 'encerrado_at' => now()],
            );
            $jogo->update(['status' => 'encerrado']);
        }
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
