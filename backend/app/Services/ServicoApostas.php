<?php

namespace App\Services;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\LogAposta;
use App\Models\Selecao;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicoApostas
{
    public function __construct(
        private readonly ServicoFechamentoApostas $servicoFechamentoApostas,
        private readonly ServicoBracketCupom $servicoBracketCupom,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     */
    public function salvarLote(Cupom $cupom, Usuario $usuario, array $itens): void
    {
        DB::transaction(function () use ($cupom, $usuario, $itens) {
            foreach ($itens as $item) {
                $normalizado = $this->normalizarItem($cupom, $item);

                if ($normalizado === null) {
                    // Confronto do mata-mata ainda sem participantes resolviveis para este
                    // cupom (a fase anterior nao foi totalmente palpitada). Ignorar este item
                    // em vez de derrubar o lote inteiro, para nao travar as demais alteracoes.
                    continue;
                }

                $existente = $this->localizarAposta($cupom, $normalizado);

                if ($this->servicoFechamentoApostas->prazoEncerrado($normalizado)) {
                    // Jogo ja fechado: reenviar o mesmo palpite (no auto-save em lote)
                    // e ignorado para nao derrubar os jogos ainda abertos. Qualquer
                    // tentativa real de alterar um jogo fechado continua recusada.
                    if ($this->conteudoInalterado($existente, $normalizado)) {
                        continue;
                    }

                    throw ValidationException::withMessages([
                        'apostas' => 'O prazo desta aposta ja foi encerrado.',
                    ]);
                }

                $this->servicoFechamentoApostas->validar($cupom, $normalizado);

                $conteudoAnterior = $existente?->conteudo;

                if ($existente) {
                    $existente->fill($normalizado);
                    $existente->save();
                    $aposta = $existente;
                    $acao = 'editada';
                } else {
                    $aposta = $cupom->apostas()->create($normalizado);
                    $acao = 'criada';
                }

                LogAposta::query()->create([
                    'cupom_id' => $cupom->id,
                    'aposta_id' => $aposta->id,
                    'usuario_id' => $usuario->id,
                    'acao' => $acao,
                    'conteudo_anterior' => $conteudoAnterior,
                    'conteudo_novo' => $aposta->conteudo,
                ]);
            }
        });
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null Null quando o item deve ser ignorado no lote
     *                                   (confronto do mata-mata ainda sem participantes).
     */
    private function normalizarItem(Cupom $cupom, array $item): ?array
    {
        $tipo = $item['tipo'];

        if (in_array($tipo, ['placar_jogo_grupos', 'placar_jogo_eliminatoria'], true)) {
            $jogo = Jogo::query()->with(['fase', 'rodada'])->findOrFail($item['jogo_id']);
            $placarMandante = (int) $item['placar_mandante'];
            $placarVisitante = (int) $item['placar_visitante'];
            $penalMandante = isset($item['penal_mandante']) ? (int) $item['penal_mandante'] : null;
            $penalVisitante = isset($item['penal_visitante']) ? (int) $item['penal_visitante'] : null;

            $selecaoClassificadaId = null;

            if ($tipo === 'placar_jogo_eliminatoria') {
                $participantes = $this->servicoBracketCupom->participantesDoJogo($cupom, $jogo);

                if (! $participantes['mandante'] || ! $participantes['visitante']) {
                    // Sem participantes resolviveis: sinaliza para o lote ignorar o item.
                    return null;
                }

                $selecaoClassificadaId = $this->resolverClassificadoEliminatoria(
                    $participantes['mandante'],
                    $participantes['visitante'],
                    $placarMandante,
                    $placarVisitante,
                    $penalMandante,
                    $penalVisitante,
                );
            }

            return [
                'tipo' => $tipo,
                'torneio_id' => $jogo->torneio_id,
                'fase_id' => $jogo->fase_id,
                'rodada_id' => $jogo->rodada_id,
                'grupo_id' => $jogo->grupo_id,
                'jogo_id' => $jogo->id,
                'selecao_id' => null,
                'jogador_id' => null,
                'conteudo' => [
                    'placar_mandante' => $placarMandante,
                    'placar_visitante' => $placarVisitante,
                    'penal_mandante' => $penalMandante,
                    'penal_visitante' => $penalVisitante,
                    'selecao_classificada_id' => $selecaoClassificadaId,
                ],
            ];
        }

        if ($tipo === 'artilheiro') {
            return [
                'tipo' => $tipo,
                'torneio_id' => (int) $item['torneio_id'],
                'fase_id' => null,
                'rodada_id' => null,
                'grupo_id' => null,
                'jogo_id' => null,
                'selecao_id' => null,
                'jogador_id' => (int) $item['jogador_id'],
                'conteudo' => [
                    'jogador_id' => (int) $item['jogador_id'],
                ],
            ];
        }

        throw ValidationException::withMessages([
            'apostas' => "Tipo de aposta nao suportado: {$tipo}.",
        ]);
    }

    private function resolverClassificadoEliminatoria(
        Selecao $mandante,
        Selecao $visitante,
        int $placarMandante,
        int $placarVisitante,
        ?int $penalMandante,
        ?int $penalVisitante,
    ): int {
        if ($placarMandante > $placarVisitante) {
            return (int) $mandante->id;
        }

        if ($placarVisitante > $placarMandante) {
            return (int) $visitante->id;
        }

        if ($penalMandante === null || $penalVisitante === null || $penalMandante === $penalVisitante) {
            throw ValidationException::withMessages([
                'apostas' => 'Empates no mata-mata exigem penaltis validos.',
            ]);
        }

        return $penalMandante > $penalVisitante
            ? (int) $mandante->id
            : (int) $visitante->id;
    }

    /**
     * @param array<string, mixed> $normalizado
     */
    private function conteudoInalterado(?Aposta $existente, array $normalizado): bool
    {
        return $existente !== null && $existente->conteudo == ($normalizado['conteudo'] ?? null);
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function localizarAposta(Cupom $cupom, array $dados): ?Aposta
    {
        return Aposta::query()
            ->where('cupom_id', $cupom->id)
            ->where('tipo', $dados['tipo'])
            ->where('torneio_id', $dados['torneio_id'])
            ->where('fase_id', $dados['fase_id'])
            ->where('rodada_id', $dados['rodada_id'])
            ->where('grupo_id', $dados['grupo_id'])
            ->where('jogo_id', $dados['jogo_id'])
            ->where('selecao_id', $dados['selecao_id'])
            ->where('jogador_id', $dados['jogador_id'])
            ->first();
    }
}
