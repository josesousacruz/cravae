<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Grupo;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Rodada;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoFechamentoApostas;
use App\Services\ServicoResultadosTorneio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FechamentoApostasTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_bloqueia_aposta_de_grupos_fora_do_prazo_da_rodada(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('grupo-fechado@teste.local');
        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();

        Rodada::query()->whereKey($jogo->rodada_id)->update([
            'data_fechamento' => now()->subMinute(),
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 0,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apostas']);
    }

    public function test_grupos_fecham_por_dia_uma_hora_antes_do_primeiro_jogo_do_dia(): void
    {
        $this->seed();

        // Sem override de rodada: aplica a regra "por dia, 1h antes do primeiro jogo do dia".
        Rodada::query()->update(['data_fechamento' => null]);

        $jogos = Jogo::query()
            ->whereHas('fase', fn ($query) => $query->where('tipo', 'grupos'))
            ->orderBy('id')
            ->take(2)
            ->get();

        $dia = '2026-08-01';
        Jogo::query()->whereKey($jogos[0]->id)->update(['data_hora_inicio' => "$dia 15:00:00"]);
        Jogo::query()->whereKey($jogos[1]->id)->update(['data_hora_inicio' => "$dia 21:00:00"]);

        $servico = app(ServicoFechamentoApostas::class);
        // Verifica o SEGUNDO jogo (21:00), que ainda nao comecou, mas deve fechar
        // junto com o dia (1h antes do primeiro jogo do dia = 14:00).
        $dadosSegundoJogo = ['tipo' => 'placar_jogo_grupos', 'jogo_id' => $jogos[1]->id];

        Carbon::setTestNow("$dia 13:00:00");
        $this->assertFalse(
            $servico->prazoEncerrado($dadosSegundoJogo),
            'A 2h do primeiro jogo do dia, os palpites do dia devem estar abertos.',
        );

        Carbon::setTestNow("$dia 14:30:00");
        $this->assertTrue(
            $servico->prazoEncerrado($dadosSegundoJogo),
            'A 30min do primeiro jogo do dia, o dia inteiro (inclusive jogos posteriores) deve fechar.',
        );

        Carbon::setTestNow();
    }

    public function test_jogo_de_outra_rodada_no_mesmo_dia_nao_afeta_o_fechamento(): void
    {
        $this->seed();
        Rodada::query()->update(['data_fechamento' => null]);

        $jogoRodada1 = Jogo::query()->whereHas('rodada', fn ($q) => $q->where('ordem', 1))->orderBy('id')->firstOrFail();
        $jogoRodada2 = Jogo::query()->whereHas('rodada', fn ($q) => $q->where('ordem', 2))->orderBy('id')->firstOrFail();

        $dia = '2026-09-01';
        // Rodada 2: primeiro (e unico) jogo do dia as 18:00 -> fecha as 17:00.
        Jogo::query()->whereKey($jogoRodada2->id)->update(['data_hora_inicio' => "$dia 18:00:00"]);
        // Rodada 1 mal-datada para o MESMO dia, bem cedo (09:00) -> NAO deve influenciar.
        Jogo::query()->whereKey($jogoRodada1->id)->update(['data_hora_inicio' => "$dia 09:00:00"]);

        $servico = app(ServicoFechamentoApostas::class);
        $dadosRodada2 = ['tipo' => 'placar_jogo_grupos', 'jogo_id' => $jogoRodada2->id];

        Carbon::setTestNow("$dia 16:30:00");
        $this->assertFalse(
            $servico->prazoEncerrado($dadosRodada2),
            'Um jogo de outra rodada no mesmo dia nao pode fechar os palpites desta rodada.',
        );

        Carbon::setTestNow("$dia 17:30:00");
        $this->assertTrue(
            $servico->prazoEncerrado($dadosRodada2),
            'Deve fechar 1h antes do primeiro jogo DA RODADA naquele dia.',
        );

        Carbon::setTestNow();
    }

    public function test_lote_com_jogo_fechado_inalterado_salva_os_jogos_ainda_abertos(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('lote-misto@teste.local');

        $jogoFechado = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();
        $jogoAberto = Jogo::query()
            ->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))
            ->where('rodada_id', '!=', $jogoFechado->rodada_id)
            ->firstOrFail();

        // Palpite ja existente para o jogo cuja rodada vai fechar.
        $cupom->apostas()->create([
            'tipo' => 'placar_jogo_grupos',
            'torneio_id' => $jogoFechado->torneio_id,
            'fase_id' => $jogoFechado->fase_id,
            'rodada_id' => $jogoFechado->rodada_id,
            'grupo_id' => $jogoFechado->grupo_id,
            'jogo_id' => $jogoFechado->id,
            'selecao_id' => null,
            'jogador_id' => null,
            'conteudo' => [
                'placar_mandante' => 2,
                'placar_visitante' => 1,
                'penal_mandante' => null,
                'penal_visitante' => null,
                'selecao_classificada_id' => null,
            ],
        ]);

        Rodada::query()->whereKey($jogoFechado->rodada_id)->update(['data_fechamento' => now()->subMinute()]);
        Rodada::query()->whereKey($jogoAberto->rodada_id)->update(['data_fechamento' => now()->addDay()]);

        Sanctum::actingAs($usuario);

        // O lote reenvia o jogo fechado (mesmo placar) e altera um jogo ainda aberto.
        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [
                [
                    'tipo' => 'placar_jogo_grupos',
                    'jogo_id' => $jogoFechado->id,
                    'placar_mandante' => 2,
                    'placar_visitante' => 1,
                ],
                [
                    'tipo' => 'placar_jogo_grupos',
                    'jogo_id' => $jogoAberto->id,
                    'placar_mandante' => 3,
                    'placar_visitante' => 0,
                ],
            ],
        ])->assertOk();

        $apostaAberta = $cupom->apostas()->where('jogo_id', $jogoAberto->id)->firstOrFail();
        $this->assertSame(3, $apostaAberta->conteudo['placar_mandante']);

        // Jogo fechado permanece com o palpite original.
        $apostaFechada = $cupom->apostas()->where('jogo_id', $jogoFechado->id)->firstOrFail();
        $this->assertSame(2, $apostaFechada->conteudo['placar_mandante']);
    }

    public function test_lote_recusa_alteracao_de_jogo_ja_fechado(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('altera-fechado@teste.local');
        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();

        Rodada::query()->whereKey($jogo->rodada_id)->update(['data_fechamento' => now()->subMinute()]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 4,
                'placar_visitante' => 2,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apostas']);
    }

    public function test_cupom_aguardando_pagamento_consegue_apostar(): void
    {
        $this->seed();

        $usuario = Usuario::query()->create([
            'nome' => 'Pendente Pagamento',
            'email' => 'pendente@teste.local',
            'telefone' => '71999999999',
            'cpf_cnpj' => '12345678901',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        $cupom = Cupom::query()->create([
            'usuario_id' => $usuario->id,
            'codigo' => 'PENDENTE01',
            'status' => 'aguardando_pagamento',
        ]);

        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'fase_de_grupos'))->firstOrFail();
        Rodada::query()->whereKey($jogo->rodada_id)->update(['data_fechamento' => now()->addDay()]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_grupos',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 0,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('apostas', [
            'cupom_id' => $cupom->id,
            'jogo_id' => $jogo->id,
        ]);
    }

    public function test_backend_bloqueia_aposta_de_mata_mata_no_inicio_do_proprio_jogo(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('mata-fechado@teste.local');
        $torneio = Torneio::query()->firstOrFail();

        // Para o palpite de mata-mata chegar ao prazo (em vez de ser ignorado por falta
        // de participantes reais), os participantes REAIS do jogo precisam existir ->
        // lancamos os resultados reais de todos os jogos de grupos.
        $this->lancarResultadosDeGrupos($torneio);

        $participantes = app(ServicoResultadosTorneio::class)->participantesPorJogo($torneio);
        $jogo = Jogo::query()
            ->whereHas('fase', fn ($query) => $query->where('slug', 'round_of_32'))
            ->get()
            ->first(fn (Jogo $j) => ($participantes[$j->id]['mandante'] ?? null) && ($participantes[$j->id]['visitante'] ?? null));
        $this->assertNotNull($jogo, 'Round of 32 deve ter participantes reais apos lancar os grupos.');

        // No horario de inicio do proprio jogo: o prazo do mata-mata ja se encerrou.
        $jogo->update(['data_hora_inicio' => now()->subMinute()]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 1,
                'penal_mandante' => 5,
                'penal_visitante' => 4,
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['apostas']);
    }

    public function test_backend_bloqueia_artilheiro_apos_inicio_do_torneio(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('inicio-torneio@teste.local');
        $torneio = Torneio::query()->firstOrFail();
        $grupo = Grupo::query()->firstOrFail();

        Torneio::query()->whereKey($torneio->id)->update([
            'data_inicio' => now()->subMinute(),
        ]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'artilheiro',
                'torneio_id' => $torneio->id,
                'jogador_id' => $grupo->selecoes()->firstOrFail()->jogadores()->firstOrFail()->id,
            ]],
        ])->assertStatus(422);
    }

    public function test_backend_ignora_oitavas_antes_de_completar_todos_os_grupos_do_cupom(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('oitavas-bloqueadas@teste.local');
        $jogo = Jogo::query()->whereHas('fase', fn ($query) => $query->where('slug', 'round_of_32'))->firstOrFail();

        Sanctum::actingAs($usuario);

        // Confronto sem participantes reais (resultados de grupos ainda nao lancados):
        // o item e ignorado em vez de derrubar o lote, mas nao deve ser persistido.
        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogo->id,
                'placar_mandante' => 1,
                'placar_visitante' => 0,
            ]],
        ])->assertOk();

        $this->assertDatabaseMissing('apostas', [
            'cupom_id' => $cupom->id,
            'tipo' => 'placar_jogo_eliminatoria',
            'jogo_id' => $jogo->id,
        ]);
    }

    public function test_backend_libera_proxima_fase_quando_os_participantes_reais_existem(): void
    {
        $this->seed();

        [$usuario, $cupom] = $this->criarUsuarioComCupom('fase-liberada@teste.local');
        $torneio = Torneio::query()->firstOrFail();

        // Realidade: oitavas so abre quando os participantes reais existem -> derivam
        // dos resultados reais dos grupos E do round_of_32.
        $this->lancarResultadosDeGrupos($torneio);
        $this->lancarResultadosDaFase($torneio, 'round_of_32');

        $participantes = app(ServicoResultadosTorneio::class)->participantesPorJogo(Torneio::query()->findOrFail($torneio->id));
        $jogoOitavas = Jogo::query()
            ->whereHas('fase', fn ($query) => $query->where('slug', 'oitavas_de_final'))
            ->get()
            ->first(fn (Jogo $j) => ($participantes[$j->id]['mandante'] ?? null) && ($participantes[$j->id]['visitante'] ?? null));
        $this->assertNotNull($jogoOitavas, 'Oitavas deve ter participantes reais apos lancar round_of_32.');

        // Garante prazo aberto para o palpite de oitavas.
        $jogoOitavas->update(['data_hora_inicio' => now()->addDay()]);

        Sanctum::actingAs($usuario);

        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogoOitavas->id,
                'placar_mandante' => 2,
                'placar_visitante' => 1,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('apostas', [
            'cupom_id' => $cupom->id,
            'tipo' => 'placar_jogo_eliminatoria',
            'jogo_id' => $jogoOitavas->id,
        ]);
    }

    private function lancarResultadosDeGrupos(Torneio $torneio): void
    {
        $jogos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))
            ->whereNotNull('selecao_mandante_id')->whereNotNull('selecao_visitante_id')->get();

        foreach ($jogos as $i => $jogo) {
            ResultadoJogo::query()->updateOrCreate(
                ['jogo_id' => $jogo->id],
                ['placar_mandante' => ($i % 3) + 1, 'placar_visitante' => 0, 'selecao_classificada_id' => null, 'encerrado_at' => now()],
            );
            $jogo->update(['status' => 'encerrado']);
        }
    }

    private function lancarResultadosDaFase(Torneio $torneio, string $slugFase): void
    {
        $participantes = app(ServicoResultadosTorneio::class)->participantesPorJogo(Torneio::query()->findOrFail($torneio->id));

        $jogos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('slug', $slugFase))
            ->get();

        foreach ($jogos as $jogo) {
            $mandante = $participantes[$jogo->id]['mandante'] ?? null;

            if (! $mandante) {
                continue;
            }

            // Mandante vence: vira o classificado real desse confronto.
            ResultadoJogo::query()->updateOrCreate(
                ['jogo_id' => $jogo->id],
                ['placar_mandante' => 2, 'placar_visitante' => 1, 'selecao_classificada_id' => $mandante->id, 'encerrado_at' => now()],
            );
            $jogo->update(['status' => 'encerrado']);
        }
    }

    /**
     * @return array{0: Usuario, 1: Cupom}
     */
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
