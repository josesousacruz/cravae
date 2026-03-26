<?php

namespace App\Services;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\EventoPontuacao;
use App\Models\Grupo;
use App\Models\PontuacaoCupom;
use App\Models\RegraPontuacao;
use App\Models\Torneio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ServicoPontuacao
{
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

                if ($aposta->tipo === 'classificacao_grupo') {
                    $total += $this->pontuarClassificacaoGrupo($aposta, $regras);
                    continue;
                }

                if ($aposta->tipo === 'artilheiro') {
                    $pontos = $this->pontuarArtilheiro($aposta, $torneio, $regras);
                    $total += $pontos;
                    $palpitesFinaisCorretos += $pontos > 0 ? 1 : 0;
                    continue;
                }

                if (in_array($aposta->tipo, ['campeao', 'vice_campeao', 'terceiro_colocado'], true)) {
                    $pontos = $this->pontuarResultadoTorneio($aposta, $torneio, $regras);
                    $total += $pontos;
                    $palpitesFinaisCorretos += $pontos > 0 ? 1 : 0;
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

        $acertouVencedor = $this->resolverResultadoPartida(
            (int) $conteudo['placar_mandante'],
            (int) $conteudo['placar_visitante'],
        ) === $this->resolverResultadoPartida($resultado->placar_mandante, $resultado->placar_visitante);

        if (! $acertouVencedor) {
            return [0, false];
        }

        $pontos = $this->obterPontosRegra($regras, 'vencedor_fase_grupos', $aposta->fase_id);
        $this->registrarEvento($aposta, $regras, 'vencedor_fase_grupos', $pontos, 'Vencedor da fase de grupos');

        return [$pontos, false];
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
            $this->registrarEvento($aposta, $regras, 'classificado_e_placar_mata_mata', $pontos, 'Classificado e placar do mata-mata');

            return [$pontos, true, true];
        }

        if ($classificadoCorreto) {
            $pontos = $this->obterPontosRegra($regras, 'classificado_mata_mata', $aposta->fase_id);
            $this->registrarEvento($aposta, $regras, 'classificado_mata_mata', $pontos, 'Classificado do mata-mata');

            return [$pontos, false, true];
        }

        return [0, false, false];
    }

    private function pontuarClassificacaoGrupo(Aposta $aposta, Collection $regras): int
    {
        $grupo = Grupo::query()
            ->with(['selecoes', 'jogos.resultado'])
            ->find($aposta->grupo_id);

        if (! $grupo) {
            return 0;
        }

        $classificacao = $this->calcularClassificacaoGrupo($grupo);

        if (count($classificacao) < 2) {
            return 0;
        }

        $pontos = 0;
        $conteudo = $aposta->conteudo;

        if ((int) ($conteudo['primeiro_colocado_id'] ?? 0) === $classificacao[0]) {
            $valor = $this->obterPontosRegra($regras, 'primeiro_colocado_grupo');
            $this->registrarEvento($aposta, $regras, 'primeiro_colocado_grupo', $valor, 'Primeiro colocado do grupo');
            $pontos += $valor;
        }

        if ((int) ($conteudo['segundo_colocado_id'] ?? 0) === $classificacao[1]) {
            $valor = $this->obterPontosRegra($regras, 'segundo_colocado_grupo');
            $this->registrarEvento($aposta, $regras, 'segundo_colocado_grupo', $valor, 'Segundo colocado do grupo');
            $pontos += $valor;
        }

        return $pontos;
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

    private function pontuarResultadoTorneio(Aposta $aposta, Torneio $torneio, Collection $regras): int
    {
        $campoResultado = match ($aposta->tipo) {
            'campeao' => 'campeao_selecao_id',
            'vice_campeao' => 'vice_campeao_selecao_id',
            default => 'terceiro_colocado_selecao_id',
        };

        if ((int) ($aposta->conteudo['selecao_id'] ?? 0) !== (int) ($torneio->resultadoTorneio?->{$campoResultado} ?? 0)) {
            return 0;
        }

        $pontos = $this->obterPontosRegra($regras, $aposta->tipo);
        $this->registrarEvento($aposta, $regras, $aposta->tipo, $pontos, 'Palpite final do torneio');

        return $pontos;
    }

    /**
     * @return array<int, int>
     */
    private function calcularClassificacaoGrupo(Grupo $grupo): array
    {
        $estatisticas = [];

        foreach ($grupo->selecoes as $selecao) {
            $estatisticas[$selecao->id] = [
                'id' => $selecao->id,
                'pontos' => 0,
                'saldo' => 0,
                'gols_marcados' => 0,
                'nome' => $selecao->nome,
            ];
        }

        foreach ($grupo->jogos as $jogo) {
            $resultado = $jogo->resultado;

            if (! $resultado || $resultado->placar_mandante === null || $resultado->placar_visitante === null) {
                continue;
            }

            $mandanteId = $jogo->selecao_mandante_id;
            $visitanteId = $jogo->selecao_visitante_id;

            $estatisticas[$mandanteId]['gols_marcados'] += $resultado->placar_mandante;
            $estatisticas[$visitanteId]['gols_marcados'] += $resultado->placar_visitante;
            $estatisticas[$mandanteId]['saldo'] += $resultado->placar_mandante - $resultado->placar_visitante;
            $estatisticas[$visitanteId]['saldo'] += $resultado->placar_visitante - $resultado->placar_mandante;

            if ($resultado->placar_mandante > $resultado->placar_visitante) {
                $estatisticas[$mandanteId]['pontos'] += 3;
            } elseif ($resultado->placar_mandante < $resultado->placar_visitante) {
                $estatisticas[$visitanteId]['pontos'] += 3;
            } else {
                $estatisticas[$mandanteId]['pontos'] += 1;
                $estatisticas[$visitanteId]['pontos'] += 1;
            }
        }

        $ordenadas = array_values($estatisticas);

        usort($ordenadas, function (array $a, array $b) {
            return [$b['pontos'], $b['saldo'], $b['gols_marcados'], $a['nome']]
                <=>
                [$a['pontos'], $a['saldo'], $a['gols_marcados'], $b['nome']];
        });

        return array_column($ordenadas, 'id');
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
}
