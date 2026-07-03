<?php

namespace App\Services;

use App\Models\Jogador;
use App\Models\LogAposta;
use App\Models\Selecao;
use Illuminate\Support\Carbon;

/**
 * Relatorio de auditoria dos palpites (logs_apostas). Usado pelo painel admin
 * (todos os usuarios) e pelo proprio jogador (restrito ao cupom dele).
 * Logs "orfaos" (aposta apagada => aposta_id nulo) sao o unico rastro de
 * palpites removidos, por isso o escopo permite isola-los.
 */
class ServicoLogsApostas
{
    /**
     * @param array{
     *     usuario_id?: int|null,
     *     torneio_id?: int|null,
     *     cupom_id?: int|null,
     *     jogo_id?: int|null,
     *     acao?: string|null,
     *     escopo?: string|null,
     *     apenas_mudancas?: bool,
     *     de?: string|null,
     *     ate?: string|null,
     * } $filtros
     * @return array{logs: \Illuminate\Support\Collection, pagina: int, total_paginas: int, total: int}
     */
    public function relatorio(array $filtros): array
    {
        $paginado = LogAposta::query()
            ->with([
                'usuario:id,nome,email',
                'cupom:id,codigo,torneio_id',
                'cupom.torneio:id,nome',
                'aposta:id,tipo,jogo_id',
                'aposta.jogo:id,selecao_mandante_id,selecao_visitante_id,data_hora_inicio',
                'aposta.jogo.selecaoMandante:id,nome',
                'aposta.jogo.selecaoVisitante:id,nome',
            ])
            ->when($filtros['usuario_id'] ?? null, fn ($q, $id) => $q->where('usuario_id', $id))
            ->when($filtros['cupom_id'] ?? null, fn ($q, $id) => $q->where('cupom_id', $id))
            ->when($filtros['torneio_id'] ?? null, fn ($q, $id) => $q->whereHas('cupom', fn ($c) => $c->where('torneio_id', $id)))
            ->when($filtros['jogo_id'] ?? null, fn ($q, $id) => $q->whereHas('aposta', fn ($a) => $a->where('jogo_id', $id)))
            ->when($filtros['acao'] ?? null, fn ($q, $acao) => $q->where('acao', $acao))
            ->when(($filtros['escopo'] ?? 'todos') === 'orfaos', fn ($q) => $q->whereNull('aposta_id'))
            ->when(($filtros['escopo'] ?? 'todos') === 'vinculados', fn ($q) => $q->whereNotNull('aposta_id'))
            // "Mudanca real": descarta re-saves do auto-save em que o conteudo nao mudou.
            ->when($filtros['apenas_mudancas'] ?? false, fn ($q) => $q->whereRaw(
                'NOT ((conteudo_anterior IS NULL AND conteudo_novo IS NULL)'
                .' OR (conteudo_anterior IS NOT NULL AND conteudo_novo IS NOT NULL AND conteudo_anterior = conteudo_novo))'
            ))
            // created_at e UTC; os filtros de dia sao no calendario de Brasilia.
            ->when($filtros['de'] ?? null, fn ($q, $de) => $q->where(
                'created_at', '>=', Carbon::parse($de.' 00:00:00', 'America/Sao_Paulo')->utc()
            ))
            ->when($filtros['ate'] ?? null, fn ($q, $ate) => $q->where(
                'created_at', '<', Carbon::parse($ate.' 00:00:00', 'America/Sao_Paulo')->addDay()->utc()
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50);

        /** @var array<int, LogAposta> $itens */
        $itens = $paginado->items();
        $nomes = $this->nomesParaResumo($itens);

        $logs = collect($itens)->map(fn (LogAposta $log) => [
            'id' => $log->id,
            // O Brasil nao tem horario de verao desde 2019: UTC-3 fixo.
            'quando_brt' => $log->created_at->clone()->subHours(3)->format('Y-m-d H:i:s'),
            'acao' => $log->acao,
            'orfao' => $log->aposta_id === null,
            'mudou' => $log->conteudo_anterior != $log->conteudo_novo,
            'usuario' => $log->usuario?->only(['id', 'nome', 'email']),
            'cupom' => $log->cupom?->codigo,
            'bolao' => $log->cupom?->torneio?->nome,
            'tipo' => $log->aposta?->tipo,
            'jogo' => $log->aposta?->jogo ? [
                'id' => $log->aposta->jogo->id,
                'nome' => ($log->aposta->jogo->selecaoMandante?->nome ?? 'A definir')
                    .' x '.($log->aposta->jogo->selecaoVisitante?->nome ?? 'A definir'),
                'data_hora_inicio' => $log->aposta->jogo->data_hora_inicio?->format('Y-m-d H:i'),
            ] : null,
            'resumo_antes' => $this->resumirConteudo($log->conteudo_anterior, $nomes),
            'resumo_depois' => $this->resumirConteudo($log->conteudo_novo, $nomes),
            'antes' => $log->conteudo_anterior,
            'depois' => $log->conteudo_novo,
        ]);

        return [
            'logs' => $logs,
            'pagina' => $paginado->currentPage(),
            'total_paginas' => $paginado->lastPage(),
            'total' => $paginado->total(),
        ];
    }

    /**
     * Nomes de selecoes/jogadores referenciados nos conteudos da pagina, para o
     * resumo legivel (ex.: "classificado: Portugal" em vez de "#41").
     *
     * @param  array<int, LogAposta>  $logs
     * @return array{selecoes: array<int, string>, jogadores: array<int, string>}
     */
    private function nomesParaResumo(array $logs): array
    {
        $selecaoIds = [];
        $jogadorIds = [];

        foreach ($logs as $log) {
            foreach ([$log->conteudo_anterior, $log->conteudo_novo] as $conteudo) {
                foreach ($conteudo ?? [] as $chave => $valor) {
                    if (! is_numeric($valor)) {
                        continue;
                    }
                    if ($chave === 'selecao_classificada_id' || str_ends_with($chave, 'selecao_id')) {
                        $selecaoIds[] = (int) $valor;
                    } elseif ($chave === 'jogador_id') {
                        $jogadorIds[] = (int) $valor;
                    }
                }
            }
        }

        return [
            'selecoes' => $selecaoIds
                ? Selecao::query()->whereIn('id', array_unique($selecaoIds))->pluck('nome', 'id')->all()
                : [],
            'jogadores' => $jogadorIds
                ? Jogador::query()->whereIn('id', array_unique($jogadorIds))->pluck('nome', 'id')->all()
                : [],
        ];
    }

    /**
     * @param  array{selecoes: array<int, string>, jogadores: array<int, string>}  $nomes
     */
    private function resumirConteudo(?array $conteudo, array $nomes): ?string
    {
        if ($conteudo === null) {
            return null;
        }

        if (array_key_exists('placar_mandante', $conteudo)) {
            $resumo = ($conteudo['placar_mandante'] ?? '-').' x '.($conteudo['placar_visitante'] ?? '-');
            if (($conteudo['penal_mandante'] ?? null) !== null || ($conteudo['penal_visitante'] ?? null) !== null) {
                $resumo .= ' (pen. '.($conteudo['penal_mandante'] ?? '-').' x '.($conteudo['penal_visitante'] ?? '-').')';
            }
            $classificadaId = $conteudo['selecao_classificada_id'] ?? null;
            if ($classificadaId) {
                $resumo .= ' | classificado: '.($nomes['selecoes'][$classificadaId] ?? "#{$classificadaId}");
            }

            return $resumo;
        }

        return collect($conteudo)->map(function ($valor, $chave) use ($nomes) {
            if (($chave === 'selecao_classificada_id' || str_ends_with($chave, 'selecao_id')) && isset($nomes['selecoes'][$valor])) {
                $valor = $nomes['selecoes'][$valor];
            } elseif ($chave === 'jogador_id' && isset($nomes['jogadores'][$valor])) {
                $valor = $nomes['jogadores'][$valor];
            }

            return $chave.': '.(is_scalar($valor) ? $valor : json_encode($valor, JSON_UNESCAPED_UNICODE));
        })->implode(' | ');
    }
}
