<?php

namespace Tests\Feature;

use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LimparResultadoJogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_limpa_resultado_de_mata_mata(): void
    {
        $this->seed();

        // Lanca resultado de todos os jogos de grupo (mandante 1x0) para que o R32 tenha participantes.
        foreach (Jogo::query()->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))->get() as $jogo) {
            ResultadoJogo::query()->create([
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 0,
                'selecao_classificada_id' => null,
                'encerrado_at' => now(),
            ]);
        }

        $admin = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        Sanctum::actingAs($admin);

        $dados = $this->getJson('/api/admin/dados')->assertOk()->json('torneio');
        $r32 = collect($dados['jogos'])->first(fn ($j) => ($j['fase']['slug'] ?? null) === 'round_of_32'
            && count($j['participantes_admin'] ?? []) === 2);
        $this->assertNotNull($r32, 'esperava um R32 com participantes definidos');
        $jogoId = $r32['id'];

        // Lanca um resultado decidido (2x1, mandante vence).
        $this->putJson("/api/admin/jogos/{$jogoId}/resultado", [
            'placar_mandante' => 2,
            'placar_visitante' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('resultados_jogos', ['jogo_id' => $jogoId]);
        $this->assertSame('encerrado', Jogo::query()->whereKey($jogoId)->value('status'));

        // ZERAR de verdade: remove o resultado e reabre o jogo.
        $this->deleteJson("/api/admin/jogos/{$jogoId}/resultado")->assertOk();

        $this->assertDatabaseMissing('resultados_jogos', ['jogo_id' => $jogoId]);
        $this->assertSame('agendado', Jogo::query()->whereKey($jogoId)->value('status'));
    }
}
