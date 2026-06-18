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

class LoteChainCompletaTest extends TestCase
{
    use RefreshDatabase;

    public function test_um_unico_lote_resolve_cadeia_ate_a_final(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('chain@teste.local');
        $this->preencherTodosOsJogosDeGrupos($cupom);

        Sanctum::actingAs($usuario);

        $bracket = $this->getJson("/api/cupons/{$cupom->id}/bracket")->assertOk()->json('bracket');

        // Monta um lote com TODOS os jogos do mata-mata, mandante vencendo 1x0 em cada um.
        $apostas = collect($bracket)->map(fn ($jogo) => [
            'tipo' => 'placar_jogo_eliminatoria',
            'jogo_id' => $jogo['id'],
            'placar_mandante' => 1,
            'placar_visitante' => 0,
        ])->values()->all();

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", ['apostas' => $apostas])->assertOk();

        $final = collect($bracket)->firstWhere('fase.slug', 'final');
        $this->assertNotNull($final, 'esperava encontrar a final no bracket');

        // A aposta da final precisa ter sido salva no MESMO lote.
        $apostaFinal = Aposta::query()
            ->where('cupom_id', $cupom->id)
            ->where('tipo', 'placar_jogo_eliminatoria')
            ->where('jogo_id', $final['id'])
            ->first();

        $this->assertNotNull($apostaFinal, 'a aposta da final nao foi salva no lote unico');
        $this->assertNotNull($apostaFinal->conteudo['selecao_classificada_id'] ?? null);
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
