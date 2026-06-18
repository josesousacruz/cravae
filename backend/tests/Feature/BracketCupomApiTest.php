<?php

namespace Tests\Feature;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BracketCupomApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_do_bracket_gera_round_of_32_quando_grupos_estao_completos(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('bracket@teste.local');
        $this->preencherTodosOsJogosDeGrupos($cupom);

        Sanctum::actingAs($usuario);

        $resposta = $this->getJson("/api/cupons/{$cupom->id}/bracket")
            ->assertOk();

        $bracket = $resposta->json('bracket');
        $resposta->assertJsonStructure([
            'bracket',
            'resumo' => [
                'campeao_selecao_id',
                'vice_campeao_selecao_id',
                'terceiro_colocado_selecao_id',
            ],
        ]);

        $this->assertCount(32, $bracket);
        $roundOf32 = collect($bracket)->where('fase.slug', 'round_of_32')->values();
        $oitavas = collect($bracket)->where('fase.slug', 'oitavas_de_final')->values();

        $this->assertCount(16, $roundOf32);
        $this->assertCount(8, $oitavas);
        $this->assertFalse($roundOf32[0]['bloqueado']);
        $this->assertNotNull($roundOf32[0]['selecao_mandante']);
        $this->assertNotNull($roundOf32[0]['selecao_visitante']);
        $this->assertTrue($oitavas[0]['bloqueado']);
    }

    public function test_backend_bloqueia_bracket_de_cupom_de_outro_usuario(): void
    {
        $this->seed();

        [$dono, $cupom] = $this->criarUsuarioComCupom('dono-bracket@teste.local');
        [$intruso] = $this->criarUsuarioComCupom('intruso-bracket@teste.local');

        Sanctum::actingAs($intruso);

        $this->getJson("/api/cupons/{$cupom->id}/bracket")
            ->assertForbidden();
    }

    public function test_aposta_eliminatoria_usa_participantes_previstos_do_cupom_para_definir_classificado(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('classificado-bracket@teste.local');
        $this->preencherTodosOsJogosDeGrupos($cupom);

        Sanctum::actingAs($usuario);

        $bracket = $this->getJson("/api/cupons/{$cupom->id}/bracket")
            ->assertOk()
            ->json('bracket');

        $partida = collect($bracket)
            ->where('fase.slug', 'round_of_32')
            ->first(function (array $jogo) {
                return $jogo['selecao_mandante']['id'] !== $jogo['id']
                    && $jogo['selecao_mandante']['id'] !== $jogo['selecao_visitante']['id'];
            });

        $jogoBase = Jogo::query()->findOrFail($partida['id']);
        $classificadoEsperado = $partida['selecao_mandante']['id'];

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogoBase->id,
                'placar_mandante' => 2,
                'placar_visitante' => 1,
            ]],
        ])->assertOk();

        $aposta = Aposta::query()
            ->where('cupom_id', $cupom->id)
            ->where('tipo', 'placar_jogo_eliminatoria')
            ->where('jogo_id', $jogoBase->id)
            ->firstOrFail();

        $this->assertSame($classificadoEsperado, $aposta->conteudo['selecao_classificada_id']);
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
