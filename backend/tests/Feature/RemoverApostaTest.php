<?php

namespace Tests\Feature;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemoverApostaTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_remove_palpite_de_jogo_aberto(): void
    {
        // Ancora o relogio no meio da fase de grupos (calendario WC2026 do seed) para
        // garantir que exista um jogo de grupo ainda aberto, independente da data real.
        Carbon::setTestNow('2026-06-13 12:00:00');

        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('remover@teste.local');

        // Um jogo de grupo ainda aberto.
        $jogo = Jogo::query()
            ->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))
            ->where('data_hora_inicio', '>', now()->addDay())
            ->orderByDesc('data_hora_inicio')
            ->firstOrFail();

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 2,
                'placar_visitante' => 1,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('apostas', [
            'cupom_id' => $cupom->id,
            'jogo_id' => $jogo->id,
        ]);

        // "Sem palpite": remove o palpite do jogo.
        $this->postJson("/api/cupons/{$cupom->id}/apostas/remover", [
            'jogos' => [$jogo->id],
        ])->assertOk();

        $this->assertDatabaseMissing('apostas', [
            'cupom_id' => $cupom->id,
            'jogo_id' => $jogo->id,
        ]);
    }

    public function test_remocao_respeita_prazo_encerrado(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('remover-fechado@teste.local');

        // Cria o palpite direto (jogo ja iniciado) para simular palpite antigo.
        $jogo = Jogo::query()
            ->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))
            ->where('data_hora_inicio', '<', now())
            ->orderBy('data_hora_inicio')
            ->firstOrFail();

        $cupom->apostas()->create([
            'tipo' => 'placar_jogo_grupos',
            'torneio_id' => $jogo->torneio_id,
            'fase_id' => $jogo->fase_id,
            'rodada_id' => $jogo->rodada_id,
            'grupo_id' => $jogo->grupo_id,
            'jogo_id' => $jogo->id,
            'selecao_id' => null,
            'jogador_id' => null,
            'conteudo' => ['placar_mandante' => 1, 'placar_visitante' => 0, 'penal_mandante' => null, 'penal_visitante' => null, 'selecao_classificada_id' => null],
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/remover", [
            'jogos' => [$jogo->id],
        ])->assertStatus(422);

        $this->assertDatabaseHas('apostas', [
            'cupom_id' => $cupom->id,
            'jogo_id' => $jogo->id,
        ]);
    }

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
