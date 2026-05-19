<?php

namespace App\Services;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ServicoBracketCupom
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function gerar(Cupom $cupom): array
    {
        $torneio = $this->carregarTorneioDoCupom($cupom);

        if (! $torneio) {
            return [];
        }

        $apostas = $cupom->apostas->keyBy(fn (Aposta $aposta) => $aposta->tipo.'-'.$aposta->jogo_id);
        $classificacao = $this->calcularClassificacaoGrupos($torneio, $apostas);
        $gruposCompletos = $cupom->apostas()
            ->where('tipo', 'placar_jogo_grupos')
            ->distinct('jogo_id')
            ->count('jogo_id') >= $torneio->jogos->where('fase.tipo', 'grupos')->count();
        $slotTerceiros = $gruposCompletos
            ? $this->resolverSlotsDeTerceiros($classificacao['terceiros_qualificados'])
            : [];

        $jogosPorFase = $torneio->jogos
            ->where('fase.tipo', '!=', 'grupos')
            ->sortBy([
                fn (Jogo $jogo) => $jogo->fase->ordem,
                fn (Jogo $jogo) => $jogo->ordem_na_fase,
            ])
            ->groupBy(fn (Jogo $jogo) => $jogo->fase->slug);
        $jogosIdsPorFase = $jogosPorFase
            ->map(fn (Collection $jogos) => $jogos->sortBy('ordem_na_fase')->pluck('id')->values()->all())
            ->all();
        $fasesEliminatorias = $torneio->fases->where('tipo', '!=', 'grupos')->sortBy('ordem')->values();
        $primeiraEliminatoria = $fasesEliminatorias->first();
        $faseAnteriorPorId = [];
        foreach ($fasesEliminatorias as $indice => $faseEliminatoria) {
            $faseAnteriorPorId[$faseEliminatoria->id] = $indice > 0 ? $fasesEliminatorias[$indice - 1] : null;
        }

        $participantesPorJogo = [];
        $bracket = [];

        foreach ($fasesEliminatorias as $fase) {
            /** @var Collection<int, Jogo> $jogosDaFase */
            $jogosDaFase = $jogosPorFase->get($fase->slug, collect())->sortBy('ordem_na_fase')->values();

            foreach ($jogosDaFase as $indice => $jogo) {
                $ordem = $indice + 1;
                [$mandante, $visitante] = match ($fase->slug) {
                    'round_of_32' => $gruposCompletos
                        ? $this->resolverParticipantesRoundOf32($ordem, $classificacao, $slotTerceiros)
                        : [null, null],
                    'oitavas_de_final' => $this->resolverParticipantesPorVencedores($participantesPorJogo, $apostas, $jogosIdsPorFase, $fase->slug, $ordem),
                    'quartas_de_final' => $this->resolverParticipantesPorVencedores($participantesPorJogo, $apostas, $jogosIdsPorFase, $fase->slug, $ordem),
                    'semifinais' => $this->resolverParticipantesPorVencedores($participantesPorJogo, $apostas, $jogosIdsPorFase, $fase->slug, $ordem),
                    'terceiro_lugar' => $this->resolverParticipantesTerceiroLugar($participantesPorJogo, $apostas, $jogosIdsPorFase),
                    'final' => $this->resolverParticipantesPorVencedores($participantesPorJogo, $apostas, $jogosIdsPorFase, $fase->slug, $ordem),
                    default => [null, null],
                };

                $participantesPorJogo[$jogo->id] = [
                    'mandante' => $mandante,
                    'visitante' => $visitante,
                ];

                $bracket[] = $this->serializarJogoDerivado(
                    $jogo,
                    $mandante,
                    $visitante,
                    $cupom,
                    $apostas,
                    $torneio,
                    $jogosIdsPorFase,
                    $primeiraEliminatoria?->id,
                    $faseAnteriorPorId,
                );
            }
        }

        return $bracket;
    }

    /**
     * @return array{mandante:?Selecao,visitante:?Selecao}
     */
    public function participantesDoJogo(Cupom $cupom, Jogo $jogo): array
    {
        $partida = collect($this->gerar($cupom))->firstWhere('id', $jogo->id);

        return [
            'mandante' => $partida['selecao_mandante'] ?? null,
            'visitante' => $partida['selecao_visitante'] ?? null,
        ];
    }

    /**
     * @return array{campeao_selecao_id:?int,vice_campeao_selecao_id:?int,terceiro_colocado_selecao_id:?int}
     */
    public function resumo(Cupom $cupom): array
    {
        $cupom->loadMissing('apostas');

        $bracket = collect($this->gerar($cupom));
        $apostas = $cupom->apostas->keyBy(fn (Aposta $aposta) => $aposta->tipo.'-'.$aposta->jogo_id);
        $final = $bracket->first(fn (array $jogo) => $jogo['fase']->slug === 'final');
        $terceiroLugar = $bracket->first(fn (array $jogo) => $jogo['fase']->slug === 'terceiro_lugar');
        $apostaFinal = $final ? $apostas->get('placar_jogo_eliminatoria-'.$final['id']) : null;
        $apostaTerceiro = $terceiroLugar ? $apostas->get('placar_jogo_eliminatoria-'.$terceiroLugar['id']) : null;

        $campeaoId = (int) ($apostaFinal?->conteudo['selecao_classificada_id'] ?? 0) ?: null;
        $viceId = $final && $campeaoId
            ? collect([
                $final['selecao_mandante']?->id,
                $final['selecao_visitante']?->id,
            ])->first(fn (?int $id) => $id && $id !== $campeaoId)
            : null;
        $terceiroId = (int) ($apostaTerceiro?->conteudo['selecao_classificada_id'] ?? 0) ?: null;

        return [
            'campeao_selecao_id' => $campeaoId,
            'vice_campeao_selecao_id' => $viceId,
            'terceiro_colocado_selecao_id' => $terceiroId,
        ];
    }

    private function carregarTorneioDoCupom(Cupom $cupom): ?Torneio
    {
        $torneioId = $cupom->apostas()->value('torneio_id')
            ?? Jogo::query()->whereHas('fase', fn ($query) => $query->where('tipo', 'grupos'))->value('torneio_id');

        if (! $torneioId) {
            return null;
        }

        $cupom->loadMissing(['apostas.jogo.fase']);

        return Torneio::query()
            ->with([
                'grupos' => fn ($query) => $query->orderBy('ordem'),
                'grupos.selecoes',
                'fases' => fn ($query) => $query->orderBy('ordem'),
                'jogos' => fn ($query) => $query->orderBy('fase_id')->orderBy('ordem_na_fase'),
                'jogos.fase',
                'jogos.rodada',
                'jogos.grupo',
                'jogos.selecaoMandante',
                'jogos.selecaoVisitante',
                'jogos.resultado',
            ])
            ->find($torneioId);
    }

    /**
     * @param Collection<string, Aposta> $apostas
     * @return array{
     *   grupos: array<string, array<int, array{grupo:string,posicao:int,selecao:Selecao,pontos:int,saldo:int,gols_pro:int,vitorias:int}> >,
     *   terceiros_qualificados: array<int, array{grupo:string,posicao:int,selecao:Selecao,pontos:int,saldo:int,gols_pro:int,vitorias:int}>
     * }
     */
    private function calcularClassificacaoGrupos(Torneio $torneio, Collection $apostas): array
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
                $aposta = $apostas->get('placar_jogo_grupos-'.$jogo->id);

                if (! $aposta) {
                    continue;
                }

                $placarMandante = (int) ($aposta->conteudo['placar_mandante'] ?? 0);
                $placarVisitante = (int) ($aposta->conteudo['placar_visitante'] ?? 0);

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

        if (! $resolver(1)) {
            throw ValidationException::withMessages([
                'apostas' => 'Nao foi possivel montar os confrontos dos melhores terceiros para este cupom.',
            ]);
        }

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
     * @param array<int, array{mandante:?Selecao,visitante:?Selecao}> $participantesPorJogo
     * @param Collection<string, Aposta> $apostas
     * @param array<string, array<int, int>> $jogosIdsPorFase
     * @return array{0:?Selecao,1:?Selecao}
     */
    private function resolverParticipantesPorVencedores(array $participantesPorJogo, Collection $apostas, array $jogosIdsPorFase, string $faseSlug, int $ordem): array
    {
        $faseOrigem = match ($faseSlug) {
            'oitavas_de_final' => 'round_of_32',
            'quartas_de_final' => 'oitavas_de_final',
            'semifinais' => 'quartas_de_final',
            'final' => 'semifinais',
            default => null,
        };

        $mapa = match ($faseSlug) {
            'oitavas_de_final' => [
                1 => [1, 2],
                2 => [3, 4],
                3 => [5, 6],
                4 => [7, 8],
                5 => [9, 10],
                6 => [11, 12],
                7 => [13, 14],
                8 => [15, 16],
            ],
            'quartas_de_final' => [
                1 => [1, 2],
                2 => [3, 4],
                3 => [5, 6],
                4 => [7, 8],
            ],
            'semifinais' => [
                1 => [1, 2],
                2 => [3, 4],
            ],
            'final' => [
                1 => [1, 2],
            ],
            default => [],
        };

        [$origemMandante, $origemVisitante] = $mapa[$ordem] ?? [null, null];

        return [
            $origemMandante && $faseOrigem ? $this->resolverVencedorDaPartida($participantesPorJogo, $apostas, $jogosIdsPorFase, $faseOrigem, $origemMandante) : null,
            $origemVisitante && $faseOrigem ? $this->resolverVencedorDaPartida($participantesPorJogo, $apostas, $jogosIdsPorFase, $faseOrigem, $origemVisitante) : null,
        ];
    }

    /**
     * @param array<int, array{mandante:?Selecao,visitante:?Selecao}> $participantesPorJogo
     * @param Collection<string, Aposta> $apostas
     * @param array<string, array<int, int>> $jogosIdsPorFase
     * @return array{0:?Selecao,1:?Selecao}
     */
    private function resolverParticipantesTerceiroLugar(array $participantesPorJogo, Collection $apostas, array $jogosIdsPorFase): array
    {
        return [
            $this->resolverPerdedorDaSemifinal($participantesPorJogo, $apostas, $jogosIdsPorFase, 1),
            $this->resolverPerdedorDaSemifinal($participantesPorJogo, $apostas, $jogosIdsPorFase, 2),
        ];
    }

    /**
     * @param array<int, array{mandante:?Selecao,visitante:?Selecao}> $participantesPorJogo
     * @param Collection<string, Aposta> $apostas
     * @param array<string, array<int, int>> $jogosIdsPorFase
     */
    private function resolverVencedorDaPartida(array $participantesPorJogo, Collection $apostas, array $jogosIdsPorFase, string $faseSlug, int $ordem): ?Selecao
    {
        $jogoId = $this->resolverJogoIdPorOrigem($jogosIdsPorFase, $faseSlug, $ordem);

        if (! $jogoId) {
            return null;
        }

        $aposta = $apostas->get('placar_jogo_eliminatoria-'.$jogoId);
        $participantes = $participantesPorJogo[$jogoId] ?? null;
        $classificadoId = (int) ($aposta?->conteudo['selecao_classificada_id'] ?? 0);

        if (! $participantes || $classificadoId <= 0) {
            return null;
        }

        return collect([$participantes['mandante'], $participantes['visitante']])
            ->first(fn (?Selecao $selecao) => $selecao?->id === $classificadoId);
    }

    /**
     * @param array<int, array{mandante:?Selecao,visitante:?Selecao}> $participantesPorJogo
     * @param Collection<string, Aposta> $apostas
     * @param array<string, array<int, int>> $jogosIdsPorFase
     */
    private function resolverPerdedorDaSemifinal(array $participantesPorJogo, Collection $apostas, array $jogosIdsPorFase, int $ordemSemifinal): ?Selecao
    {
        $jogoId = $this->resolverJogoIdPorOrigem($jogosIdsPorFase, 'semifinais', $ordemSemifinal);

        if (! $jogoId) {
            return null;
        }

        $aposta = $apostas->get('placar_jogo_eliminatoria-'.$jogoId);
        $participantes = $participantesPorJogo[$jogoId] ?? null;
        $classificadoId = (int) ($aposta?->conteudo['selecao_classificada_id'] ?? 0);

        if (! $participantes || $classificadoId <= 0) {
            return null;
        }

        return collect([$participantes['mandante'], $participantes['visitante']])
            ->first(fn (?Selecao $selecao) => $selecao && $selecao->id !== $classificadoId);
    }

    /**
     * @param array<string, array<int, int>> $jogosIdsPorFase
     */
    private function resolverJogoIdPorOrigem(array $jogosIdsPorFase, string $faseSlug, int $ordem): ?int
    {
        return $jogosIdsPorFase[$faseSlug][$ordem - 1] ?? null;
    }

    /**
     * @param Collection<string, Aposta> $apostas
     * @return array<string, mixed>
     */
    private function serializarJogoDerivado(
        Jogo $jogo,
        ?Selecao $mandante,
        ?Selecao $visitante,
        Cupom $cupom,
        Collection $apostas,
        Torneio $torneio,
        array $jogosIdsPorFase,
        ?int $primeiraEliminatoriaId,
        array $faseAnteriorPorId,
    ): array
    {
        $bloqueado = $this->faseBloqueadaNoCupom($cupom, $jogo, $apostas, $torneio, $jogosIdsPorFase, $primeiraEliminatoriaId, $faseAnteriorPorId);

        return [
            'id' => $jogo->id,
            'jogo_base_id' => $jogo->id,
            'fase_id' => $jogo->fase_id,
            'rodada_id' => $jogo->rodada_id,
            'grupo_id' => $jogo->grupo_id,
            'data_hora_inicio' => optional($jogo->data_hora_inicio)->toISOString(),
            'ordem_na_fase' => $jogo->ordem_na_fase,
            'status' => $jogo->status,
            'fase' => $jogo->fase,
            'rodada' => $jogo->rodada,
            'grupo' => $jogo->grupo,
            'selecao_mandante' => $mandante,
            'selecao_visitante' => $visitante,
            'resultado' => $jogo->resultado,
            'bloqueado' => $bloqueado,
            'motivo_bloqueio' => $bloqueado ? 'Fase ainda bloqueada para este cupom.' : null,
        ];
    }

    /**
     * @param Collection<string, Aposta> $apostas
     */
    private function faseBloqueadaNoCupom(
        Cupom $cupom,
        Jogo $jogo,
        Collection $apostas,
        Torneio $torneio,
        array $jogosIdsPorFase,
        ?int $primeiraEliminatoriaId,
        array $faseAnteriorPorId,
    ): bool
    {
        $faseAtual = $jogo->fase;
        if (! $faseAtual || $faseAtual->tipo === 'grupos') {
            return false;
        }

        if (! $primeiraEliminatoriaId) {
            return false;
        }

        if ($faseAtual->id === $primeiraEliminatoriaId) {
            $totalJogosGrupos = $torneio->jogos->where('fase.tipo', 'grupos')->count();
            $totalPalpitesGrupos = $cupom->apostas()
                ->where('tipo', 'placar_jogo_grupos')
                ->distinct('jogo_id')
                ->count('jogo_id');

            return $totalPalpitesGrupos < $totalJogosGrupos;
        }

        $faseAnterior = $faseAnteriorPorId[$faseAtual->id] ?? null;

        if (! $faseAnterior) {
            return false;
        }

        $totalJogosFaseAnterior = count($jogosIdsPorFase[$faseAnterior->slug] ?? []);

        $totalPalpitesFaseAnterior = $cupom->apostas()
            ->where('tipo', 'placar_jogo_eliminatoria')
            ->where('fase_id', $faseAnterior->id)
            ->distinct('jogo_id')
            ->count('jogo_id');

        return $totalPalpitesFaseAnterior < $totalJogosFaseAnterior;
    }

    private function letraGrupo(string $nomeGrupo): string
    {
        return (string) preg_replace('/^Grupo\s+/i', '', $nomeGrupo);
    }
}
