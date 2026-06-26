<?php

namespace Database\Seeders;

use App\Models\Fase;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\ResultadoTorneio;
use App\Models\Selecao;
use App\Models\Torneio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cria o 2º bolão: só mata-mata (32avos -> final), linkado à mesma Copa real
 * (TheSportsDB liga 4429 / temporada 2026). Sem fase de grupos: o chaveamento é
 * preenchido direto da API (ServicoMataMata, modo espelho por rodada).
 */
class BolaoMataMataSeeder extends Seeder
{
    public function run(): void
    {
        $torneio = Torneio::query()->updateOrCreate(
            ['nome' => 'Inter World Cup — Mata-Mata', 'edicao' => '2026-MM'],
            [
                'liga_externa_id' => 4429,
                'temporada_externa' => '2026',
                'status' => 'publicado',
                'data_inicio' => '2026-06-28',
                'data_fim' => '2026-07-19',
                'valor_cupom' => 10.00,
                'compras_abertas' => true,
            ],
        );

        // ── Fases (só mata-mata) ───────────────────────────────
        $roundOf32 = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'round_of_32'],
            ['nome' => 'Round of 32', 'ordem' => 1, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-06-28 12:00:00'],
        );
        $oitavas = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'oitavas_de_final'],
            ['nome' => 'Oitavas de Final', 'ordem' => 2, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-07-03 12:00:00'],
        );
        $quartas = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'quartas_de_final'],
            ['nome' => 'Quartas de Final', 'ordem' => 3, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-07-09 12:00:00'],
        );
        $semifinais = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'semifinais'],
            ['nome' => 'Semifinais', 'ordem' => 4, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-07-14 12:00:00'],
        );
        $terceiroLugar = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'terceiro_lugar'],
            ['nome' => 'Terceiro Lugar', 'ordem' => 5, 'tipo' => 'final', 'data_fechamento' => '2026-07-18 12:00:00'],
        );
        $final = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'final'],
            ['nome' => 'Final', 'ordem' => 6, 'tipo' => 'final', 'data_fechamento' => '2026-07-19 12:00:00'],
        );

        // ── 48 seleções (sem grupo, com id_externo) ────────────
        $nomesPorSigla = Selecao::query()->whereNotNull('nome')->pluck('nome', 'sigla')->all();
        foreach (config('thesportsdb.selecoes') as $sigla => $idExterno) {
            $nome = $nomesPorSigla[$sigla] ?? $sigla;
            Selecao::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'sigla' => $sigla],
                [
                    'grupo_id' => null,
                    'nome' => $nome,
                    'id_externo' => $idExterno,
                    'slug' => Str::slug($nome),
                    'ativo' => true,
                ],
            );
        }

        // ── 32 jogos placeholder (times nulos) ─────────────────
        $jogos = [];
        foreach (range(1, 16) as $i) {
            $jogos[] = ['fase' => $roundOf32, 'ordem' => $i];
        }
        foreach (range(1, 8) as $i) {
            $jogos[] = ['fase' => $oitavas, 'ordem' => $i];
        }
        foreach (range(1, 4) as $i) {
            $jogos[] = ['fase' => $quartas, 'ordem' => $i];
        }
        foreach (range(1, 2) as $i) {
            $jogos[] = ['fase' => $semifinais, 'ordem' => $i];
        }
        $jogos[] = ['fase' => $terceiroLugar, 'ordem' => 1];
        $jogos[] = ['fase' => $final, 'ordem' => 1];

        $base = strtotime('2026-06-28 16:00');
        $passo = 0;
        foreach ($jogos as $jogo) {
            Jogo::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'fase_id' => $jogo['fase']->id, 'ordem_na_fase' => $jogo['ordem']],
                [
                    'rodada_id' => null,
                    'grupo_id' => null,
                    'selecao_mandante_id' => null,
                    'selecao_visitante_id' => null,
                    'data_hora_inicio' => date('Y-m-d H:i:s', $base + ($passo++ * 4 * 3600)),
                    'status' => 'agendado',
                ],
            );
        }

        // ── Regras de pontuação (só knockout) ──────────────────
        $regras = [
            ['fase' => $roundOf32, 'chave' => 'classificado_mata_mata', 'nome' => 'Classificado Round of 32', 'descricao' => 'Acertou quem avançou no Round of 32', 'pontos' => 4],
            ['fase' => $oitavas, 'chave' => 'classificado_mata_mata', 'nome' => 'Classificado oitavas', 'descricao' => 'Acertou quem avançou nas oitavas', 'pontos' => 6],
            ['fase' => $quartas, 'chave' => 'classificado_mata_mata', 'nome' => 'Classificado quartas', 'descricao' => 'Acertou quem avançou nas quartas', 'pontos' => 8],
            ['fase' => $semifinais, 'chave' => 'classificado_mata_mata', 'nome' => 'Classificado semifinal', 'descricao' => 'Acertou quem avançou na semifinal', 'pontos' => 10],
            ['fase' => $terceiroLugar, 'chave' => 'classificado_mata_mata', 'nome' => 'Vencedor terceiro lugar', 'descricao' => 'Acertou quem venceu a disputa de terceiro lugar', 'pontos' => 8],
            ['fase' => $final, 'chave' => 'classificado_mata_mata', 'nome' => 'Campeão da final', 'descricao' => 'Acertou o campeão', 'pontos' => 10],
            ['fase' => null, 'chave' => 'campeao', 'nome' => 'Campeão', 'descricao' => 'Acertou o campeão da Copa', 'pontos' => 25],
            ['fase' => null, 'chave' => 'vice_campeao', 'nome' => 'Vice-campeão', 'descricao' => 'Acertou o vice-campeão', 'pontos' => 15],
            ['fase' => null, 'chave' => 'terceiro_colocado', 'nome' => 'Terceiro colocado', 'descricao' => 'Acertou o terceiro colocado', 'pontos' => 12],
        ];
        foreach ($regras as $regra) {
            RegraPontuacao::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'fase_id' => $regra['fase']?->id, 'chave' => $regra['chave']],
                ['nome' => $regra['nome'], 'descricao' => $regra['descricao'], 'pontos' => $regra['pontos'], 'ativo' => true],
            );
        }

        ResultadoTorneio::query()->updateOrCreate(
            ['torneio_id' => $torneio->id],
            ['campeao_selecao_id' => null, 'vice_campeao_selecao_id' => null, 'terceiro_colocado_selecao_id' => null, 'artilheiro_jogador_id' => null],
        );
    }
}
