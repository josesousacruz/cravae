<?php

namespace Tests\Feature;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MataMataRealidadeTest extends TestCase
{
    use RefreshDatabase;

    private function criarCupom(string $email): array
    {
        $usuario = Usuario::query()->create([
            'nome' => 'U '.strtok($email, '@'), 'email' => $email,
            'telefone' => '71999999999', 'cpf_cnpj' => '12345678901',
            'password' => '12345678', 'perfil' => 'usuario',
        ]);
        Sanctum::actingAs($usuario);
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $pedido = $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])->assertCreated()->json('pedido');
        $cupom = $this->postJson("/api/pedidos-checkout/{$pedido['id']}/confirmar-sandbox", [])->assertOk()->json('cupom');

        return [$usuario, Cupom::query()->findOrFail($cupom['id']), $torneio];
    }

    public function test_palpite_eliminatoria_usa_participantes_reais_sem_exigir_grupos(): void
    {
        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom('real@teste.local');

        $this->lancarResultadosDeGrupos($torneio);

        $jogoR32 = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('slug', 'round_of_32'))
            ->orderBy('ordem_na_fase')
            ->firstOrFail();

        Sanctum::actingAs($usuario);
        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogoR32->id,
                'placar_mandante' => 2,
                'placar_visitante' => 1,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('apostas', [
            'cupom_id' => $cupom->id,
            'tipo' => 'placar_jogo_eliminatoria',
            'jogo_id' => $jogoR32->id,
        ]);
        $aposta = Aposta::query()->where('cupom_id', $cupom->id)->where('jogo_id', $jogoR32->id)->firstOrFail();
        $this->assertNotNull($aposta->conteudo['selecao_classificada_id']);
    }

    private function lancarResultadosDeGrupos(Torneio $torneio): void
    {
        $jogos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))
            ->whereNotNull('selecao_mandante_id')
            ->whereNotNull('selecao_visitante_id')
            ->get();

        foreach ($jogos as $i => $jogo) {
            $m = ($i % 3) + 1;
            ResultadoJogo::query()->updateOrCreate(
                ['jogo_id' => $jogo->id],
                ['placar_mandante' => $m, 'placar_visitante' => 0, 'selecao_classificada_id' => null, 'encerrado_at' => now()],
            );
            $jogo->update(['status' => 'encerrado']);
        }
    }
}
