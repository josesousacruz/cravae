<?php

namespace App\Services;

use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;
use Illuminate\Support\Collection;

class ServicoResultadosTorneio
{
    /**
     * @return array{mandante:?Selecao,visitante:?Selecao}
     */
    public function participantesDoJogo(Jogo $jogo): array
    {
        $torneio = $jogo->relationLoaded('torneio')
            ? $jogo->torneio
            : $jogo->torneio()->with([
                'grupos' => fn ($query) => $query->orderBy('ordem'),
                'grupos.selecoes',
                'fases' => fn ($query) => $query->orderBy('ordem'),
                'jogos' => fn ($query) => $query->orderBy('ordem_na_fase'),
                'jogos.fase',
                'jogos.resultado',
                'jogos.selecaoMandante',
                'jogos.selecaoVisitante',
            ])->first();

        if (! $torneio) {
            return ['mandante' => null, 'visitante' => null];
        }

        $participantes = $this->participantesPorJogo($torneio);

        return $participantes[$jogo->id] ?? ['mandante' => null, 'visitante' => null];
    }

    /**
     * @return array{campeao_selecao_id:?int,vice_campeao_selecao_id:?int,terceiro_colocado_selecao_id:?int}
     */
    public function resolverPodio(Torneio $torneio): array
    {
        $torneio->loadMissing([
            'grupos' => fn ($query) => $query->orderBy('ordem'),
            'grupos.selecoes',
            'fases' => fn ($query) => $query->orderBy('ordem'),
            'jogos' => fn ($query) => $query->orderBy('ordem_na_fase'),
            'jogos.fase',
            'jogos.resultado',
            'jogos.selecaoMandante',
            'jogos.selecaoVisitante',
        ]);

        $participantes = $this->participantesPorJogo($torneio);
        $final = $torneio->jogos->first(fn (Jogo $jogo) => $jogo->fase?->slug === 'final');
        $terceiroLugar = $torneio->jogos->first(fn (Jogo $jogo) => $jogo->fase?->slug === 'terceiro_lugar');

        $campeao = $final?->resultado?->selecao_classificada_id;
        $vice = $final && $campeao
            ? collect([
                $participantes[$final->id]['mandante'] ?? null,
                $participantes[$final->id]['visitante'] ?? null,
            ])->first(fn (?Selecao $selecao) => $selecao && (int) $selecao->id !== (int) $campeao)
            : null;
        $terceiro = $terceiroLugar?->resultado?->selecao_classificada_id;

        return [
            'campeao_selecao_id' => $campeao ? (int) $campeao : null,
            'vice_campeao_selecao_id' => $vice?->id ? (int) $vice->id : null,
            'terceiro_colocado_selecao_id' => $terceiro ? (int) $terceiro : null,
        ];
    }

