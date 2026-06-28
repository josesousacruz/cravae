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
 * Prova que o binding event-driven NÃO deriva conforme a fase é completada: novos
 * confrontos entram nos slots vazios sem remapear os que já estão definidos, e time
 * sempre bate com o vínculo. É o cenário real que quebrava com o espelho posicional
 * (a lista de eventos cresce/reordena por data → índices andavam).
 */
class EventDrivenMataMataFasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_fase_sendo_completada_nao_remapeia_slots_ja_definidos(): void
    {
        $torneio = Torneio::create([
            'nome' => 'Teste Fases', 'edicao' => '2026-F', 'status' => 'publicado',
            'liga_externa_id' => 4429, 'temporada_externa' => '2026',
        ]);
        $fase = Fase::create([
            'torneio_id' => $torneio->id, 'slug' => 'round_of_32',
            'nome' => 'Round of 32', 'ordem' => 1, 'tipo' => 'eliminatoria',
        ]);

        $sel = [];
        foreach (['A' => 2001, 'B' => 2002, 'C' => 2003, 'D' => 2004, 'E' => 2005, 'F' => 2006, 'G' => 2007, 'H' => 2008] as $k => $idExt) {
            $sel[$k] = Selecao::create([
                'torneio_id' => $torneio->id, 'nome' => "Sel $k", 'sigla' => "T$k",
                'slug' => "sel-$k", 'ativo' => true, 'id_externo' => $idExt,
            ]);
        }

        // 4 slots vazios, com datas-âncora crescentes (ordem de preenchimento).
        foreach (range(1, 4) as $i) {
            Jogo::create([
                'torneio_id' => $torneio->id, 'fase_id' => $fase->id, 'ordem_na_fase' => $i,
                'selecao_mandante_id' => null, 'selecao_visitante_id' => null,
                'data_hora_inicio' => sprintf('2026-06-%02d 16:00:00', 27 + $i), 'status' => 'agendado',
            ]);
        }

        $servico = app(ServicoMataMata::class);

        // ── FASE PARCIAL: só 2 confrontos definidos (A×B e C×D) ──
        $this->eventosTheSportsDb = [32 => [
            ['idEvent' => '600', 'idHomeTeam' => '2001', 'idAwayTeam' => '2002', 'dateEvent' => '2026-06-28'],
            ['idEvent' => '601', 'idHomeTeam' => '2003', 'idAwayTeam' => '2004', 'dateEvent' => '2026-06-30'],
        ]];
        $servico->persistirParticipantes($torneio->fresh());

        $slotAB = Jogo::where('id_evento_externo', 600)->firstOrFail();
        $slotCD = Jogo::where('id_evento_externo', 601)->firstOrFail();
        $this->assertSame($sel['A']->id, $slotAB->selecao_mandante_id);
        $this->assertSame($sel['C']->id, $slotCD->selecao_mandante_id);

        // ── MAIS TARDE: entram 2 confrontos (E×F com data NO MEIO, e G×H) ──
        // Com índice posicional isso deslocaria C×D de slot; event-driven não.
        $this->eventosTheSportsDb = [32 => [
            ['idEvent' => '600', 'idHomeTeam' => '2001', 'idAwayTeam' => '2002', 'dateEvent' => '2026-06-28'],
            ['idEvent' => '602', 'idHomeTeam' => '2005', 'idAwayTeam' => '2006', 'dateEvent' => '2026-06-29'],
            ['idEvent' => '601', 'idHomeTeam' => '2003', 'idAwayTeam' => '2004', 'dateEvent' => '2026-06-30'],
            ['idEvent' => '603', 'idHomeTeam' => '2007', 'idAwayTeam' => '2008', 'dateEvent' => '2026-07-01'],
        ]];
        $servico->persistirParticipantes($torneio->fresh());

        // Os 2 confrontos antigos NÃO mudaram de slot nem de vínculo (sem deriva).
        $slotAB->refresh();
        $slotCD->refresh();
        $this->assertSame($sel['A']->id, $slotAB->selecao_mandante_id, 'A×B derivou de slot');
        $this->assertSame($sel['B']->id, $slotAB->selecao_visitante_id);
        $this->assertSame(600, (int) $slotAB->id_evento_externo);
        $this->assertSame($sel['C']->id, $slotCD->selecao_mandante_id, 'C×D derivou de slot');
        $this->assertSame(601, (int) $slotCD->id_evento_externo);

        // Os 2 novos entraram nos slots que faltavam.
        $slotEF = Jogo::where('id_evento_externo', 602)->firstOrFail();
        $slotGH = Jogo::where('id_evento_externo', 603)->firstOrFail();
        $this->assertSame($sel['E']->id, $slotEF->selecao_mandante_id);
        $this->assertSame($sel['G']->id, $slotGH->selecao_mandante_id);

        // Invariante: todo slot vinculado tem time == time do evento (coerência),
        // e nenhum confronto aparece em dois slots.
        $vinculados = Jogo::where('fase_id', $fase->id)->whereNotNull('id_evento_externo')->get();
        $this->assertCount(4, $vinculados);
        $pares = $vinculados->map(fn ($j) => $j->selecao_mandante_id.'-'.$j->selecao_visitante_id);
        $this->assertSame($pares->count(), $pares->unique()->count(), 'confronto duplicado em dois slots');
    }
}
