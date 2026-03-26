<?php

namespace Database\Seeders;

use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Jogador;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\ResultadoTorneio;
use App\Models\Rodada;
use App\Models\Selecao;
use App\Models\Torneio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TorneioMockadoSeeder extends Seeder
{
    public function run(): void
    {
        $torneio = Torneio::query()->updateOrCreate(
            ['nome' => 'Inter World Cup', 'edicao' => '2026'],
            [
                'status' => 'publicado',
                'data_inicio' => now()->addDays(10),
                'data_fim' => now()->addDays(40),
                'valor_cupom' => 10.00,
            ],
        );

        $faseGrupos = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'fase_de_grupos'],
            [
                'nome' => 'Fase de Grupos',
                'ordem' => 1,
                'tipo' => 'grupos',
                'data_fechamento' => now()->addDays(10)->subHour(),
            ],
        );

        $semifinais = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'semifinais'],
            [
                'nome' => 'Semifinais',
                'ordem' => 2,
                'tipo' => 'eliminatoria',
                'data_fechamento' => now()->addDays(20)->subHour(),
            ],
        );

        $terceiroLugar = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'terceiro_lugar'],
            [
                'nome' => 'Terceiro Lugar',
                'ordem' => 3,
                'tipo' => 'final',
                'data_fechamento' => now()->addDays(25)->subHour(),
            ],
        );

        $final = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'final'],
            [
                'nome' => 'Final',
                'ordem' => 4,
                'tipo' => 'final',
                'data_fechamento' => now()->addDays(26)->subHour(),
            ],
        );

        $rodada1 = Rodada::query()->updateOrCreate(
            ['fase_id' => $faseGrupos->id, 'ordem' => 1],
            [
                'nome' => 'Rodada 1',
                'data_fechamento' => now()->addDays(10)->subHour(),
            ],
        );

        $grupoA = Grupo::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'nome' => 'Grupo A'],
            ['ordem' => 1],
        );

        $grupoB = Grupo::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'nome' => 'Grupo B'],
            ['ordem' => 2],
        );

        $selecoes = collect([
            ['grupo' => $grupoA, 'nome' => 'Brasil', 'sigla' => 'BRA'],
            ['grupo' => $grupoA, 'nome' => 'Japao', 'sigla' => 'JPN'],
            ['grupo' => $grupoB, 'nome' => 'Franca', 'sigla' => 'FRA'],
            ['grupo' => $grupoB, 'nome' => 'Mexico', 'sigla' => 'MEX'],
        ])->map(function (array $dados) use ($torneio) {
            $selecao = Selecao::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'sigla' => $dados['sigla']],
                [
                    'grupo_id' => $dados['grupo']->id,
                    'nome' => $dados['nome'],
                    'slug' => Str::slug($dados['nome']),
                    'ativo' => true,
                ],
            );

            Jogador::query()->updateOrCreate(
                ['selecao_id' => $selecao->id, 'nome' => 'Camisa 9 '.$dados['sigla']],
                [
                    'apelido' => 'Artilheiro '.$dados['sigla'],
                    'posicao' => 'Atacante',
                    'numero_camisa' => 9,
                    'ativo' => true,
                ],
            );

            return $selecao;
        })->keyBy('sigla');

        Jogo::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'fase_id' => $faseGrupos->id, 'ordem_na_fase' => 1],
            [
                'rodada_id' => $rodada1->id,
                'grupo_id' => $grupoA->id,
                'selecao_mandante_id' => $selecoes['BRA']->id,
                'selecao_visitante_id' => $selecoes['JPN']->id,
                'data_hora_inicio' => now()->addDays(10),
                'status' => 'agendado',
            ],
        );

        Jogo::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'fase_id' => $faseGrupos->id, 'ordem_na_fase' => 2],
            [
                'rodada_id' => $rodada1->id,
                'grupo_id' => $grupoB->id,
                'selecao_mandante_id' => $selecoes['FRA']->id,
                'selecao_visitante_id' => $selecoes['MEX']->id,
                'data_hora_inicio' => now()->addDays(11),
                'status' => 'agendado',
            ],
        );

        Jogo::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'fase_id' => $semifinais->id, 'ordem_na_fase' => 1],
            [
                'rodada_id' => null,
                'grupo_id' => null,
                'selecao_mandante_id' => $selecoes['BRA']->id,
                'selecao_visitante_id' => $selecoes['MEX']->id,
                'data_hora_inicio' => now()->addDays(20),
                'status' => 'agendado',
            ],
        );

        Jogo::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'fase_id' => $semifinais->id, 'ordem_na_fase' => 2],
            [
                'rodada_id' => null,
                'grupo_id' => null,
                'selecao_mandante_id' => $selecoes['FRA']->id,
                'selecao_visitante_id' => $selecoes['JPN']->id,
                'data_hora_inicio' => now()->addDays(21),
                'status' => 'agendado',
            ],
        );

        Jogo::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'fase_id' => $terceiroLugar->id, 'ordem_na_fase' => 1],
            [
                'rodada_id' => null,
                'grupo_id' => null,
                'selecao_mandante_id' => $selecoes['MEX']->id,
                'selecao_visitante_id' => $selecoes['JPN']->id,
                'data_hora_inicio' => now()->addDays(25),
                'status' => 'agendado',
            ],
        );

        Jogo::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'fase_id' => $final->id, 'ordem_na_fase' => 1],
            [
                'rodada_id' => null,
                'grupo_id' => null,
                'selecao_mandante_id' => $selecoes['BRA']->id,
                'selecao_visitante_id' => $selecoes['FRA']->id,
                'data_hora_inicio' => now()->addDays(26),
                'status' => 'agendado',
            ],
        );

        $regras = [
            ['fase' => $faseGrupos, 'chave' => 'placar_exato_fase_grupos', 'nome' => 'Placar exato fase de grupos', 'pontos' => 10],
            ['fase' => $faseGrupos, 'chave' => 'vencedor_fase_grupos', 'nome' => 'Vencedor fase de grupos', 'pontos' => 5],
            ['fase' => null, 'chave' => 'primeiro_colocado_grupo', 'nome' => 'Primeiro colocado do grupo', 'pontos' => 8],
            ['fase' => null, 'chave' => 'segundo_colocado_grupo', 'nome' => 'Segundo colocado do grupo', 'pontos' => 6],
            ['fase' => null, 'chave' => 'artilheiro', 'nome' => 'Artilheiro', 'pontos' => 20],
            ['fase' => $semifinais, 'chave' => 'classificado_mata_mata', 'nome' => 'Classificado semifinal', 'pontos' => 10],
            ['fase' => $semifinais, 'chave' => 'classificado_e_placar_mata_mata', 'nome' => 'Classificado e placar semifinal', 'pontos' => 16],
            ['fase' => $terceiroLugar, 'chave' => 'classificado_mata_mata', 'nome' => 'Vencedor terceiro lugar', 'pontos' => 10],
            ['fase' => $terceiroLugar, 'chave' => 'classificado_e_placar_mata_mata', 'nome' => 'Vencedor e placar terceiro lugar', 'pontos' => 16],
            ['fase' => $final, 'chave' => 'classificado_mata_mata', 'nome' => 'Campeao da final', 'pontos' => 10],
            ['fase' => $final, 'chave' => 'classificado_e_placar_mata_mata', 'nome' => 'Campeao e placar da final', 'pontos' => 16],
            ['fase' => null, 'chave' => 'campeao', 'nome' => 'Campeao', 'pontos' => 25],
            ['fase' => null, 'chave' => 'vice_campeao', 'nome' => 'Vice-campeao', 'pontos' => 15],
            ['fase' => null, 'chave' => 'terceiro_colocado', 'nome' => 'Terceiro colocado', 'pontos' => 12],
        ];

        foreach ($regras as $regra) {
            RegraPontuacao::query()->updateOrCreate(
                [
                    'torneio_id' => $torneio->id,
                    'fase_id' => $regra['fase']?->id,
                    'chave' => $regra['chave'],
                ],
                [
                    'nome' => $regra['nome'],
                    'descricao' => $regra['nome'].' do MVP',
                    'pontos' => $regra['pontos'],
                    'ativo' => true,
                ],
            );
        }

        ResultadoTorneio::query()->updateOrCreate(
            ['torneio_id' => $torneio->id],
            [
                'campeao_selecao_id' => null,
                'vice_campeao_selecao_id' => null,
                'terceiro_colocado_selecao_id' => null,
                'artilheiro_jogador_id' => null,
            ],
        );
    }
}