    /**
     * @return array<int, array{mandante:?Selecao,visitante:?Selecao}>
     */
    public function participantesPorJogo(Torneio $torneio): array
    {
        $torneio->loadMissing([
            'grupos' => fn ($query) => $query->orderBy('ordem'),
            'grupos.selecoes',
            'fases' => fn ($query) => $query->orderBy('ordem'),
            'jogos' => fn ($query) => $query->orderBy('ordem_na_fase'),
            'jogos.fase',
            'jogos.resultado',
            'jogos.selecaoMandante',
            'jogos.selecaoVisitante',
        ]);

        $resultados = $torneio->jogos->keyBy('id');
        $jogosGrupos = $torneio->jogos->where('fase.tipo', 'grupos');
        $gruposCompletos = $jogosGrupos->every(fn (Jogo $jogo) => $jogo->resultado?->placar_mandante !== null && $jogo->resultado?->placar_visitante !== null);
        $classificacao = $gruposCompletos ? $this->calcularClassificacaoGrupos($torneio) : ['grupos' => [], 'terceiros_qualificados' => []];
        $slotTerceiros = $gruposCompletos ? $this->resolverSlotsDeTerceiros($classificacao['terceiros_qualificados']) : [];

        /** @var Collection<string, Collection<int, Jogo>> $porFase */
        $porFase = $torneio->jogos
            ->filter(fn (Jogo $jogo) => $jogo->fase?->tipo !== 'grupos')
            ->sortBy([
                fn (Jogo $jogo) => $jogo->fase->ordem,
                fn (Jogo $jogo) => $jogo->ordem_na_fase,
            ])
            ->groupBy(fn (Jogo $jogo) => $jogo->fase->slug);

        $participantes = [];
        $fasesEliminatorias = $torneio->fases->where('tipo', '!=', 'grupos')->sortBy('ordem')->values();

        foreach ($fasesEliminatorias as $fase) {
            $faseAnteriorCompleta = match ($fase->slug) {
                'round_of_32' => $gruposCompletos,
                'oitavas_de_final' => $this->faseCompleta($porFase, 'round_of_32'),
                'quartas_de_final' => $this->faseCompleta($porFase, 'oitavas_de_final'),
                'semifinais' => $this->faseCompleta($porFase, 'quartas_de_final'),
                'terceiro_lugar', 'final' => $this->faseCompleta($porFase, 'semifinais'),
                default => false,
            };

            foreach (($porFase[$fase->slug] ?? collect())->values() as $indice => $jogo) {
                $ordem = $indice + 1;

                // Fonte da verdade = API: se o jogo já tem os times persistidos
                // (espelho da TheSportsDB), usa-os direto, sem derivar dos grupos.
                if ($jogo->selecaoMandante && $jogo->selecaoVisitante) {
                    $participantes[$jogo->id] = [
                        'mandante' => $jogo->selecaoMandante,
                        'visitante' => $jogo->selecaoVisitante,
                    ];
                    continue;
                }

                if (! $faseAnteriorCompleta) {
                    $participantes[$jogo->id] = ['mandante' => null, 'visitante' => null];
                    continue;
                }

                [$mandante, $visitante] = match ($fase->slug) {
                    'round_of_32' => $this->resolverParticipantesRoundOf32($ordem, $classificacao, $slotTerceiros),
                    'oitavas_de_final' => $this->resolverParticipantesPorResultados($porFase, $participantes, 'round_of_32', [
                        1 => [1, 2], 2 => [3, 4], 3 => [5, 6], 4 => [7, 8],
                        5 => [9, 10], 6 => [11, 12], 7 => [13, 14], 8 => [15, 16],
                    ], $ordem),
                    'quartas_de_final' => $this->resolverParticipantesPorResultados($porFase, $participantes, 'oitavas_de_final', [
                        1 => [1, 2], 2 => [3, 4], 3 => [5, 6], 4 => [7, 8],
                    ], $ordem),
                    'semifinais' => $this->resolverParticipantesPorResultados($porFase, $participantes, 'quartas_de_final', [
                        1 => [1, 2], 2 => [3, 4],
                    ], $ordem),
                    'final' => $this->resolverParticipantesPorResultados($porFase, $participantes, 'semifinais', [
                        1 => [1, 2],
                    ], $ordem),
                    'terceiro_lugar' => [
                        $this->resolverPerdedorDaSemifinal($porFase, $participantes, 1),
                        $this->resolverPerdedorDaSemifinal($porFase, $participantes, 2),
                    ],
                    default => [$resultados[$jogo->id]?->selecaoMandante, $resultados[$jogo->id]?->selecaoVisitante],
                };

                $participantes[$jogo->id] = [
                    'mandante' => $mandante,
                    'visitante' => $visitante,
                ];
            }
        }

        return $participantes;
    }

    private function faseCompleta(Collection $porFase, string $slugFase): bool
    {
        $jogos = ($porFase[$slugFase] ?? collect())->values();

        return $jogos->isNotEmpty()
            && $jogos->every(fn (Jogo $jogo) => $jogo->resultado?->selecao_classificada_id !== null);
    }

