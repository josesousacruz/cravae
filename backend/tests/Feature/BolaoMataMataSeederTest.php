<?php

namespace Tests\Feature;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\Selecao;
use App\Models\Torneio;
use Database\Seeders\BolaoMataMataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BolaoMataMataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_torneio_mata_mata_com_estrutura_esperada(): void
    {
        $this->seed(BolaoMataMataSeeder::class);

        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();

        // Linkado à Copa real da TheSportsDB.
        $this->assertSame(4429, (int) $torneio->liga_externa_id);
        $this->assertSame('2026', $torneio->temporada_externa);

        // 48 seleções, todas com id_externo, sem grupo.
        $selecoes = Selecao::query()->where('torneio_id', $torneio->id)->get();
        $this->assertCount(48, $selecoes);
        $this->assertTrue($selecoes->every(fn (Selecao $s) => $s->id_externo !== null));
        $this->assertTrue($selecoes->every(fn (Selecao $s) => $s->grupo_id === null));

        // 6 fases de mata-mata, nenhuma de grupos.
        $fases = Fase::query()->where('torneio_id', $torneio->id)->get();
        $this->assertSame(6, $fases->count());
        $this->assertFalse($fases->contains(fn (Fase $f) => $f->tipo === 'grupos'));

        // 32 jogos placeholder (times nulos).
        $jogos = Jogo::query()->where('torneio_id', $torneio->id)->get();
        $this->assertSame(32, $jogos->count());
        $this->assertTrue($jogos->every(fn (Jogo $j) => $j->selecao_mandante_id === null && $j->selecao_visitante_id === null));

        // Regras só de knockout (sem chaves de grupos/artilheiro).
        $chaves = RegraPontuacao::query()->where('torneio_id', $torneio->id)->pluck('chave')->unique();
        $this->assertTrue($chaves->contains('classificado_mata_mata'));
        $this->assertFalse($chaves->contains('placar_exato_fase_grupos'));
        $this->assertFalse($chaves->contains('artilheiro'));
    }
}
