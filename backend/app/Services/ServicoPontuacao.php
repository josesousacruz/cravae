<?php

namespace App\Services;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\EventoPontuacao;
use App\Models\PontuacaoCupom;
use App\Models\RegraPontuacao;
use App\Models\Torneio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServicoPontuacao
{
    public function __construct()
    {
    }

    public function recalcularTorneio(Torneio $torneio): void
    {
        Cupom::query()
            ->whereHas('apostas', fn ($query) => $query->where('torneio_id', $torneio->id))
            ->with(['apostas.jogo.resultado', 'pontuacao'])
            ->get()
            ->each(fn (Cupom $cupom) => $this->recalcularCupom($cupom, $torneio));
    }

    public function recalcularCupom(Cupom $cupom, ?Torneio $torneio = null): void
    {
        if (! $torneio) {
            $torneioId = $cupom->apostas()->value('torneio_id');
            $torneio = $torneioId
                ? Torneio::query()
                    ->with(['resultadoTorneio', 'regrasPontuacao', 'grupos.selecoes', 'jogos.resultado', 'jogos.fase'])
                    ->find($torneioId)
                : null;
        }

        if (! $torneio) {
            return;
        }

        $cupom->loadMissing(['apostas.jogo.resultado', 'pontuacao']);
        $regras = RegraPontuacao::query()
            ->where('torneio_id', $torneio->id)
            ->where('ativo', true)
            ->get();

        DB::transaction(function () use ($cupom, $torneio, $regras) {
            EventoPontuacao::query()->where('cupom_id', $cupom->id)->delete();

            $total = 0;
            $placaresExatos = 0;
            $classificadosCorretos = 0;
            $palpitesFinaisCorretos = 0;

            foreach ($cupom->apostas as $aposta) {
                if ($aposta->tipo === 'placar_jogo_grupos') {
                    [$pontos, $exact] = $this->pontuarJogoGrupo($aposta, $regras);
                    $total += $pontos;
                    $placaresExatos += $exact ? 1 : 0;
                    continue;
                }

                if ($aposta->tipo === 'placar_jogo_eliminatoria') {
                    [$pontos, $exact, $classificado] = $this->pontuarJogoEliminatoria($aposta, $regras);
                    $total += $pontos;
                    $placaresExatos += $exact ? 1 : 0;
                    $classificadosCorretos += $classificado ? 1 : 0;
                    continue;
                }

                if ($aposta->tipo === 'artilheiro') {
                    $pontos = $this->pontuarArtilheiro($aposta, $torneio, $regras);
                    $total += $pontos;
                    $palpitesFinaisCorretos += $pontos > 0 ? 1 : 0;
                    continue;
                }

                if ($aposta->tipo === 'podio') {
                    $resultadoPodio = $this->pontuarPodio($aposta, $torneio, $regras);
                    $total += $resultadoPodio['pontos'];
                    $palpitesFinaisCorretos += $resultadoPodio['acertos'];
                }
            }

            PontuacaoCupom::query()->updateOrCreate(
                ['cupom_id' => $cupom->id],
                [
                    'pontuacao_total' => $total,
                    'quantidade_placares_exatos' => $placaresExatos,
                    'quantidade_classificados_corretos' => $classificadosCorretos,
                    'quantidade_palpites_finais_corretos' => $palpitesFinaisCorretos,
                    'ultimo_recalculo_at' => now(),
                ],
            );
        });
    }

    /**
     * @return array{0:int,1:bool}
     */
    private function pontuarJogoGrupo(Aposta $aposta, Collection $regras): array
    {
        $resultado = $aposta->jogo?->resultado;

        if (! $resultado || $resultado->placar_mandante === null || $resultado->placar_visitante === null) {
            return [0, false];
        }

        $conteudo = $aposta->conteudo;
        $placarExato = (int) $conteudo['placar_mandante'] === $resultado->placar_mandante
            && (int) $conteudo['placar_visitante'] === $resultado->placar_visitante;

        if ($placarExato) {
            $pontos = $this->obterPontosRegra($regras, 'placar_exato_fase_grupos', $aposta->fase_id);
            $this->registrarEvento($aposta, $regras, 'placar_exato_fase_grupos', $pontos, 'Placar exato da fase de grupos');

            return [$pontos, true];
        }

        $golsMandanteCorretos = (int) $conteudo['placar_mandante'] === (int) $resultado->placar_mandante;
        $golsVisitanteCorretos = (int) $conteudo['placar_visitante'] === (int) $resultado->placar_visitante;
        $acertouVencedor = $this->resolverResultadoPartida(
            (int) $conteudo['placar_mandante'],
            (int) $conteudo['placar_visitante'],
        ) === $this->resolverResultadoPartida($resultado->placar_mandante, $resultado->placar_visitante);

        if ($acertouVencedor && ($golsMandanteCorretos || $golsVisitanteCorretos)) {
            $pontos = $this->obterPontosRegra($regras, 'vencedor_e_acertou_gols', $aposta->fase_id);
            $this->registrarEvento($aposta, $regras, 'vencedor_e_acertou_gols', $pontos, 'Vencedor e gols de um dos times');

            return [$pontos, false];
        }

        if ($acertouVencedor) {
            $chave = $this->resolverResultadoPartida($resultado->placar_mandante, $resultado->placar_visitante) === 'empate'
                ? 'empate_sem_placar'
                : 'apenas_vencedor';
            $descricao = $chave === 'empate_sem_placar' ? 'Empate sem placar exato' : 'Apenas vencedor';
            $pontos = $this->obterPontosRegra($regras, $chave, $aposta->fase_id);
            $this->registrarEvento($aposta, $regras, $chave, $pontos, $descricao);

            return [$pontos, false];
        }

        if ($golsMandanteCorretos || $golsVisitanteCorretos) {
            $pontos = $this->obterPontosRegra($regras, 'acertou_1_placar', $aposta->fase_id);
            $this->registrarEvento($aposta, $regras, 'acertou_1_placar', $pontos, 'Acertou um placar');

            return [$pontos, false];
        }

        return [0, false];
    }

    /**
     * @return array{0:int,1:bool,2:bool}
     */
    private function pontuarJogoEliminatoria(Aposta $aposta, Collection $regras): array
    {
        $resultado = $aposta->jogo?->resultado;

        if (! $resultado || $resultado->selecao_classificada_id === null) {
            return [0, false, false];
        }

        $conteudo = $aposta->conteudo;
        $classificadoCorreto = (int) ($conteudo['selecao_classificada_id'] ?? 0) === (int) $resultado->selecao_classificada_id;
        $placarExato = (int) $conteudo['placar_mandante'] === $resultado->placar_mandante
            && (int) $conteudo['placar_visitante'] === $resultado->placar_visitante;

        if ($classificadoCorreto && $placarExato) {
            $pontos = $this->obterPontosRegra($regras, 'classificado_e_placar_mata_mata', $aposta->fase_id);
            if ($pontos <= 0) {
                $pontos = $this->obterPontosRegra($regras, 'classificado_mata_mata', $aposta->fase_id);
                $this->registrarEvento($aposta, $regras, 'classificado_mata_mata', $pontos, 'Classificado do mata-mata');
            } else {
                $this->registrarEvento($aposta, $regras, 'classificado_e_placar_mata_mata', $pontos, 'Classificado e placar do mata-mata');
            }

            return [$pontos, true, true];
        }

        if ($classificadoCorreto) {
            $pontos = $this->obterPontosRegra($regras, 'classificado_mata_mata', $aposta->fase_id);
            $this->registrarEvento($aposta, $regras, 'classificado_mata_mata', $pontos, 'Classificado do mata-mata');

            return [$pontos, false, true];
        }

        return [0, false, false];
    }

    private function pontuarArtilheiro(Aposta $aposta, Torneio $torneio, Collection $regras): int
    {
        if ((int) ($aposta->conteudo['jogador_id'] ?? 0) !== (int) $torneio->resultadoTorneio?->artilheiro_jogador_id) {
            return 0;
        }

        $pontos = $this->obterPontosRegra($regras, 'artilheiro');
        $this->registrarEvento($aposta, $regras, 'artilheiro', $pontos, 'Artilheiro do torneio');

        return $pontos;
    }

    /**
     * @return array{pontos:int,acertos:int}
     */
    private function pontuarPodio(Aposta $aposta, Torneio $torneio, Collection $regras): array
    {
        $conteudo = $aposta->conteudo;
        $previsto = [
            'campeao' => (int) ($conteudo['campeao_selecao_id'] ?? 0) ?: null,
            'vice' => (int) ($conteudo['vice_selecao_id'] ?? 0) ?: null,
            'terceiro' => (int) ($conteudo['terceiro_selecao_id'] ?? 0) ?: null,
        ];
        $real = $this->resolverPodioReal($torneio);

        $total = 0;
        $acertos = 0;

        foreach ([
            'campeao' => 'campeao',
            'vice' => 'vice_campeao',
            'terceiro' => 'terceiro_colocado',
        ] as $campo => $regra) {
            if (! $previsto[$campo] || ! $real[$campo] || $previsto[$campo] !== $real[$campo]) {
                continue;
            }

            $pontos = $this->obterPontosRegra($regras, $regra);
            $regraId = $this->resolverIdRegra($regras, $regra);
            if ($pontos <= 0 || $regraId <= 0) {
                continue;
            }

            $total += $pontos;
            $acertos++;
            EventoPontuacao::query()->create([
                'cupom_id' => $aposta->cupom_id,
                'regra_pontuacao_id' => $regraId,
                'jogo_id' => null,
                'aposta_id' => $aposta->id,
                'pontos' => $pontos,
                'descricao' => 'Palpite de podio ('.$regra.')',
            ]);
        }

        return ['pontos' => $total, 'acertos' => $acertos];
    }

    /**
     * @return array{campeao:?int,vice:?int,terceiro:?int}
     */
    private function resolverPodioReal(Torneio $torneio): array
    {
        return [
            'campeao' => $torneio->resultadoTorneio?->campeao_selecao_id,
            'vice' => $torneio->resultadoTorneio?->vice_campeao_selecao_id,
            'terceiro' => $torneio->resultadoTorneio?->terceiro_colocado_selecao_id,
        ];
    }
    private function resolverResultadoPartida(int $placarMandante, int $placarVisitante): string
    {
        return match (true) {
            $placarMandante > $placarVisitante => 'mandante',
            $placarMandante < $placarVisitante => 'visitante',
            default => 'empate',
        };
    }

    private function obterPontosRegra(Collection $regras, string $chave, ?int $faseId = null): int
    {
        $regra = $regras->first(
            fn (RegraPontuacao $item) => $item->chave === $chave && (int) ($item->fase_id ?? 0) === (int) ($faseId ?? 0),
        );

        if (! $regra) {
            $regra = $regras->first(fn (RegraPontuacao $item) => $item->chave === $chave && $item->fase_id === null);
        }

        return (int) ($regra?->pontos ?? 0);
    }

    private function registrarEvento(Aposta $aposta, Collection $regras, string $chave, int $pontos, string $descricao): void
    {
        if ($pontos <= 0) {
            return;
        }

        $regra = $regras->first(
            fn (RegraPontuacao $item) => $item->chave === $chave && (int) ($item->fase_id ?? 0) === (int) ($aposta->fase_id ?? 0),
        ) ?? $regras->first(fn (RegraPontuacao $item) => $item->chave === $chave && $item->fase_id === null);

        if (! $regra) {
            return;
        }

        EventoPontuacao::query()->create([
            'cupom_id' => $aposta->cupom_id,
            'regra_pontuacao_id' => $regra->id,
            'jogo_id' => $aposta->jogo_id,
            'aposta_id' => $aposta->id,
            'pontos' => $pontos,
            'descricao' => $descricao,
        ]);
    }

    private function resolverIdRegra(Collection $regras, string $chave): int
    {
        return (int) ($regras->first(fn (RegraPontuacao $item) => $item->chave === $chave && $item->fase_id === null)?->id ?? 0);
    }
}
