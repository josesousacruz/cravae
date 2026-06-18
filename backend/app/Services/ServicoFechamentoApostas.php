<?php

namespace App\Services;

use App\Models\Cupom;
use App\Models\Fase;
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

        $this->validarDesbloqueioProgressivo($cupom, $dados);
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

    private function validarDesbloqueioProgressivo(Cupom $cupom, array $dados): void
    {
        if (($dados['tipo'] ?? null) !== 'placar_jogo_eliminatoria') {
            return;
        }

        $jogo = Jogo::query()->with('fase')->findOrFail($dados['jogo_id']);
        $faseAtual = $jogo->fase;

        if (! $faseAtual) {
            return;
        }

        $primeiraEliminatoria = Fase::query()
            ->where('torneio_id', $jogo->torneio_id)
            ->where('tipo', '!=', 'grupos')
            ->orderBy('ordem')
            ->first();

        if (! $primeiraEliminatoria) {
            return;
        }

        if ($faseAtual->id === $primeiraEliminatoria->id) {
            $totalJogosGrupos = Jogo::query()
                ->where('torneio_id', $jogo->torneio_id)
                ->whereHas('fase', fn ($query) => $query->where('tipo', 'grupos'))
                ->count();

            $totalPalpitesGrupos = $cupom->apostas()
                ->where('tipo', 'placar_jogo_grupos')
                ->distinct('jogo_id')
                ->count('jogo_id');

            if ($totalPalpitesGrupos < $totalJogosGrupos) {
                throw ValidationException::withMessages([
                    'apostas' => 'As eliminatorias so desbloqueiam apos preencher todos os jogos da fase de grupos.',
                ]);
            }

            return;
        }

        $faseAnterior = Fase::query()
            ->where('torneio_id', $jogo->torneio_id)
            ->where('tipo', '!=', 'grupos')
            ->where('ordem', '<', $faseAtual->ordem)
            ->orderByDesc('ordem')
            ->first();

        if (! $faseAnterior) {
            return;
        }

        $totalJogosFaseAnterior = Jogo::query()
            ->where('fase_id', $faseAnterior->id)
            ->count();

        $totalPalpitesFaseAnterior = $cupom->apostas()
            ->where('tipo', 'placar_jogo_eliminatoria')
            ->where('fase_id', $faseAnterior->id)
            ->distinct('jogo_id')
            ->count('jogo_id');

        if ($totalPalpitesFaseAnterior < $totalJogosFaseAnterior) {
            throw ValidationException::withMessages([
                'apostas' => 'Esta fase do mata-mata ainda esta bloqueada para este cupom.',
            ]);
        }
    }
}
