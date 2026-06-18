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
        // O cupom pode receber apostas mesmo aguardando pagamento: a cobranca e
        // recebida por fora do sistema, entao o pagamento nao bloqueia palpites.
        if (! in_array($cupom->status, ['ativo', 'aguardando_pagamento'], true)) {
            throw ValidationException::withMessages([
                'cupom' => 'Este cupom nao pode receber apostas.',
            ]);
        }
    }

    public function prazoEncerrado(array $dados): bool
    {
        $dataFechamento = $this->resolverDataFechamento($dados);

        return $dataFechamento !== null && now()->greaterThanOrEqualTo($dataFechamento);
    }

    private function resolverDataFechamento(array $dados): ?Carbon
    {
        $tipo = $dados['tipo'];

        if (in_array($tipo, ['placar_jogo_grupos', 'placar_jogo_eliminatoria'], true)) {
            $jogo = Jogo::query()->with(['fase', 'rodada'])->findOrFail($dados['jogo_id']);

            if ($jogo->fase?->tipo === 'grupos') {
                // Os palpites de grupos fecham POR DIA, 1h antes do primeiro jogo do dia.
                // data_fechamento da rodada, se definido, e um override opcional.
                return $jogo->rodada?->data_fechamento ?? $this->fechamentoDoDia($jogo);
            }

            return $jogo->data_hora_inicio;
        }

        $torneio = Torneio::query()->with('fases')->findOrFail($dados['torneio_id']);

        if ($tipo === 'artilheiro') {
            return $torneio->data_inicio?->copy()->subHour();
        }

        if ($tipo === 'podio') {
            // O palpite de campeao/vice/3o fecha no FIM da fase de grupos: 1h antes do
            // primeiro jogo do mata-mata. Para bolao so de mata-mata, e antes do 1o jogo.
            $primeiroMataMata = Jogo::query()
                ->where('torneio_id', $torneio->id)
                ->whereHas('fase', fn ($query) => $query->where('tipo', '!=', 'grupos'))
                ->whereNotNull('data_hora_inicio')
                ->min('data_hora_inicio');

            return $primeiroMataMata
                ? Carbon::parse($primeiroMataMata)->subHour()
                : $torneio->data_inicio?->copy()->subHour();
        }

        return null;
    }

    /**
     * Fechamento por dia: 1h antes do primeiro jogo agendado para o mesmo dia
     * (mesma data civil) do jogo informado, dentro do torneio.
     */
    private function fechamentoDoDia(Jogo $jogo): ?Carbon
    {
        if (! $jogo->data_hora_inicio) {
            return null;
        }

        $query = Jogo::query()
            ->where('torneio_id', $jogo->torneio_id)
            ->whereDate('data_hora_inicio', $jogo->data_hora_inicio->toDateString());

        // Cada rodada fecha por dia de forma independente: considera apenas os jogos
        // da mesma rodada, para que um jogo de outra rodada no mesmo dia nao interfira.
        if ($jogo->rodada_id) {
            $query->where('rodada_id', $jogo->rodada_id);
        }

        $primeiroDoDia = $query->min('data_hora_inicio');

        $referencia = $primeiroDoDia ? Carbon::parse($primeiroDoDia) : $jogo->data_hora_inicio;

        return $referencia->copy()->subHour();
    }
}
