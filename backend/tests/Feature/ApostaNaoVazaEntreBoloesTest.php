<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Fase;
use App\Models\Jogo;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoApostas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Defesa contra vazamento de palpite entre bolões: um item cujo jogo pertence a OUTRO
 * torneio (ex.: UI com estado defasado ao navegar entre cupons) não pode ser gravado
 * no cupom — é ignorado.
 */
class ApostaNaoVazaEntreBoloesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lote_ignora_item_de_jogo_de_outro_torneio(): void
    {
        $torneioCupom = Torneio::create(['nome' => 'Bolão A', 'edicao' => '2026-A', 'status' => 'publicado']);
        $torneioJogo = Torneio::create(['nome' => 'Bolão B', 'edicao' => '2026-B', 'status' => 'publicado']);

        $faseB = Fase::create([
            'torneio_id' => $torneioJogo->id, 'slug' => 'fase_de_grupos',
            'nome' => 'Grupos', 'ordem' => 1, 'tipo' => 'grupos',
        ]);
        $jogoB = Jogo::create([
            'torneio_id' => $torneioJogo->id, 'fase_id' => $faseB->id, 'ordem_na_fase' => 1,
            'data_hora_inicio' => '2090-01-01 16:00:00', 'status' => 'agendado',
        ]);

        $usuario = Usuario::create([
            'nome' => 'Apostador', 'email' => 'vaza@teste.local', 'telefone' => '71999999999',
            'cpf_cnpj' => '12345678901', 'password' => '12345678', 'perfil' => 'usuario',
        ]);
        $cupomA = Cupom::create([
            'usuario_id' => $usuario->id, 'codigo' => 'CUPOM-A', 'status' => 'ativo',
            'torneio_id' => $torneioCupom->id,
        ]);

        app(ServicoApostas::class)->salvarLote($cupomA, $usuario, [[
            'tipo' => 'placar_jogo_grupos',
            'jogo_id' => $jogoB->id,
            'placar_mandante' => 1,
            'placar_visitante' => 0,
        ]]);

        // O jogo é de outro torneio → o item é ignorado, nada é gravado no cupom.
        $this->assertDatabaseMissing('apostas', [
            'cupom_id' => $cupomA->id,
            'jogo_id' => $jogoB->id,
        ]);
    }
}
