<?php

namespace Database\Seeders;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\PedidoCheckout;
use App\Models\ResultadoJogo;
use App\Models\ResultadoTorneio;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoPontuacao;
use App\Services\ServicoResultadosTorneio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder SOMENTE LOCAL: cria um usuario de demonstracao com TODOS os tipos de
 * aposta (grupos + mata-mata em todas as fases + podio). Como o mata-mata e
 * "pela realidade", o seeder LANCA os resultados reais fase a fase para que os
 * participantes reais existam e possam ser palpitados.
 *
 * Efeito colateral: o torneio fica totalmente "jogado" (todos os resultados
 * lancados). Ideal para demonstrar o fluxo completo (chaveamento, ranking,
 * pontuacao). NAO incluir no DatabaseSeeder de producao.
 *
 * Uso: php artisan db:seed --class=Database\\Seeders\\DemoCompletoSeeder
 *
 * Login: demo@interworldcup.local / demo12345
 */
class DemoCompletoSeeder extends Seeder
{
    public function run(): void
    {
        $torneio = Torneio::query()->where('nome', 'Inter World Cup')->firstOrFail();
        $servicoResultados = app(ServicoResultadosTorneio::class);

        $usuario = Usuario::query()->updateOrCreate(
            ['email' => 'demo@interworldcup.local'],
            [
                'nome' => 'Demo Completo',
                'perfil' => 'participante',
                'telefone' => '71988887777',
                'cpf_cnpj' => '12345678901',
                'password' => Hash::make('demo12345'),
            ],
        );

        $pedido = PedidoCheckout::query()->updateOrCreate(
            ['referencia_checkout' => 'demo-completo'],
            [
                'usuario_id' => $usuario->id,
                'torneio_id' => $torneio->id,
                'valor' => $torneio->valor_cupom ?? 10,
                'status' => 'pago',
                'forma_pagamento' => 'pix',
                'pago_at' => now(),
            ],
        );

        $cupom = Cupom::query()->updateOrCreate(
            ['usuario_id' => $usuario->id, 'torneio_id' => $torneio->id],
            ['pedido_checkout_id' => $pedido->id, 'codigo' => 'DEMOCOMPLETO', 'status' => 'ativo'],
        );

        Aposta::query()->where('cupom_id', $cupom->id)->delete();

        // 1) GRUPOS: lanca resultado real e palpita igual (acerta o placar).
        $jogosGrupos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('fase', fn ($q) => $q->where('tipo', 'grupos'))
            ->whereNotNull('selecao_mandante_id')
            ->whereNotNull('selecao_visitante_id')
            ->orderBy('ordem_na_fase')
            ->get();

        foreach ($jogosGrupos as $i => $jogo) {
            $placarMandante = ($i % 3) + 1; // 1,2,3 -> mandante normalmente vence
            $placarVisitante = $i % 2;      // 0,1

            ResultadoJogo::query()->updateOrCreate(
                ['jogo_id' => $jogo->id],
                ['placar_mandante' => $placarMandante, 'placar_visitante' => $placarVisitante, 'selecao_classificada_id' => null, 'encerrado_at' => now()],
            );
            $jogo->update(['status' => 'encerrado']);

            $this->criarAposta($cupom->id, $torneio->id, 'placar_jogo_grupos', $jogo, [
                'placar_mandante' => $placarMandante,
                'placar_visitante' => $placarVisitante,
                'penal_mandante' => null,
                'penal_visitante' => null,
                'selecao_classificada_id' => null,
            ]);
        }

        // 2) MATA-MATA: fase a fase, na ordem. Resolve participantes reais,
        //    lanca o resultado (mandante vence 1x0) e palpita igual.
        $fasesElim = $torneio->fases()->where('tipo', '!=', 'grupos')->orderBy('ordem')->get();

        foreach ($fasesElim as $fase) {
            $jogos = Jogo::query()
                ->where('torneio_id', $torneio->id)
                ->where('fase_id', $fase->id)
                ->orderBy('ordem_na_fase')
                ->get();

            foreach ($jogos as $jogo) {
                $participantes = $servicoResultados->participantesDoJogo($jogo->fresh());
                $mandante = $participantes['mandante'];
                $visitante = $participantes['visitante'];

                if (! $mandante || ! $visitante) {
                    continue;
                }

                $vencedor = (int) $mandante->id;

                ResultadoJogo::query()->updateOrCreate(
                    ['jogo_id' => $jogo->id],
                    ['placar_mandante' => 1, 'placar_visitante' => 0, 'selecao_classificada_id' => $vencedor, 'encerrado_at' => now()],
                );
                $jogo->update(['status' => 'encerrado']);

                $this->criarAposta($cupom->id, $torneio->id, 'placar_jogo_eliminatoria', $jogo, [
                    'placar_mandante' => 1,
                    'placar_visitante' => 0,
                    'penal_mandante' => null,
                    'penal_visitante' => null,
                    'selecao_classificada_id' => $vencedor,
                ]);
            }
        }

        // 3) PODIO: palpita o podio real.
        $podio = $servicoResultados->resolverPodio($torneio->fresh());

        Aposta::query()->create([
            'cupom_id' => $cupom->id,
            'tipo' => 'podio',
            'torneio_id' => $torneio->id,
            'fase_id' => null,
            'rodada_id' => null,
            'grupo_id' => null,
            'jogo_id' => null,
            'selecao_id' => null,
            'jogador_id' => null,
            'conteudo' => [
                'campeao_selecao_id' => $podio['campeao_selecao_id'],
                'vice_selecao_id' => $podio['vice_campeao_selecao_id'],
                'terceiro_selecao_id' => $podio['terceiro_colocado_selecao_id'],
            ],
        ]);

        // 4) Sincroniza o podio real do torneio e recalcula a pontuacao.
        ResultadoTorneio::query()->updateOrCreate(
            ['torneio_id' => $torneio->id],
            [
                'campeao_selecao_id' => $podio['campeao_selecao_id'],
                'vice_campeao_selecao_id' => $podio['vice_campeao_selecao_id'],
                'terceiro_colocado_selecao_id' => $podio['terceiro_colocado_selecao_id'],
            ],
        );

        app(ServicoPontuacao::class)->recalcularCupom($cupom->fresh());
    }

    /**
     * @param array<string, mixed> $conteudo
     */
    private function criarAposta(int $cupomId, int $torneioId, string $tipo, Jogo $jogo, array $conteudo): void
    {
        Aposta::query()->create([
            'cupom_id' => $cupomId,
            'tipo' => $tipo,
            'torneio_id' => $torneioId,
            'fase_id' => $jogo->fase_id,
            'rodada_id' => $jogo->rodada_id,
            'grupo_id' => $jogo->grupo_id,
            'jogo_id' => $jogo->id,
            'selecao_id' => null,
            'jogador_id' => null,
            'conteudo' => $conteudo,
        ]);
    }
}