    /**
     * @return array{
     *   grupos: array<string, array<int, array{grupo:string,posicao:int,selecao:Selecao,pontos:int,saldo:int,gols_pro:int,vitorias:int}> >,
     *   terceiros_qualificados: array<int, array{grupo:string,posicao:int,selecao:Selecao,pontos:int,saldo:int,gols_pro:int,vitorias:int}>
     * }
     */
    private function calcularClassificacaoGrupos(Torneio $torneio): array
    {
        $grupos = [];

        foreach ($torneio->grupos as $grupo) {
            $tabela = [];

            foreach ($grupo->selecoes as $selecao) {
                $tabela[$selecao->id] = [
                    'grupo' => $this->letraGrupo($grupo->nome),
                    'posicao' => 0,
                    'selecao' => $selecao,
                    'pontos' => 0,
                    'saldo' => 0,
                    'gols_pro' => 0,
                    'vitorias' => 0,
                ];
            }

            $jogos = $torneio->jogos
                ->where('grupo_id', $grupo->id)
                ->where('fase.tipo', 'grupos');

            foreach ($jogos as $jogo) {
                $resultado = $jogo->resultado;

                if (! $resultado) {
                    continue;
                }

                $placarMandante = (int) ($resultado->placar_mandante ?? 0);
                $placarVisitante = (int) ($resultado->placar_visitante ?? 0);

                $mandante = &$tabela[$jogo->selecao_mandante_id];
                $visitante = &$tabela[$jogo->selecao_visitante_id];

                $mandante['gols_pro'] += $placarMandante;
                $visitante['gols_pro'] += $placarVisitante;
                $mandante['saldo'] += $placarMandante - $placarVisitante;
                $visitante['saldo'] += $placarVisitante - $placarMandante;

                if ($placarMandante > $placarVisitante) {
                    $mandante['pontos'] += 3;
                    $mandante['vitorias'] += 1;
                } elseif ($placarVisitante > $placarMandante) {
                    $visitante['pontos'] += 3;
                    $visitante['vitorias'] += 1;
                } else {
                    $mandante['pontos'] += 1;
                    $visitante['pontos'] += 1;
                }
            }

            $classificados = collect($tabela)
                ->sortBy([
                    ['pontos', 'desc'],
                    ['saldo', 'desc'],
                    ['gols_pro', 'desc'],
                    ['vitorias', 'desc'],
                    fn (array $item) => $item['selecao']->nome,
                ])
                ->values()
                ->map(function (array $item, int $index) {
                    $item['posicao'] = $index + 1;

                    return $item;
                })
                ->all();

            $grupos[$this->letraGrupo($grupo->nome)] = $classificados;
        }

        $terceiros = collect($grupos)
            ->map(fn (array $grupo) => $grupo[2] ?? null)
            ->filter()
            ->sortBy([
                ['pontos', 'desc'],
                ['saldo', 'desc'],
                ['gols_pro', 'desc'],
                ['vitorias', 'desc'],
                fn (array $item) => $item['grupo'],
            ])
            ->take(8)
            ->values()
            ->all();

        return [
            'grupos' => $grupos,
            'terceiros_qualificados' => $terceiros,
        ];
    }

    /**
     * @param array<int, array{grupo:string,posicao:int,selecao:Selecao,pontos:int,saldo:int,gols_pro:int,vitorias:int}> $terceiros
     * @return array<int, Selecao>
     */
    private function resolverSlotsDeTerceiros(array $terceiros): array
    {
        $gruposQualificados = collect($terceiros)->map(fn (array $item) => $item['grupo'])->values()->all();
        $porGrupo = collect($terceiros)->keyBy(fn (array $item) => $item['grupo']);

        $slots = [
            1 => ['A', 'B', 'C', 'D', 'F'],
            2 => ['C', 'D', 'F', 'G', 'H'],
            3 => ['B', 'E', 'F', 'I', 'J'],
            4 => ['A', 'E', 'H', 'I', 'J'],
            5 => ['C', 'E', 'F', 'H', 'I'],
            6 => ['E', 'H', 'I', 'J', 'K'],
            7 => ['E', 'F', 'G', 'I', 'J'],
            8 => ['D', 'E', 'I', 'J', 'L'],
        ];

        $atribuicoes = [];
        $usados = [];

        $resolver = function (int $slot) use (&$resolver, $slots, $porGrupo, $gruposQualificados, &$atribuicoes, &$usados): bool {
            if ($slot > count($slots)) {
                return true;
            }

            $candidatos = collect($gruposQualificados)
                ->filter(fn (string $grupo) => in_array($grupo, $slots[$slot], true) && ! in_array($grupo, $usados, true))
                ->values()
                ->all();

            foreach ($candidatos as $grupo) {
                $atribuicoes[$slot] = $porGrupo[$grupo]['selecao'];
                $usados[] = $grupo;

                if ($resolver($slot + 1)) {
                    return true;
                }

                unset($atribuicoes[$slot]);
                $usados = array_values(array_filter($usados, fn (string $item) => $item !== $grupo));
            }

            return false;
        };

        $resolver(1);

        return $atribuicoes;
    }

