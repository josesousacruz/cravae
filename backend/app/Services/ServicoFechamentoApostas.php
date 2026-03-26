<?php

namespace App\Services;

use App\Models\Cupom;
use App\Models\Jogo;
use App\Models\Torneio;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ServicoFechamentoApostas
{
    public function validar(Cupom $cupom, array $dados): void
    {
        if ($cupom->status !== 'ativo') {
            throw ValidationException::withMessages([
                'cupom' => 'O cupom precisa estar ativo para receber apostas.',
            ]);
        }

        $dataFechamento = $this->resolverDataFechamento($dados);

        if ($dataFechamento && now()->greaterThanOrEqualTo($dataFechamento)) {
            throw ValidationException::withMessages([
                'apostas' => 'O prazo desta aposta ja foi encerrado.',
            ]);
        }
    }

    private function resolverDataFechamento(array $dados): ?Carbon
    {
        $tipo = $dados['tipo'];

        if (in_array($tipo, ['placar_jogo_grupos', 'placar_jogo_eliminatoria'], true)) {
            $jogo = Jogo::query()->with(['fase', 'rodada'])->findOrFail($dados['jogo_id']);

            if ($jogo->fase?->tipo === 'grupos') {
                return $jogo->rodada?->data_fechamento ?? $jogo->data_hora_inicio?->copy()->subHour();
            }

            return $jogo->fase?->data_fechamento ?? $jogo->data_hora_inicio?->copy()->subHour();
        }

        $torneio = Torneio::query()->with('fases')->findOrFail($dados['torneio_id']);

        if (in_array($tipo, ['classificacao_grupo', 'artilheiro'], true)) {
            return $torneio->data_inicio?->copy()->subHour();
        }

        if (in_array($tipo, ['campeao', 'vice_campeao', 'terceiro_colocado'], true)) {
            return $torneio->fases
                ->where('tipo', '!=', 'grupos')
                ->sortBy('ordem')
                ->first()?->data_fechamento;
        }

        return null;
    }
}
