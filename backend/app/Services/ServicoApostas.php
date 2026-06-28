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
        private readonly ServicoResultadosTorneio $servicoResultadosTorneio,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $itens
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

                // Defesa: o item PRECISA pertencer ao torneio do cupom. Sem isto, um request
                // com jogo/torneio de outro bolao (ex.: UI com estado defasado) gravaria um
                // palpite vazado entre boloes. Ignora em vez de gravar errado.
                if ($cupom->torneio_id && (int) $normalizado['torneio_id'] !== (int) $cupom->torneio_id) {
                    continue;
                }

                $existente = $this->localizarAposta($cupom, $normalizado);

                if ($this->servicoFechamentoApostas->prazoEncerrado($normalizado)) {
                    // Jogo ja fechado: ignora este item (mantem o palpite anterior) em vez de
                    // derrubar o lote inteiro. Cobre tanto o reenvio inalterado do auto-save
                    // quanto uma alteracao no limite do prazo (divergencia de relogio cliente
                    // x servidor) -- assim os demais jogos ainda abertos continuam salvando.
                    continue;
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
     * Remove (limpa) os palpites dos jogos informados, respeitando o prazo de cada um.
     *
     * @param  array<int, int>  $jogoIds
     */
    public function removerLote(Cupom $cupom, Usuario $usuario, array $jogoIds): void
    {
        $jogoIds = array_values(array_unique(array_map('intval', $jogoIds)));

        if ($jogoIds === []) {
            return;
        }

        DB::transaction(function () use ($cupom, $usuario, $jogoIds) {
            $apostas = Aposta::query()
                ->where('cupom_id', $cupom->id)
                ->whereIn('tipo', ['placar_jogo_grupos', 'placar_jogo_eliminatoria'])
                ->whereIn('jogo_id', $jogoIds)
                ->get();

            foreach ($apostas as $aposta) {
                if ($this->servicoFechamentoApostas->prazoEncerrado([
                    'tipo' => $aposta->tipo,
                    'jogo_id' => $aposta->jogo_id,
                    'torneio_id' => $aposta->torneio_id,
                ])) {
                    throw ValidationException::withMessages([
                        'apostas' => 'O prazo desta aposta ja foi encerrado.',
                    ]);
                }

                $conteudoAnterior = $aposta->conteudo;
                $aposta->delete();

                LogAposta::query()->create([
                    'cupom_id' => $cupom->id,
                    'aposta_id' => null,
                    'usuario_id' => $usuario->id,
                    'acao' => 'removida',
                    'conteudo_anterior' => $conteudoAnterior,
                    'conteudo_novo' => null,
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null Null quando o item deve ser ignorado no lote
     *                                   (confronto do mata-mata ainda sem participantes).
     */
    private function normalizarItem(Cupom $cupom, array $item): ?array
    {
        $tipo = $item['tipo'];

        if (in_array($tipo, ['placar_jogo_grupos', 'placar_jogo_eliminatoria'], true)) {
            $jogo = Jogo::query()->with(['fase', 'rodada', 'selecaoMandante', 'selecaoVisitante'])->findOrFail($item['jogo_id']);
            $placarMandante = (int) $item['placar_mandante'];
            $placarVisitante = (int) $item['placar_visitante'];
            $penalMandante = isset($item['penal_mandante']) ? (int) $item['penal_mandante'] : null;
            $penalVisitante = isset($item['penal_visitante']) ? (int) $item['penal_visitante'] : null;

            $selecaoClassificadaId = null;

            if ($tipo === 'placar_jogo_eliminatoria') {
                // Preferir os times persistidos no jogo (2º bolão espelha a API);
                // cai na derivação só quando o jogo ainda não tem times definidos.
                $mandante = $jogo->selecaoMandante;
                $visitante = $jogo->selecaoVisitante;

                if (! $mandante || ! $visitante) {
                    $participantes = $this->servicoResultadosTorneio->participantesDoJogo($jogo);
                    $mandante = $participantes['mandante'];
                    $visitante = $participantes['visitante'];
                }

                if (! $mandante || ! $visitante) {
                    // Participantes reais ainda nao definidos: ignora o item no lote.
                    return null;
                }

                $selecaoClassificadaId = $this->resolverClassificadoEliminatoria(
                    $mandante,
                    $visitante,
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

        if ($tipo === 'podio') {
            return [
                'tipo' => 'podio',
                'torneio_id' => (int) $item['torneio_id'],
                'fase_id' => null,
                'rodada_id' => null,
                'grupo_id' => null,
                'jogo_id' => null,
                'selecao_id' => null,
                'jogador_id' => null,
                'conteudo' => [
                    'campeao_selecao_id' => (int) $item['campeao_selecao_id'],
                    'vice_selecao_id' => (int) $item['vice_selecao_id'],
                    'terceiro_selecao_id' => (int) $item['terceiro_selecao_id'],
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
     * @param  array<string, mixed>  $dados
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
