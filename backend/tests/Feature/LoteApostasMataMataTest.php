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

class LoteApostasMataMataTest extends TestCase
{
    use RefreshDatabase;

    public function test_lote_ignora_eliminatorio_orfao_e_salva_o_resto(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('repro@teste.local');
        $this->preencherTodosOsJogosDeGrupos($cupom);

        Sanctum::actingAs($usuario);

        // Bracket com R32 definido (grupos completos), mas oitavas ainda "A definir".
        $bracket = $this->getJson("/api/cupons/{$cupom->id}/bracket")->assertOk()->json('bracket');

        $oitava = collect($bracket)->firstWhere('fase.slug', 'oitavas_de_final');
        $this->assertNotNull($oitava, 'esperava encontrar uma oitava no bracket');
        $this->assertNull($oitava['selecao_mandante'], 'oitava deveria estar sem participantes definidos');

        // Pega um jogo de grupos AINDA ABERTO (prazo no futuro) para representar uma alteracao
        // valida que o usuario quer salvar.
        $jogoGrupo = Jogo::query()
            ->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))
            ->where('data_hora_inicio', '>', now()->addDay())
            ->orderByDesc('data_hora_inicio')
            ->firstOrFail();

        // Lote = uma alteracao valida de grupo + um palpite de oitava SEM participantes.
        // Simula o auto-save do front que reenvia tudo que tem placar preenchido.
        $resposta = $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [
                ['tipo' => 'placar_jogo_grupos', 'jogo_id' => $jogoGrupo->id, 'placar_mandante' => 5, 'placar_visitante' => 2],
                ['tipo' => 'placar_jogo_eliminatoria', 'jogo_id' => $oitava['id'], 'placar_mandante' => 1, 'placar_visitante' => 0],
            ],
        ]);

        // EXPECTATIVA APOS A CORRECAO: a alteracao de grupo deve persistir (a oitava orfa e ignorada).
        $resposta->assertOk();

        $apostaGrupo = Aposta::query()
            ->where('cupom_id', $cupom->id)
            ->where('tipo', 'placar_jogo_grupos')
            ->where('jogo_id', $jogoGrupo->id)
            ->firstOrFail();

        $this->assertSame(5, $apostaGrupo->conteudo['placar_mandante'], 'a alteracao de grupo foi perdida no rollback');
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
