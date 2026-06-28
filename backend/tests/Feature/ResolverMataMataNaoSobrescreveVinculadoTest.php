<?php

namespace Tests\Feature;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Services\ServicoMataMata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garante a regra central da correção: o espelho posicional do mata-mata NUNCA
 * sobrescreve um slot que já tem vínculo (id_evento_externo) — senão recriaria a
 * divergência time≠vínculo. Ele só preenche slots ainda SEM vínculo.
 */
class ResolverMataMataNaoSobrescreveVinculadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_vinculado_nao_e_sobrescrito_e_slot_vazio_e_preenchido(): void
    {
        $torneio = Torneio::create([
            'nome' => 'Teste MM', 'edicao' => '2026-T', 'status' => 'publicado',
            'liga_externa_id' => 4429, 'temporada_externa' => '2026',
        ]);

        $fase = Fase::create([
            'torneio_id' => $torneio->id, 'slug' => 'round_of_32',
            'nome' => 'Round of 32', 'ordem' => 1, 'tipo' => 'eliminatoria',
        ]);

        // 6 seleções com id_externo (A,B já no slot vinculado; C,D no evento que
        // tentaria sobrescrever; E,F no evento do slot vazio).
        $sel = [];
        foreach (['A' => 1001, 'B' => 1002, 'C' => 1003, 'D' => 1004, 'E' => 1005, 'F' => 1006] as $k => $idExt) {
            $sel[$k] = Selecao::create([
                'torneio_id' => $torneio->id, 'nome' => "Sel $k", 'sigla' => "T$k",
                'slug' => "sel-$k", 'ativo' => true, 'id_externo' => $idExt,
            ]);
        }

        // Slot 1: JÁ VINCULADO (evento 999), times A x B, data mais cedo.
        $jogoVinculado = Jogo::create([
            'torneio_id' => $torneio->id, 'fase_id' => $fase->id, 'ordem_na_fase' => 1,
            'selecao_mandante_id' => $sel['A']->id, 'selecao_visitante_id' => $sel['B']->id,
            'data_hora_inicio' => '2026-06-28 16:00:00', 'status' => 'agendado',
            'id_evento_externo' => 999,
        ]);

        // Slot 2: VAZIO (sem times, sem vínculo), data mais tarde.
        $jogoVazio = Jogo::create([
            'torneio_id' => $torneio->id, 'fase_id' => $fase->id, 'ordem_na_fase' => 2,
            'selecao_mandante_id' => null, 'selecao_visitante_id' => null,
            'data_hora_inicio' => '2026-06-29 16:00:00', 'status' => 'agendado',
        ]);

        // API (rodada 32): por posição/data, o evento[0] (C x D) cairia no slot 1
        // (vinculado) e o evento[1] (E x F) no slot 2 (vazio).
        $this->eventosTheSportsDb = [
            32 => [
                ['idEvent' => '500', 'idHomeTeam' => '1003', 'idAwayTeam' => '1004', 'dateEvent' => '2026-06-28'],
                ['idEvent' => '501', 'idHomeTeam' => '1005', 'idAwayTeam' => '1006', 'dateEvent' => '2026-06-29'],
            ],
        ];

        app(ServicoMataMata::class)->persistirParticipantes($torneio->fresh());

        // Slot vinculado: INTOCADO (continua A x B e evento 999) — a regra central.
        $jogoVinculado->refresh();
        $this->assertSame($sel['A']->id, $jogoVinculado->selecao_mandante_id, 'slot vinculado teve o mandante sobrescrito');
        $this->assertSame($sel['B']->id, $jogoVinculado->selecao_visitante_id, 'slot vinculado teve o visitante sobrescrito');
        $this->assertSame(999, (int) $jogoVinculado->id_evento_externo);

        // Slot vazio: PREENCHIDO com E x F (o resolver ainda faz seu trabalho legítimo).
        $jogoVazio->refresh();
        $this->assertSame($sel['E']->id, $jogoVazio->selecao_mandante_id);
        $this->assertSame($sel['F']->id, $jogoVazio->selecao_visitante_id);
    }
}
