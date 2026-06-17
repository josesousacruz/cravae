<?php

namespace Database\Seeders;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\PedidoCheckout;
use App\Models\ResultadoJogo;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoPontuacao;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder SOMENTE LOCAL para popular telas com dados realistas e capturar
 * os mockups da landing page. NAO incluir no DatabaseSeeder de producao.
 *
 * Uso: php artisan db:seed --class=Database\\Seeders\\DemonstracaoSeeder
 */
class DemonstracaoSeeder extends Seeder
{
    public function run(): void
    {
        $torneio = Torneio::query()->where('nome', 'Inter World Cup')->firstOrFail();

        // Jogos da fase de grupos (rodadas 1 e 2) com seleções definidas.
        $jogos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereNotNull('selecao_mandante_id')
            ->whereNotNull('selecao_visitante_id')
            ->whereHas('rodada', fn ($q) => $q->whereIn('ordem', [1, 2]))
            ->orderBy('ordem_na_fase')
            ->get();

        // Participante principal da demo: cupom ativo com palpites completos.
        $this->criarParticipante($torneio, $jogos, 'Larissa Andrade', 'larissa.demo@interworldcup.local', 1.0);

        // Participantes extras para o ranking ficar populado.
        $this->criarParticipante($torneio, $jogos, 'Rafael Menezes', 'rafael.demo@interworldcup.local', 0.8);
        $this->criarParticipante($torneio, $jogos, 'Camila Souza', 'camila.demo@interworldcup.local', 0.7);
        $this->criarParticipante($torneio, $jogos, 'Bruno Carvalho', 'bruno.demo@interworldcup.local', 0.6);
        $this->criarParticipante($torneio, $jogos, 'Patricia Lima', 'patricia.demo@interworldcup.local', 0.5);

        // Finaliza a rodada 1 para gerar eventos de pontuação (telas "Resultados"/"Ranking" populadas).
        $this->finalizarRodada1($torneio);

        // Recalcula a pontuação de todos os cupons do torneio.
        app(ServicoPontuacao::class)->recalcularTorneio($torneio);
    }

    private function finalizarRodada1(Torneio $torneio): void
    {
        $jogos = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereNotNull('selecao_mandante_id')
            ->whereNotNull('selecao_visitante_id')
            ->whereHas('rodada', fn ($q) => $q->where('ordem', 1))
            ->get();

        foreach ($jogos as $jogo) {
            [$golsMandante, $golsVisitante] = $this->placarDeterministico($jogo->ordem_na_fase, 1);

            ResultadoJogo::query()->updateOrCreate(
                ['jogo_id' => $jogo->id],
                [
                    'placar_mandante' => $golsMandante,
                    'placar_visitante' => $golsVisitante,
                    'selecao_classificada_id' => null,
                    'encerrado_at' => now(),
                ],
            );

            $jogo->update(['status' => 'encerrado']);
        }
    }

    private function criarParticipante(Torneio $torneio, $jogos, string $nome, string $email, float $fracaoPalpites): void
    {
        $usuario = Usuario::query()->updateOrCreate(
            ['email' => $email],
            ['nome' => $nome, 'perfil' => 'participante', 'password' => Hash::make('demo12345')],
        );

        $pedido = PedidoCheckout::query()->updateOrCreate(
            ['usuario_id' => $usuario->id, 'referencia_checkout' => 'demo-'.$usuario->id],
            [
                'valor' => $torneio->valor_cupom ?? 10.00,
                'status' => 'pago',
                'forma_pagamento' => 'pix',
                'pago_at' => now(),
            ],
        );

        $cupom = Cupom::query()->updateOrCreate(
            ['pedido_checkout_id' => $pedido->id],
            [
                'usuario_id' => $usuario->id,
                'codigo' => $this->codigoDeterministico($usuario->id),
                'status' => 'ativo',
            ],
        );

        // Recria as apostas do cupom de forma idempotente.
        Aposta::query()->where('cupom_id', $cupom->id)->delete();

        $totalAlvo = (int) floor($jogos->count() * $fracaoPalpites);
        $indice = 0;

        foreach ($jogos as $jogo) {
            if ($indice >= $totalAlvo) {
                break;
            }

            [$golsMandante, $golsVisitante] = $this->placarDeterministico($jogo->ordem_na_fase, $usuario->id);

            Aposta::query()->create([
                'cupom_id' => $cupom->id,
                'tipo' => 'placar_jogo_grupos',
                'torneio_id' => $jogo->torneio_id,
                'fase_id' => $jogo->fase_id,
                'rodada_id' => $jogo->rodada_id,
                'grupo_id' => $jogo->grupo_id,
                'jogo_id' => $jogo->id,
                'selecao_id' => null,
                'jogador_id' => null,
                'conteudo' => [
                    'placar_mandante' => $golsMandante,
                    'placar_visitante' => $golsVisitante,
                    'penal_mandante' => null,
                    'penal_visitante' => null,
                    'selecao_classificada_id' => null,
                ],
            ]);

            $indice++;
        }
    }

    private function codigoDeterministico(int $usuarioId): string
    {
        $base = strtoupper(substr(md5('demo-cupom-'.$usuarioId), 0, 10));

        return str_replace(['0', '1'], ['G', 'H'], $base);
    }

    /**
     * Placar plausível e determinístico (0 a 3 gols) a partir da ordem do jogo.
     *
     * @return array{0:int,1:int}
     */
    private function placarDeterministico(int $ordem, int $semente): array
    {
        $mandante = (($ordem * 7) + $semente) % 4;
        $visitante = (($ordem * 3) + ($semente * 2)) % 4;

        return [$mandante, $visitante];
    }
}
