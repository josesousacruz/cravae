<?php

namespace App\Services;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\LogAposta;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class ServicoApostas
{
    public function __construct(
        private readonly ServicoFechamentoApostas $servicoFechamentoApostas,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     */
    public function salvarLote(Cupom $cupom, Usuario $usuario, array $itens): void
    {
        DB::transaction(function () use ($cupom, $usuario, $itens) {
            foreach ($itens as $item) {
                $normalizado = $this->normalizarItem($item);
                $this->servicoFechamentoApostas->validar($cupom, $normalizado);

                $existente = $this->localizarAposta($cupom, $normalizado);
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
     * @return array<string, mixed>
     */
    private function normalizarItem(array $item): array
    {
        $tipo = $item['tipo'];

        if (in_array($tipo, ['placar_jogo_grupos', 'placar_jogo_eliminatoria'], true)) {
            $jogo = Jogo::query()->with(['fase', 'rodada'])->findOrFail($item['jogo_id']);

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
                    'placar_mandante' => (int) $item['placar_mandante'],
                    'placar_visitante' => (int) $item['placar_visitante'],
                    'selecao_classificada_id' => isset($item['selecao_classificada_id']) ? (int) $item['selecao_classificada_id'] : null,
                ],
            ];
        }

        if ($tipo === 'classificacao_grupo') {
            return [
                'tipo' => $tipo,
                'torneio_id' => (int) $item['torneio_id'],
                'fase_id' => null,
                'rodada_id' => null,
                'grupo_id' => (int) $item['grupo_id'],
                'jogo_id' => null,
                'selecao_id' => null,
                'jogador_id' => null,
                'conteudo' => [
                    'primeiro_colocado_id' => (int) $item['primeiro_colocado_id'],
                    'segundo_colocado_id' => (int) $item['segundo_colocado_id'],
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

        return [
            'tipo' => $tipo,
            'torneio_id' => (int) $item['torneio_id'],
            'fase_id' => null,
            'rodada_id' => null,
            'grupo_id' => null,
            'jogo_id' => null,
            'selecao_id' => (int) $item['selecao_id'],
            'jogador_id' => null,
            'conteudo' => [
                'selecao_id' => (int) $item['selecao_id'],
            ],
        ];
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
