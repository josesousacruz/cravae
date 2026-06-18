<?php

namespace Tests\Feature;

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

    private function criarCupom(): array
    {
        $usuario = Usuario::query()->create([
            'nome' => 'Bracket', 'email' => 'bracket-real@teste.local',
            'telefone' => '71999999999', 'cpf_cnpj' => '12345678901',
            'password' => '12345678', 'perfil' => 'usuario',
        ]);
        Sanctum::actingAs($usuario);
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $pedido = $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])->assertCreated()->json('pedido');
        $cupom = $this->postJson("/api/pedidos-checkout/{$pedido['id']}/confirmar-sandbox", [])->assertOk()->json('cupom');

        return [$usuario, Cupom::query()->findOrFail($cupom['id']), $torneio];
    }

    public function test_bracket_lista_jogos_eliminatorios_com_palpite_e_podio(): void
    {
        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom();

        Sanctum::actingAs($usuario);
        $resp = $this->getJson("/api/cupons/{$cupom->id}/bracket")->assertOk();

        $resp->assertJsonStructure([
            'bracket' => [['id', 'fase' => ['slug', 'nome'], 'selecao_mandante', 'selecao_visitante', 'palpite']],
            'resumo' => ['podio_palpite', 'podio_real'],
        ]);
    }

    public function test_aposta_eliminatoria_so_salva_quando_participantes_reais_existem(): void
    {
        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom();

        // Sem resultados de grupos: round_of_32 sem participantes reais -> item ignorado.
        $jogoR32 = Jogo::query()->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('slug', 'round_of_32'))
            ->orderBy('ordem_na_fase')->firstOrFail();

        Sanctum::actingAs($usuario);
        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria', 'jogo_id' => $jogoR32->id,
                'placar_mandante' => 1, 'placar_visitante' => 0,
            ]],
        ])->assertOk();

        $this->assertDatabaseMissing('apostas', [
            'cupom_id' => $cupom->id, 'jogo_id' => $jogoR32->id,
        ]);
    }
}
