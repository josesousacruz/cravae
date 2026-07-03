<?php

namespace Tests\Feature;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\Torneio;
use App\Services\ServicoFechamentoApostas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * O palpite de mata-mata deve fechar no horário de Brasília do jogo (data_hora_inicio
 * é wall-clock BRT). Antes do fix fechava ~3h cedo (o app roda em UTC e lia os dígitos
 * como UTC). Jogo às 16:00 BRT = 19:00 UTC.
 */
class FechamentoMataMataBrtTest extends TestCase
{
    use RefreshDatabase;

    public function test_palpite_mata_mata_fecha_no_horario_brt_do_jogo(): void
    {
        $torneio = Torneio::create([
            'nome' => 'Teste Fechamento', 'edicao' => '2026-FX', 'status' => 'publicado',
        ]);
        $fase = Fase::create([
            'torneio_id' => $torneio->id, 'slug' => 'round_of_32',
            'nome' => 'Round of 32', 'ordem' => 1, 'tipo' => 'eliminatoria',
        ]);
        $jogo = Jogo::create([
            'torneio_id' => $torneio->id, 'fase_id' => $fase->id, 'ordem_na_fase' => 1,
            'data_hora_inicio' => '2026-06-28 16:00:00', 'status' => 'agendado',
        ]);

        $servico = app(ServicoFechamentoApostas::class);
        $dados = ['tipo' => 'placar_jogo_eliminatoria', 'jogo_id' => $jogo->id];

        // 13:00 BRT (16:00 UTC): ANTES do fix fechava aqui. Agora deve estar ABERTO.
        Carbon::setTestNow(Carbon::parse('2026-06-28 16:00:00', 'UTC'));
        $this->assertFalse($servico->prazoEncerrado($dados), 'não pode fechar às 13:00 BRT');

        // 15:59 BRT (18:59 UTC): ABERTO (1 min antes do jogo).
        Carbon::setTestNow(Carbon::parse('2026-06-28 18:59:00', 'UTC'));
        $this->assertFalse($servico->prazoEncerrado($dados), 'não pode fechar às 15:59 BRT');

        // 16:00 BRT (19:00 UTC): FECHADO (início do jogo).
        Carbon::setTestNow(Carbon::parse('2026-06-28 19:00:00', 'UTC'));
        $this->assertTrue($servico->prazoEncerrado($dados), 'deveria fechar às 16:00 BRT');

        Carbon::setTestNow();
    }

    /**
     * Todos os jogos do mata-mata de um mesmo dia fecham juntos, no horário de
     * início do PRIMEIRO jogo eliminatório do dia. Jogos de outros dias não são afetados.
     */
    public function test_todos_os_jogos_do_dia_fecham_no_horario_do_primeiro_jogo(): void
    {
        $torneio = Torneio::create([
            'nome' => 'Teste Fechamento Dia', 'edicao' => '2026-FD', 'status' => 'publicado',
        ]);
        $fase = Fase::create([
            'torneio_id' => $torneio->id, 'slug' => 'round_of_32',
            'nome' => 'Round of 32', 'ordem' => 1, 'tipo' => 'eliminatoria',
        ]);
        Jogo::create([
            'torneio_id' => $torneio->id, 'fase_id' => $fase->id, 'ordem_na_fase' => 1,
            'data_hora_inicio' => '2026-06-28 16:00:00', 'status' => 'agendado',
        ]);
        $jogoNoite = Jogo::create([
            'torneio_id' => $torneio->id, 'fase_id' => $fase->id, 'ordem_na_fase' => 2,
            'data_hora_inicio' => '2026-06-28 20:00:00', 'status' => 'agendado',
        ]);
        $jogoDiaSeguinte = Jogo::create([
            'torneio_id' => $torneio->id, 'fase_id' => $fase->id, 'ordem_na_fase' => 3,
            'data_hora_inicio' => '2026-06-29 13:00:00', 'status' => 'agendado',
        ]);

        $servico = app(ServicoFechamentoApostas::class);
        $dadosNoite = ['tipo' => 'placar_jogo_eliminatoria', 'jogo_id' => $jogoNoite->id];
        $dadosDiaSeguinte = ['tipo' => 'placar_jogo_eliminatoria', 'jogo_id' => $jogoDiaSeguinte->id];

        // 15:59 BRT (18:59 UTC): jogo das 20:00 ainda ABERTO.
        Carbon::setTestNow(Carbon::parse('2026-06-28 18:59:00', 'UTC'));
        $this->assertFalse($servico->prazoEncerrado($dadosNoite), 'jogo das 20:00 aberto antes do 1º jogo do dia');

        // 16:00 BRT (19:00 UTC): início do 1º jogo do dia FECHA o jogo das 20:00 também.
        Carbon::setTestNow(Carbon::parse('2026-06-28 19:00:00', 'UTC'));
        $this->assertTrue($servico->prazoEncerrado($dadosNoite), 'jogo das 20:00 fecha junto com o 1º jogo do dia');

        // O jogo do dia seguinte NÃO é afetado pelo fechamento de hoje.
        $this->assertFalse($servico->prazoEncerrado($dadosDiaSeguinte), 'jogo do dia seguinte continua aberto');

        Carbon::setTestNow();
    }
}
