<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Fase;
use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoBracketReal;
use Database\Seeders\BolaoMataMataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BracketMataMataTest extends TestCase
{
    use RefreshDatabase;

    public function test_bracket_do_2o_bolao_mostra_times_persistidos_do_jogo(): void
    {
        $this->seed(BolaoMataMataSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();

        $bra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'BRA')->firstOrFail();
        $jpn = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'JPN')->firstOrFail();
        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $r32->id)->orderBy('ordem_na_fase')->firstOrFail();
        $jogo->forceFill(['selecao_mandante_id' => $bra->id, 'selecao_visitante_id' => $jpn->id])->save();

        $usuario = Usuario::query()->create([
            'nome' => 'Apostador', 'email' => 'b@teste.local', 'telefone' => '71999999999',
            'cpf_cnpj' => '12345678901', 'password' => '12345678', 'perfil' => 'usuario',
        ]);
        $cupom = Cupom::query()->create([
            'torneio_id' => $torneio->id, 'usuario_id' => $usuario->id, 'codigo' => 'MM-BR-1', 'status' => 'ativo',
        ]);

        $bracket = app(ServicoBracketReal::class)->gerar($cupom->fresh());

        $confronto = collect($bracket)->firstWhere('id', $jogo->id);
        $this->assertNotNull($confronto);
        $this->assertSame($bra->id, $confronto['selecao_mandante']?->id);
        $this->assertSame($jpn->id, $confronto['selecao_visitante']?->id);
        $this->assertCount(32, $bracket, 'bracket do mata-mata deve listar os 32 confrontos');
    }
}
