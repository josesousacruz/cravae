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

        $bonusGoleadores = $this->pontuarGoleadores($aposta, $regras, $placarExato);

        if ($placarExato) {
            $pontos = $this->obterPontosRegra($regras, 'placar_exato_fase_grupos', $aposta->fase_id);
            $this->registrarEvento($aposta, $regras, 'placar_exato_fase_grupos', $pontos, 'Placar exato da fase de grupos');

            return [$pontos + $bonusGoleadores, true];
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

            return [$pontos + $bonusGoleadores, false];
        }

        if ($acertouVencedor) {
            $chave = $this->resolverResultadoPartida($resultado->placar_mandante, $resultado->placar_visitante) === 'empate'
                ? 'empate_sem_placar'
                : 'apenas_vencedor';
            $descricao = $chave === 'empate_sem_placar' ? 'Empate sem placar exato' : 'Apenas vencedor';
            $pontos = $this->obterPontosRegra($regras, $chave, $aposta->fase_id);
            $this->registrarEvento($aposta, $regras, $chave, $pontos, $descricao);

            return [$pontos + $bonusGoleadores, false];
        }

        if ($golsMandanteCorretos || $golsVisitanteCorretos) {
            $pontos = $this->obterPontosRegra($regras, 'acertou_1_placar', $aposta->fase_id);
            $this->registrarEvento($aposta, $regras, 'acertou_1_placar', $pontos, 'Acertou um placar');

            return [$pontos + $bonusGoleadores, false];
        }

        return [$bonusGoleadores, false];
    }

    /**
     * Bônus dos goleadores de um palpite de placar. Compara os autores
     * palpitados com os reais como MULTICONJUNTO por lado (a ordem dos gols
     * não importa): cada acerto vale a regra `goleador_acertado`. Se o placar
     * é exato e TODOS os gols reais foram atribuídos corretamente (0x0 conta:
     * zero autores é trivialmente correto), soma a regra
     * `placar_exato_goleadores`. Lado real sem lista de goleadores (sync ainda
     * não trouxe a timeline) só é considerado conhecido quando o placar do
     * lado é 0.
     */
    private function pontuarGoleadores(Aposta $aposta, Collection $regras, bool $placarExato): int
    {
        $resultado = $aposta->jogo?->resultado;
        $conteudo = $aposta->conteudo;

        $ladoConhecido = function (?array $reais, int $placarLado): ?array {
            if (is_array($reais)) {
                return $reais;
            }

            return $placarLado === 0 ? [] : null;
        };

        $reaisMandante = $ladoConhecido($resultado->goleadores_mandante, (int) $resultado->placar_mandante);
        $reaisVisitante = $ladoConhecido($resultado->goleadores_visitante, (int) $resultado->placar_visitante);

        $acertos = 0;
        $ladosCompletos = 0;

        foreach ([
            [$conteudo['goleadores_mandante'] ?? [], $reaisMandante],
            [$conteudo['goleadores_visitante'] ?? [], $reaisVisitante],
        ] as [$palpite, $reais]) {
            if ($reais === null) {
                continue; // Gols reais ainda desconhecidos: sem bônus neste lado.
            }

            $contagemPalpite = $this->contagemGoleadores(is_array($palpite) ? $palpite : []);
            $contagemReal = $this->contagemGoleadoresReais($reais);

            $acertosLado = 0;
            foreach ($contagemPalpite as $chave => $quantidade) {
                $acertosLado += min($quantidade, $contagemReal[$chave] ?? 0);
            }

            $acertos += $acertosLado;

            if ($contagemPalpite === $contagemReal) {
                $ladosCompletos++;
            }
        }

        $bonus = 0;

        if ($acertos > 0) {
            $pontosPorGol = $this->obterPontosRegra($regras, 'goleador_acertado', $aposta->fase_id);
            if ($pontosPorGol > 0) {
                $bonus += $acertos * $pontosPorGol;
                $this->registrarEvento(
                    $aposta,
                    $regras,
                    'goleador_acertado',
                    $acertos * $pontosPorGol,
                    "Goleadores acertados ({$acertos})",
                );
            }
        }

        $todosConhecidos = $reaisMandante !== null && $reaisVisitante !== null;

        if ($placarExato && $todosConhecidos && $ladosCompletos === 2) {
            $pontosCheio = $this->obterPontosRegra($regras, 'placar_exato_goleadores', $aposta->fase_id);
            if ($pontosCheio > 0) {
                $bonus += $pontosCheio;
                $this->registrarEvento($aposta, $regras, 'placar_exato_goleadores', $pontosCheio, 'Placar exato com todos os goleadores');
            }
        }

        return $bonus;
    }

    /**
     * Multiconjunto do palpite: ['j:123' => 2, 'contra' => 1]. Nulls (autor
     * não palpitado) ficam de fora.
     *
     * @param  array<int, int|string|null>  $palpite
     * @return array<string, int>
     */
    private function contagemGoleadores(array $palpite): array
    {
        $contagem = [];

        foreach ($palpite as $item) {
            if ($item === null || $item === '') {
                continue;
            }
            $chave = $item === 'contra' ? 'contra' : 'j:'.(int) $item;
            $contagem[$chave] = ($contagem[$chave] ?? 0) + 1;
        }

        ksort($contagem);

        return $contagem;
    }

    /**
     * Multiconjunto do resultado real. Formato persistido pelo sync/admin:
     * [{"jogador_id": int|null, "contra": bool}, ...] — um item por gol.
     *
     * @param  array<int, mixed>  $reais
     * @return array<string, int>
     */
    private function contagemGoleadoresReais(array $reais): array
    {
        $contagem = [];

        foreach ($reais as $gol) {
            if (! is_array($gol)) {
                continue;
            }
            $chave = ($gol['contra'] ?? false) ? 'contra' : 'j:'.(int) ($gol['jogador_id'] ?? 0);
            $contagem[$chave] = ($contagem[$chave] ?? 0) + 1;
        }

        ksort($contagem);

        return $contagem;
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