    /**
     * @param array<string, array<int, array{grupo:string,posicao:int,selecao:Selecao,pontos:int,saldo:int,gols_pro:int,vitorias:int}>> $classificacao
     * @param array<int, Selecao> $slotTerceiros
     * @return array{0:?Selecao,1:?Selecao}
     */
    private function resolverParticipantesRoundOf32(int $ordem, array $classificacao, array $slotTerceiros): array
    {
        $primeiros = fn (string $grupo) => $classificacao['grupos'][$grupo][0]['selecao'] ?? null;
        $segundos = fn (string $grupo) => $classificacao['grupos'][$grupo][1]['selecao'] ?? null;

        return match ($ordem) {
            1 => [$primeiros('E'), $slotTerceiros[1] ?? null],
            2 => [$primeiros('I'), $slotTerceiros[2] ?? null],
            3 => [$segundos('A'), $segundos('B')],
            4 => [$primeiros('F'), $segundos('C')],
            5 => [$segundos('K'), $segundos('L')],
            6 => [$primeiros('H'), $segundos('J')],
            7 => [$primeiros('D'), $slotTerceiros[3] ?? null],
            8 => [$primeiros('G'), $slotTerceiros[4] ?? null],
            9 => [$primeiros('C'), $segundos('F')],
            10 => [$segundos('E'), $segundos('I')],
            11 => [$primeiros('A'), $slotTerceiros[5] ?? null],
            12 => [$primeiros('L'), $slotTerceiros[6] ?? null],
            13 => [$primeiros('J'), $segundos('H')],
            14 => [$segundos('D'), $segundos('G')],
            15 => [$primeiros('B'), $slotTerceiros[7] ?? null],
            16 => [$primeiros('K'), $slotTerceiros[8] ?? null],
            default => [null, null],
        };
    }

    /**
     * @param Collection<string, Collection<int, Jogo>> $porFase
     * @param array<int, array{mandante:?Selecao,visitante:?Selecao}> $participantes
     * @param array<int, array{0:int,1:int}> $mapa
     * @return array{0:?Selecao,1:?Selecao}
     */
    private function resolverParticipantesPorResultados(Collection $porFase, array $participantes, string $faseOrigem, array $mapa, int $ordem): array
    {
        [$origemMandante, $origemVisitante] = $mapa[$ordem] ?? [null, null];

        return [
            $origemMandante ? $this->resolverVencedorReal($porFase, $participantes, $faseOrigem, $origemMandante) : null,
            $origemVisitante ? $this->resolverVencedorReal($porFase, $participantes, $faseOrigem, $origemVisitante) : null,
        ];
    }

    /**
     * @param Collection<string, Collection<int, Jogo>> $porFase
     * @param array<int, array{mandante:?Selecao,visitante:?Selecao}> $participantes
     */
    private function resolverVencedorReal(Collection $porFase, array $participantes, string $faseSlug, int $ordem): ?Selecao
    {
        /** @var Jogo|null $jogo */
        $jogo = ($porFase[$faseSlug] ?? collect())->values()->get($ordem - 1);

        if (! $jogo) {
            return null;
        }

        $classificadoId = $jogo->resultado?->selecao_classificada_id;
        if (! $classificadoId) {
            return null;
        }

        return collect([
            $participantes[$jogo->id]['mandante'] ?? null,
            $participantes[$jogo->id]['visitante'] ?? null,
        ])->first(fn (?Selecao $selecao) => $selecao && (int) $selecao->id === (int) $classificadoId);
    }

    /**
     * @param Collection<string, Collection<int, Jogo>> $porFase
     * @param array<int, array{mandante:?Selecao,visitante:?Selecao}> $participantes
     */
    private function resolverPerdedorDaSemifinal(Collection $porFase, array $participantes, int $ordem): ?Selecao
    {
        /** @var Jogo|null $semifinal */
        $semifinal = ($porFase['semifinais'] ?? collect())->values()->get($ordem - 1);

        if (! $semifinal) {
            return null;
        }

        $classificadoId = $semifinal->resultado?->selecao_classificada_id;
        $mandante = $participantes[$semifinal->id]['mandante'] ?? null;
        $visitante = $participantes[$semifinal->id]['visitante'] ?? null;

        if (! $classificadoId || (! $mandante && ! $visitante)) {
            return null;
        }

        return (int) $mandante?->id === (int) $classificadoId ? $visitante : $mandante;
    }

    private function letraGrupo(string $nomeGrupo): string
    {
        return (string) preg_replace('/^Grupo\s+/i', '', $nomeGrupo);
    }
}
