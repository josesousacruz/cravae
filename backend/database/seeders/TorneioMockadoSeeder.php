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
                'data_inicio' => '2026-06-11',
                'data_fim' => '2026-07-19',
                'valor_cupom' => 10.00,
            ],
        );

        // ── Fases ──────────────────────────────────────────────

        $faseGrupos = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'fase_de_grupos'],
            ['nome' => 'Fase de Grupos', 'ordem' => 1, 'tipo' => 'grupos', 'data_fechamento' => '2026-06-11 12:00:00'],
        );

        $roundOf32 = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'round_of_32'],
            ['nome' => 'Round of 32', 'ordem' => 2, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-06-28 12:00:00'],
        );

        $oitavas = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'oitavas_de_final'],
            ['nome' => 'Oitavas de Final', 'ordem' => 3, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-07-03 12:00:00'],
        );

        $quartas = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'quartas_de_final'],
            ['nome' => 'Quartas de Final', 'ordem' => 4, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-07-09 12:00:00'],
        );

        $semifinais = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'semifinais'],
            ['nome' => 'Semifinais', 'ordem' => 5, 'tipo' => 'eliminatoria', 'data_fechamento' => '2026-07-14 12:00:00'],
        );

        $terceiroLugar = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'terceiro_lugar'],
            ['nome' => 'Terceiro Lugar', 'ordem' => 6, 'tipo' => 'final', 'data_fechamento' => '2026-07-18 12:00:00'],
        );

        $final = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'final'],
            ['nome' => 'Final', 'ordem' => 7, 'tipo' => 'final', 'data_fechamento' => '2026-07-19 12:00:00'],
        );

        // ── Rodadas ────────────────────────────────────────────

        // data_fechamento da rodada fica nulo: os palpites de grupos fecham por dia,
        // 1h antes do primeiro jogo do dia (ver ServicoFechamentoApostas).
        $rodada1 = Rodada::query()->updateOrCreate(
            ['fase_id' => $faseGrupos->id, 'ordem' => 1],
            ['nome' => 'Rodada 1', 'data_fechamento' => null],
        );

        $rodada2 = Rodada::query()->updateOrCreate(
            ['fase_id' => $faseGrupos->id, 'ordem' => 2],
            ['nome' => 'Rodada 2', 'data_fechamento' => null],
        );

        $rodada3 = Rodada::query()->updateOrCreate(
            ['fase_id' => $faseGrupos->id, 'ordem' => 3],
            ['nome' => 'Rodada 3', 'data_fechamento' => null],
        );

        // ── Grupos ─────────────────────────────────────────────

        $gruposData = [
            'A' => [
                ['nome' => 'Mexico', 'sigla' => 'MEX'],
                ['nome' => 'Africa do Sul', 'sigla' => 'RSA'],
                ['nome' => 'Coreia do Sul', 'sigla' => 'KOR'],
                ['nome' => 'Tchequia', 'sigla' => 'CZE'], // Repescagem UEFA Path D
            ],
            'B' => [
                ['nome' => 'Canada', 'sigla' => 'CAN'],
                ['nome' => 'Bosnia e Herzegovina', 'sigla' => 'BIH'], // Repescagem UEFA Path A
                ['nome' => 'Qatar', 'sigla' => 'QAT'],
                ['nome' => 'Suica', 'sigla' => 'SUI'],
            ],
            'C' => [
                ['nome' => 'Brasil', 'sigla' => 'BRA'],
                ['nome' => 'Marrocos', 'sigla' => 'MAR'],
                ['nome' => 'Haiti', 'sigla' => 'HAI'],
                ['nome' => 'Escocia', 'sigla' => 'SCO'],
            ],
            'D' => [
                ['nome' => 'Estados Unidos', 'sigla' => 'USA'],
                ['nome' => 'Paraguai', 'sigla' => 'PAR'],
                ['nome' => 'Australia', 'sigla' => 'AUS'],
                ['nome' => 'Turquia', 'sigla' => 'TUR'], // Repescagem UEFA Path C
            ],
            'E' => [
                ['nome' => 'Alemanha', 'sigla' => 'GER'],
                ['nome' => 'Curacao', 'sigla' => 'CUW'],
                ['nome' => 'Costa do Marfim', 'sigla' => 'CIV'],
                ['nome' => 'Equador', 'sigla' => 'ECU'],
            ],
            'F' => [
                ['nome' => 'Holanda', 'sigla' => 'NED'],
                ['nome' => 'Japao', 'sigla' => 'JPN'],
                ['nome' => 'Suecia', 'sigla' => 'SWE'], // Repescagem UEFA Path B
                ['nome' => 'Tunisia', 'sigla' => 'TUN'],
            ],
            'G' => [
                ['nome' => 'Belgica', 'sigla' => 'BEL'],
                ['nome' => 'Egito', 'sigla' => 'EGY'],
                ['nome' => 'Ira', 'sigla' => 'IRN'],
                ['nome' => 'Nova Zelandia', 'sigla' => 'NZL'],
            ],
            'H' => [
                ['nome' => 'Espanha', 'sigla' => 'ESP'],
                ['nome' => 'Cabo Verde', 'sigla' => 'CPV'],
                ['nome' => 'Arabia Saudita', 'sigla' => 'KSA'],
                ['nome' => 'Uruguai', 'sigla' => 'URU'],
            ],
            'I' => [
                ['nome' => 'Franca', 'sigla' => 'FRA'],
                ['nome' => 'Senegal', 'sigla' => 'SEN'],
                ['nome' => 'Iraque', 'sigla' => 'IRQ'], // Repescagem Intercontinental 2
                ['nome' => 'Noruega', 'sigla' => 'NOR'],
            ],
            'J' => [
                ['nome' => 'Argentina', 'sigla' => 'ARG'],
                ['nome' => 'Argelia', 'sigla' => 'ALG'],
                ['nome' => 'Austria', 'sigla' => 'AUT'],
                ['nome' => 'Jordania', 'sigla' => 'JOR'],
            ],
            'K' => [
                ['nome' => 'Portugal', 'sigla' => 'POR'],
                ['nome' => 'RD Congo', 'sigla' => 'COD'], // Repescagem Intercontinental 1
                ['nome' => 'Uzbequistao', 'sigla' => 'UZB'],
                ['nome' => 'Colombia', 'sigla' => 'COL'],
            ],
            'L' => [
                ['nome' => 'Inglaterra', 'sigla' => 'ENG'],
                ['nome' => 'Croacia', 'sigla' => 'CRO'],
                ['nome' => 'Gana', 'sigla' => 'GHA'],
                ['nome' => 'Panama', 'sigla' => 'PAN'],
            ],
        ];

        $grupos = [];
        $selecoes = [];
        $ordem = 1;

        foreach ($gruposData as $letra => $times) {
            $grupo = Grupo::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'nome' => "Grupo $letra"],
                ['ordem' => $ordem++],
            );
            $grupos[$letra] = $grupo;

            foreach ($times as $time) {
                $selecao = Selecao::query()->updateOrCreate(
                    ['torneio_id' => $torneio->id, 'sigla' => $time['sigla']],
                    [
                        'grupo_id' => $grupo->id,
                        'nome' => $time['nome'],
                        'id_externo' => config('thesportsdb.selecoes')[$time['sigla']] ?? null,
                        'slug' => Str::slug($time['nome']),
                        'ativo' => true,
                    ],
                );

                Jogador::query()->updateOrCreate(
                    ['selecao_id' => $selecao->id, 'nome' => 'Camisa 9 '.$time['sigla']],
                    [
                        'apelido' => 'Artilheiro '.$time['sigla'],
                        'posicao' => 'Atacante',
                        'numero_camisa' => 9,
                        'ativo' => true,
                    ],
                );

                Jogador::query()->updateOrCreate(
                    ['selecao_id' => $selecao->id, 'nome' => 'Camisa 10 '.$time['sigla']],
                    [
                        'apelido' => 'Meia '.$time['sigla'],
                        'posicao' => 'Meia',
                        'numero_camisa' => 10,
                        'ativo' => true,
                    ],
                );

                $selecoes[$time['sigla']] = $selecao;
            }
        }

        // ── Jogos da Fase de Grupos (72 jogos) ────────────────

        $jogosGrupos = [
            // Rodada 1
            ['rodada' => 1, 'grupo' => 'A', 'data' => '2026-06-11 18:00', 'mandante' => 'MEX', 'visitante' => 'RSA'],
            ['rodada' => 1, 'grupo' => 'A', 'data' => '2026-06-11 21:00', 'mandante' => 'KOR', 'visitante' => 'UD4'],
            ['rodada' => 1, 'grupo' => 'B', 'data' => '2026-06-12 15:00', 'mandante' => 'CAN', 'visitante' => 'UA1'],
            ['rodada' => 1, 'grupo' => 'D', 'data' => '2026-06-12 18:00', 'mandante' => 'USA', 'visitante' => 'PAR'],
            ['rodada' => 1, 'grupo' => 'C', 'data' => '2026-06-13 15:00', 'mandante' => 'HAI', 'visitante' => 'SCO'],
            ['rodada' => 1, 'grupo' => 'D', 'data' => '2026-06-13 15:00', 'mandante' => 'AUS', 'visitante' => 'UC3'],
            ['rodada' => 1, 'grupo' => 'C', 'data' => '2026-06-13 18:00', 'mandante' => 'BRA', 'visitante' => 'MAR'],
            ['rodada' => 1, 'grupo' => 'B', 'data' => '2026-06-13 21:00', 'mandante' => 'QAT', 'visitante' => 'SUI'],
            ['rodada' => 1, 'grupo' => 'E', 'data' => '2026-06-14 15:00', 'mandante' => 'CIV', 'visitante' => 'ECU'],
            ['rodada' => 1, 'grupo' => 'E', 'data' => '2026-06-14 18:00', 'mandante' => 'GER', 'visitante' => 'CUW'],
            ['rodada' => 1, 'grupo' => 'F', 'data' => '2026-06-14 18:00', 'mandante' => 'NED', 'visitante' => 'JPN'],
            ['rodada' => 1, 'grupo' => 'F', 'data' => '2026-06-14 21:00', 'mandante' => 'UB2', 'visitante' => 'TUN'],
            ['rodada' => 1, 'grupo' => 'H', 'data' => '2026-06-15 15:00', 'mandante' => 'KSA', 'visitante' => 'URU'],
            ['rodada' => 1, 'grupo' => 'H', 'data' => '2026-06-15 18:00', 'mandante' => 'ESP', 'visitante' => 'CPV'],
            ['rodada' => 1, 'grupo' => 'G', 'data' => '2026-06-15 18:00', 'mandante' => 'IRN', 'visitante' => 'NZL'],
            ['rodada' => 1, 'grupo' => 'G', 'data' => '2026-06-15 21:00', 'mandante' => 'BEL', 'visitante' => 'EGY'],
            ['rodada' => 1, 'grupo' => 'I', 'data' => '2026-06-16 15:00', 'mandante' => 'FRA', 'visitante' => 'SEN'],
            ['rodada' => 1, 'grupo' => 'I', 'data' => '2026-06-16 18:00', 'mandante' => 'IC2', 'visitante' => 'NOR'],
            ['rodada' => 1, 'grupo' => 'J', 'data' => '2026-06-16 18:00', 'mandante' => 'ARG', 'visitante' => 'ALG'],
            ['rodada' => 1, 'grupo' => 'J', 'data' => '2026-06-16 21:00', 'mandante' => 'AUT', 'visitante' => 'JOR'],
            ['rodada' => 1, 'grupo' => 'L', 'data' => '2026-06-17 15:00', 'mandante' => 'GHA', 'visitante' => 'PAN'],
            ['rodada' => 1, 'grupo' => 'L', 'data' => '2026-06-17 18:00', 'mandante' => 'ENG', 'visitante' => 'CRO'],
            ['rodada' => 1, 'grupo' => 'K', 'data' => '2026-06-17 18:00', 'mandante' => 'POR', 'visitante' => 'IC1'],
            ['rodada' => 1, 'grupo' => 'K', 'data' => '2026-06-17 21:00', 'mandante' => 'UZB', 'visitante' => 'COL'],

            // Rodada 2
            ['rodada' => 2, 'grupo' => 'A', 'data' => '2026-06-18 15:00', 'mandante' => 'UD4', 'visitante' => 'RSA'],
            ['rodada' => 2, 'grupo' => 'B', 'data' => '2026-06-18 18:00', 'mandante' => 'SUI', 'visitante' => 'UA1'],
            ['rodada' => 2, 'grupo' => 'B', 'data' => '2026-06-18 18:00', 'mandante' => 'CAN', 'visitante' => 'QAT'],
            ['rodada' => 2, 'grupo' => 'A', 'data' => '2026-06-18 21:00', 'mandante' => 'MEX', 'visitante' => 'KOR'],
            ['rodada' => 2, 'grupo' => 'C', 'data' => '2026-06-19 15:00', 'mandante' => 'BRA', 'visitante' => 'HAI'],
            ['rodada' => 2, 'grupo' => 'C', 'data' => '2026-06-19 15:00', 'mandante' => 'SCO', 'visitante' => 'MAR'],
            ['rodada' => 2, 'grupo' => 'D', 'data' => '2026-06-19 18:00', 'mandante' => 'UC3', 'visitante' => 'PAR'],
            ['rodada' => 2, 'grupo' => 'D', 'data' => '2026-06-19 21:00', 'mandante' => 'USA', 'visitante' => 'AUS'],
            ['rodada' => 2, 'grupo' => 'E', 'data' => '2026-06-20 15:00', 'mandante' => 'GER', 'visitante' => 'CIV'],
            ['rodada' => 2, 'grupo' => 'E', 'data' => '2026-06-20 18:00', 'mandante' => 'ECU', 'visitante' => 'CUW'],
            ['rodada' => 2, 'grupo' => 'F', 'data' => '2026-06-20 18:00', 'mandante' => 'NED', 'visitante' => 'UB2'],
            ['rodada' => 2, 'grupo' => 'F', 'data' => '2026-06-20 21:00', 'mandante' => 'TUN', 'visitante' => 'JPN'],
            ['rodada' => 2, 'grupo' => 'H', 'data' => '2026-06-21 15:00', 'mandante' => 'URU', 'visitante' => 'CPV'],
            ['rodada' => 2, 'grupo' => 'H', 'data' => '2026-06-21 18:00', 'mandante' => 'ESP', 'visitante' => 'KSA'],
            ['rodada' => 2, 'grupo' => 'G', 'data' => '2026-06-21 18:00', 'mandante' => 'BEL', 'visitante' => 'IRN'],
            ['rodada' => 2, 'grupo' => 'G', 'data' => '2026-06-21 21:00', 'mandante' => 'NZL', 'visitante' => 'EGY'],
            ['rodada' => 2, 'grupo' => 'I', 'data' => '2026-06-22 15:00', 'mandante' => 'NOR', 'visitante' => 'SEN'],
            ['rodada' => 2, 'grupo' => 'I', 'data' => '2026-06-22 18:00', 'mandante' => 'FRA', 'visitante' => 'IC2'],
            ['rodada' => 2, 'grupo' => 'J', 'data' => '2026-06-22 18:00', 'mandante' => 'ARG', 'visitante' => 'AUT'],
            ['rodada' => 2, 'grupo' => 'J', 'data' => '2026-06-22 21:00', 'mandante' => 'JOR', 'visitante' => 'ALG'],
            ['rodada' => 2, 'grupo' => 'L', 'data' => '2026-06-23 15:00', 'mandante' => 'ENG', 'visitante' => 'GHA'],
            ['rodada' => 2, 'grupo' => 'L', 'data' => '2026-06-23 18:00', 'mandante' => 'PAN', 'visitante' => 'CRO'],
            ['rodada' => 2, 'grupo' => 'K', 'data' => '2026-06-23 18:00', 'mandante' => 'POR', 'visitante' => 'UZB'],
            ['rodada' => 2, 'grupo' => 'K', 'data' => '2026-06-23 21:00', 'mandante' => 'COL', 'visitante' => 'IC1'],

            // Rodada 3
            ['rodada' => 3, 'grupo' => 'C', 'data' => '2026-06-24 18:00', 'mandante' => 'SCO', 'visitante' => 'BRA'],
            ['rodada' => 3, 'grupo' => 'C', 'data' => '2026-06-24 18:00', 'mandante' => 'MAR', 'visitante' => 'HAI'],
            ['rodada' => 3, 'grupo' => 'B', 'data' => '2026-06-24 18:00', 'mandante' => 'SUI', 'visitante' => 'CAN'],
            ['rodada' => 3, 'grupo' => 'B', 'data' => '2026-06-24 18:00', 'mandante' => 'UA1', 'visitante' => 'QAT'],
            ['rodada' => 3, 'grupo' => 'A', 'data' => '2026-06-24 21:00', 'mandante' => 'UD4', 'visitante' => 'MEX'],
            ['rodada' => 3, 'grupo' => 'A', 'data' => '2026-06-24 21:00', 'mandante' => 'RSA', 'visitante' => 'KOR'],
            ['rodada' => 3, 'grupo' => 'E', 'data' => '2026-06-25 18:00', 'mandante' => 'CUW', 'visitante' => 'CIV'],
            ['rodada' => 3, 'grupo' => 'E', 'data' => '2026-06-25 18:00', 'mandante' => 'ECU', 'visitante' => 'GER'],
            ['rodada' => 3, 'grupo' => 'F', 'data' => '2026-06-25 18:00', 'mandante' => 'JPN', 'visitante' => 'UB2'],
            ['rodada' => 3, 'grupo' => 'F', 'data' => '2026-06-25 18:00', 'mandante' => 'TUN', 'visitante' => 'NED'],
            ['rodada' => 3, 'grupo' => 'D', 'data' => '2026-06-25 21:00', 'mandante' => 'UC3', 'visitante' => 'USA'],
            ['rodada' => 3, 'grupo' => 'D', 'data' => '2026-06-25 21:00', 'mandante' => 'PAR', 'visitante' => 'AUS'],
            ['rodada' => 3, 'grupo' => 'I', 'data' => '2026-06-26 18:00', 'mandante' => 'NOR', 'visitante' => 'FRA'],
            ['rodada' => 3, 'grupo' => 'I', 'data' => '2026-06-26 18:00', 'mandante' => 'SEN', 'visitante' => 'IC2'],
            ['rodada' => 3, 'grupo' => 'G', 'data' => '2026-06-26 18:00', 'mandante' => 'EGY', 'visitante' => 'IRN'],
            ['rodada' => 3, 'grupo' => 'G', 'data' => '2026-06-26 18:00', 'mandante' => 'NZL', 'visitante' => 'BEL'],
            ['rodada' => 3, 'grupo' => 'H', 'data' => '2026-06-26 21:00', 'mandante' => 'CPV', 'visitante' => 'KSA'],
            ['rodada' => 3, 'grupo' => 'H', 'data' => '2026-06-26 21:00', 'mandante' => 'URU', 'visitante' => 'ESP'],
            ['rodada' => 3, 'grupo' => 'L', 'data' => '2026-06-27 18:00', 'mandante' => 'PAN', 'visitante' => 'ENG'],
            ['rodada' => 3, 'grupo' => 'L', 'data' => '2026-06-27 18:00', 'mandante' => 'CRO', 'visitante' => 'GHA'],
            ['rodada' => 3, 'grupo' => 'J', 'data' => '2026-06-27 18:00', 'mandante' => 'ALG', 'visitante' => 'AUT'],
            ['rodada' => 3, 'grupo' => 'J', 'data' => '2026-06-27 18:00', 'mandante' => 'JOR', 'visitante' => 'ARG'],
            ['rodada' => 3, 'grupo' => 'K', 'data' => '2026-06-27 21:00', 'mandante' => 'COL', 'visitante' => 'POR'],
            ['rodada' => 3, 'grupo' => 'K', 'data' => '2026-06-27 21:00', 'mandante' => 'IC1', 'visitante' => 'UZB'],
        ];

        $rodadas = [1 => $rodada1, 2 => $rodada2, 3 => $rodada3];
        $ordemJogo = 1;

        foreach ($jogosGrupos as $jogo) {
            Jogo::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'fase_id' => $faseGrupos->id, 'ordem_na_fase' => $ordemJogo],
                [
                    'rodada_id' => $rodadas[$jogo['rodada']]->id,
                    'grupo_id' => $grupos[$jogo['grupo']]->id,
                    'selecao_mandante_id' => $selecoes[$jogo['mandante']]->id,
                    'selecao_visitante_id' => $selecoes[$jogo['visitante']]->id,
                    'data_hora_inicio' => $jogo['data'],
                    'status' => 'agendado',
                ],
            );
            $ordemJogo++;
        }

        $jogosEliminatorios = [
            ['fase' => $roundOf32, 'data' => '2026-06-28 16:00', 'ordem' => 1],
            ['fase' => $roundOf32, 'data' => '2026-06-28 20:00', 'ordem' => 2],
            ['fase' => $roundOf32, 'data' => '2026-06-29 16:00', 'ordem' => 3],
            ['fase' => $roundOf32, 'data' => '2026-06-29 20:00', 'ordem' => 4],
            ['fase' => $roundOf32, 'data' => '2026-06-30 16:00', 'ordem' => 5],
            ['fase' => $roundOf32, 'data' => '2026-06-30 20:00', 'ordem' => 6],
            ['fase' => $roundOf32, 'data' => '2026-07-01 16:00', 'ordem' => 7],
            ['fase' => $roundOf32, 'data' => '2026-07-01 20:00', 'ordem' => 8],
            ['fase' => $roundOf32, 'data' => '2026-07-02 16:00', 'ordem' => 9],
            ['fase' => $roundOf32, 'data' => '2026-07-02 20:00', 'ordem' => 10],
            ['fase' => $roundOf32, 'data' => '2026-07-03 16:00', 'ordem' => 11],
            ['fase' => $roundOf32, 'data' => '2026-07-03 20:00', 'ordem' => 12],
            ['fase' => $roundOf32, 'data' => '2026-07-04 16:00', 'ordem' => 13],
            ['fase' => $roundOf32, 'data' => '2026-07-04 20:00', 'ordem' => 14],
            ['fase' => $roundOf32, 'data' => '2026-07-05 16:00', 'ordem' => 15],
            ['fase' => $roundOf32, 'data' => '2026-07-05 20:00', 'ordem' => 16],

            ['fase' => $oitavas, 'data' => '2026-07-06 16:00', 'ordem' => 1],
            ['fase' => $oitavas, 'data' => '2026-07-06 20:00', 'ordem' => 2],
            ['fase' => $oitavas, 'data' => '2026-07-07 16:00', 'ordem' => 3],
            ['fase' => $oitavas, 'data' => '2026-07-07 20:00', 'ordem' => 4],
            ['fase' => $oitavas, 'data' => '2026-07-08 16:00', 'ordem' => 5],
            ['fase' => $oitavas, 'data' => '2026-07-08 20:00', 'ordem' => 6],
            ['fase' => $oitavas, 'data' => '2026-07-09 16:00', 'ordem' => 7],
            ['fase' => $oitavas, 'data' => '2026-07-09 20:00', 'ordem' => 8],

            ['fase' => $quartas, 'data' => '2026-07-10 16:00', 'ordem' => 1],
            ['fase' => $quartas, 'data' => '2026-07-10 20:00', 'ordem' => 2],
            ['fase' => $quartas, 'data' => '2026-07-11 16:00', 'ordem' => 3],
            ['fase' => $quartas, 'data' => '2026-07-11 20:00', 'ordem' => 4],

            ['fase' => $semifinais, 'data' => '2026-07-14 16:00', 'ordem' => 1],
            ['fase' => $semifinais, 'data' => '2026-07-15 16:00', 'ordem' => 2],

            ['fase' => $terceiroLugar, 'data' => '2026-07-18 15:00', 'ordem' => 1],
            ['fase' => $final, 'data' => '2026-07-19 16:00', 'ordem' => 1],
        ];

        foreach ($jogosEliminatorios as $jogo) {
            Jogo::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'fase_id' => $jogo['fase']->id, 'ordem_na_fase' => $jogo['ordem']],
                [
                    'rodada_id' => null,
                    'grupo_id' => null,
                    'selecao_mandante_id' => null,
                    'selecao_visitante_id' => null,
                    'data_hora_inicio' => $jogo['data'],
                    'status' => 'agendado',
                ],
            );
        }

        // ── Regras de Pontuacao ────────────────────────────────

        $regras = [
            ['fase' => $faseGrupos, 'chave' => 'placar_exato_fase_grupos', 'nome' => 'Placar Exato', 'descricao' => 'Acertou o placar exato do jogo', 'pontos' => 10],
            ['fase' => $faseGrupos, 'chave' => 'vencedor_e_acertou_gols', 'nome' => 'Vencedor + Acertou Gols BR', 'descricao' => 'Acertou o vencedor e a quantidade de gols de um dos times', 'pontos' => 7],
            ['fase' => $faseGrupos, 'chave' => 'apenas_vencedor', 'nome' => 'Apenas O Vencedor', 'descricao' => 'Acertou apenas quem venceu ou que empatou', 'pontos' => 5],
            ['fase' => $faseGrupos, 'chave' => 'empate_sem_placar', 'nome' => 'Empate Sem Placar Exato', 'descricao' => 'Acertou que houve empate mas errou o placar', 'pontos' => 5],
            ['fase' => $faseGrupos, 'chave' => 'acertou_1_placar', 'nome' => 'Acertou 1 Placar, Errou O Resultado', 'descricao' => 'Acertou a quantidade de gols de um time mas errou o resultado', 'pontos' => 2],
            ['fase' => $faseGrupos, 'chave' => 'errou_tudo', 'nome' => 'Errou Tudo', 'descricao' => 'Errou tudo', 'pontos' => 0],
            ['fase' => null, 'chave' => 'primeiro_colocado_grupo', 'nome' => 'Primeiro colocado do grupo', 'descricao' => 'Acertou o primeiro colocado de um grupo', 'pontos' => 8],
            ['fase' => null, 'chave' => 'segundo_colocado_grupo', 'nome' => 'Segundo colocado do grupo', 'descricao' => 'Acertou o segundo colocado de um grupo', 'pontos' => 6],
            ['fase' => null, 'chave' => 'artilheiro', 'nome' => 'Artilheiro', 'descricao' => 'Acertou o artilheiro da Copa', 'pontos' => 20],
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
                [
                    'torneio_id' => $torneio->id,
                    'fase_id' => $regra['fase']?->id,
                    'chave' => $regra['chave'],
                ],
                [
                    'nome' => $regra['nome'],
                    'descricao' => $regra['descricao'],
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
