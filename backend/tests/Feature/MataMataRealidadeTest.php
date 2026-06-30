<?php

namespace Tests\Feature;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MataMataRealidadeTest extends TestCase
{
    use RefreshDatabase;

    private function criarCupom(string $email): array
    {
        $usuario = Usuario::query()->create([
            'nome' => 'U '.strtok($email, '@'), 'email' => $email,
            'telefone' => '71999999999', 'cpf_cnpj' => '12345678901',
            'password' => '12345678', 'perfil' => 'usuario',
        ]);
        Sanctum::actingAs($usuario);
        $torneio = Torneio::query()->where('status', 'publicado')->firstOrFail();
        $pedido = $this->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])->assertCreated()->json('pedido');
        $cupom = $this->postJson("/api/pedidos-checkout/{$pedido['id']}/confirmar-sandbox", [])->assertOk()->json('cupom');

        return [$usuario, Cupom::query()->findOrFail($cupom['id']), $torneio];
    }

    public function test_palpite_eliminatoria_usa_participantes_reais_sem_exigir_grupos(): void
    {
        // Ancora o relogio antes do mata-mata (calendario WC2026 do seed) para que o
        // prazo do palpite de eliminatoria (Round of 32, a partir de 28/06) siga aberto.
        Carbon::setTestNow('2026-06-20 12:00:00');

        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom('real@teste.local');

        $this->lancarResultadosDeGrupos($torneio);

        $jogoR32 = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('slug', 'round_of_32'))
            ->orderBy('ordem_na_fase')
            ->firstOrFail();

        Sanctum::actingAs($usuario);
        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'placar_jogo_eliminatoria',
                'jogo_id' => $jogoR32->id,
                'placar_mandante' => 2,
                'placar_visitante' => 1,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('apostas', [
            'cupom_id' => $cupom->id,
            'tipo' => 'placar_jogo_eliminatoria',
            'jogo_id' => $jogoR32->id,
        ]);
        $aposta = Aposta::query()->where('cupom_id', $cupom->id)->where('jogo_id', $jogoR32->id)->firstOrFail();
        $this->assertNotNull($aposta->conteudo['selecao_classificada_id']);
    }

    public function test_aposta_podio_pontua_contra_resultado_real(): void
    {
        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom('podio@teste.local');

        $selecoes = \App\Models\Selecao::query()->where('torneio_id', $torneio->id)->take(5)->pluck('id')->all();
        // [campeao, vice, terceiro] = palpite; o resultado REAL usa outras selecoes para
        // vice/terceiro, de modo que so o campeao acerta (FKs reais, sem ids ficticios).
        [$campeao, $vice, $terceiro, $viceReal, $terceiroReal] = $selecoes;

        foreach (['campeao' => 50, 'vice_campeao' => 30, 'terceiro_colocado' => 20] as $chave => $pontos) {
            \App\Models\RegraPontuacao::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'fase_id' => null, 'chave' => $chave],
                ['nome' => $chave, 'pontos' => $pontos, 'ativo' => true],
            );
        }

        // Cria a aposta de podio direto (o endpoint de lote fecharia pelo prazo, pois o
        // torneio do seed ja comecou; aqui o foco e a PONTUACAO).
        $cupom->apostas()->create([
            'tipo' => 'podio',
            'torneio_id' => $torneio->id,
            'fase_id' => null, 'rodada_id' => null, 'grupo_id' => null,
            'jogo_id' => null, 'selecao_id' => null, 'jogador_id' => null,
            'conteudo' => [
                'campeao_selecao_id' => $campeao,
                'vice_selecao_id' => $vice,
                'terceiro_selecao_id' => $terceiro,
            ],
        ]);

        \App\Models\ResultadoTorneio::query()->updateOrCreate(
            ['torneio_id' => $torneio->id],
            ['campeao_selecao_id' => $campeao, 'vice_campeao_selecao_id' => $viceReal, 'terceiro_colocado_selecao_id' => $terceiroReal],
        );

        app(\App\Services\ServicoPontuacao::class)->recalcularCupom($cupom->fresh());

        $pontuacao = \App\Models\PontuacaoCupom::query()->where('cupom_id', $cupom->id)->firstOrFail();
        $this->assertSame(50, (int) $pontuacao->pontuacao_total, 'esperava 50 pts (so o campeao correto)');
    }

    public function test_endpoint_bracket_retorna_jogos_de_mata_mata_com_times_reais_ou_nulos(): void
    {
        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom('bracket@teste.local');

        Sanctum::actingAs($usuario);
        $resp = $this->getJson("/api/cupons/{$cupom->id}/bracket")->assertOk();

        $this->assertIsArray($resp->json('bracket'));
        $this->assertNotEmpty($resp->json('bracket'));
        $this->assertArrayHasKey('podio_real', $resp->json('resumo'));
        $primeiro = $resp->json('bracket.0');
        $this->assertArrayHasKey('selecao_mandante', $primeiro);
    }

    public function test_podio_pode_ser_palpitado_durante_a_fase_de_grupos(): void
    {
        // Ancora o relogio na fase de grupos (calendario WC2026 do seed): o podio fecha
        // 1h antes do 1o jogo do mata-mata (28/06), entao precisa estar antes disso.
        Carbon::setTestNow('2026-06-20 12:00:00');

        $this->seed();
        [$usuario, $cupom, $torneio] = $this->criarCupom('podio-prazo@teste.local');

        $selecoes = \App\Models\Selecao::query()->where('torneio_id', $torneio->id)->take(3)->pluck('id')->all();

        // O podio fecha 1h antes do 1o jogo do mata-mata (futuro no seed), entao esta aberto.
        Sanctum::actingAs($usuario);
        $this->postJson("/api/cupons/{$cupom->id}/apostas/lote", [
            'apostas' => [[
                'tipo' => 'podio',
                'torneio_id' => $torneio->id,
                'campeao_selecao_id' => $selecoes[0],
                'vice_selecao_id' => $selecoes[1],
                'terceiro_selecao_id' => $selecoes[2],
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('apostas', ['cupom_id' => $cupom->id, 'tipo' => 'podio']);
    }

    private function lancarResultadosDeGrupos(Torneio $torneio): void
    {
        $jogos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))
            ->whereNotNull('selecao_mandante_id')
            ->whereNotNull('selecao_visitante_id')
            ->get();

        foreach ($jogos as $i => $jogo) {
            $m = ($i % 3) + 1;
            ResultadoJogo::query()->updateOrCreate(
                ['jogo_id' => $jogo->id],
                ['placar_mandante' => $m, 'placar_visitante' => 0, 'selecao_classificada_id' => null, 'encerrado_at' => now()],
            );
            $jogo->update(['status' => 'encerrado']);
        }
    }
}
