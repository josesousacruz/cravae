<?php

namespace App\Http\Controllers;

use App\Http\Requests\AtualizarComprasCupomRequest;
use App\Http\Requests\AtualizarFechamentoPodioRequest;
use App\Http\Requests\AtualizarRegraPontuacaoRequest;
use App\Http\Requests\SalvarResultadoJogoRequest;
use App\Http\Requests\SalvarResultadoTorneioRequest;
use App\Jobs\RecalcularPontuacaoTorneioJob;
use App\Models\Cupom;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Jogador;
use App\Models\Jogo;
use App\Models\LogAposta;
use App\Models\RegraPontuacao;
use App\Models\ResultadoTorneio;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoCheckout;
use App\Services\ServicoResultadosTorneio;
use App\Services\ServicoSincronizacaoResultados;
use App\Services\ServicoTheSportsDb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PainelAdministradorController extends Controller
{
    /**
     * Chaves de pontuacao reconhecidas pelo motor (ServicoPontuacao). Apenas regras
     * com uma destas chaves efetivamente pontuam. Mantém os dois sincronizados.
     */
    private const CHAVES_PONTUACAO = [
        'placar_exato_fase_grupos' => 'Placar exato (fase de grupos)',
        'vencedor_e_acertou_gols' => 'Vencedor + acertou gols (grupos)',
        'apenas_vencedor' => 'Apenas o vencedor (grupos)',
        'empate_sem_placar' => 'Empate sem placar exato (grupos)',
        'acertou_1_placar' => 'Acertou 1 placar (grupos)',
        'classificado_mata_mata' => 'Classificado (mata-mata)',
        'classificado_e_placar_mata_mata' => 'Classificado + placar (mata-mata)',
        'artilheiro' => 'Artilheiro do torneio',
        'campeao' => 'Campeao',
        'vice_campeao' => 'Vice-campeao',
        'terceiro_colocado' => 'Terceiro colocado',
    ];

    public function __construct(
        private readonly ServicoResultadosTorneio $servicoResultadosTorneio,
        private readonly ServicoCheckout $servicoCheckout,
        private readonly ServicoSincronizacaoResultados $servicoSincronizacao,
        private readonly ServicoTheSportsDb $api,
    ) {
    }

    public function cuponsPendentes(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('busca', ''));

        $cupons = Cupom::query()
            ->where('status', 'aguardando_pagamento')
            ->with(['usuario:id,nome,email,telefone', 'pedidoCheckout:id,valor,status'])
            ->when($busca !== '', function ($query) use ($busca) {
                $query->where(function ($q) use ($busca) {
                    $q->where('codigo', 'like', "%{$busca}%")
                        ->orWhereHas('usuario', fn ($u) => $u
                            ->where('nome', 'like', "%{$busca}%")
                            ->orWhere('email', 'like', "%{$busca}%")
                            ->orWhere('telefone', 'like', "%{$busca}%"));
                });
            })
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'cupons' => $cupons,
        ]);
    }

    public function marcarCupomPago(Cupom $cupom): JsonResponse
    {
        $cupom = $this->servicoCheckout->marcarCupomComoPago($cupom);

        return response()->json([
            'cupom' => $cupom->load(['usuario:id,nome,email,telefone', 'pedidoCheckout:id,valor,status']),
        ]);
    }

    public function usuarios(): JsonResponse
    {
        return response()->json([
            'usuarios' => Usuario::query()->orderBy('nome')->get(['id', 'nome', 'email']),
        ]);
    }

    /**
     * Relatorio de auditoria dos palpites (logs_apostas), com filtros por jogador,
     * periodo (dia civil de Brasilia), bolao, jogo, acao e escopo. Logs "orfaos"
     * (aposta apagada => aposta_id nulo) sao o unico rastro de palpites removidos,
     * por isso o escopo permite isola-los.
     */
    public function logsApostas(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'usuario_id' => ['nullable', 'integer', 'exists:usuarios,id'],
            'torneio_id' => ['nullable', 'integer', 'exists:torneios,id'],
            'jogo_id' => ['nullable', 'integer', 'exists:jogos,id'],
            'acao' => ['nullable', Rule::in(['criada', 'editada', 'removida'])],
            'escopo' => ['nullable', Rule::in(['todos', 'orfaos', 'vinculados'])],
            'apenas_mudancas' => ['nullable', 'in:0,1'],
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d'],
        ]);

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
            ->when($filtros['torneio_id'] ?? null, fn ($q, $id) => $q->whereHas('cupom', fn ($c) => $c->where('torneio_id', $id)))
            ->when($filtros['jogo_id'] ?? null, fn ($q, $id) => $q->whereHas('aposta', fn ($a) => $a->where('jogo_id', $id)))
            ->when($filtros['acao'] ?? null, fn ($q, $acao) => $q->where('acao', $acao))
            ->when(($filtros['escopo'] ?? 'todos') === 'orfaos', fn ($q) => $q->whereNull('aposta_id'))
            ->when(($filtros['escopo'] ?? 'todos') === 'vinculados', fn ($q) => $q->whereNotNull('aposta_id'))
            // "Mudanca real": descarta re-saves do auto-save em que o conteudo nao mudou.
            ->when($request->boolean('apenas_mudancas'), fn ($q) => $q->whereRaw(
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
        $nomes = $this->nomesParaResumoDeLogs($itens);

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
            'resumo_antes' => $this->resumirConteudoDeLog($log->conteudo_anterior, $nomes),
            'resumo_depois' => $this->resumirConteudoDeLog($log->conteudo_novo, $nomes),
            'antes' => $log->conteudo_anterior,
            'depois' => $log->conteudo_novo,
        ]);

        return response()->json([
            'logs' => $logs,
            'pagina' => $paginado->currentPage(),
            'total_paginas' => $paginado->lastPage(),
            'total' => $paginado->total(),
        ]);
    }

    /**
     * Nomes de selecoes/jogadores referenciados nos conteudos da pagina, para o
     * resumo legivel (ex.: "classificado: Portugal" em vez de "#41").
     *
     * @param  array<int, LogAposta>  $logs
     * @return array{selecoes: array<int, string>, jogadores: array<int, string>}
     */
    private function nomesParaResumoDeLogs(array $logs): array
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
    private function resumirConteudoDeLog(?array $conteudo, array $nomes): ?string
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

    public function resumo(): JsonResponse
    {
        return response()->json([
            'metricas' => [
                'usuarios' => Usuario::query()->count(),
                'torneios' => Torneio::query()->count(),
                'grupos' => Grupo::query()->count(),
                'selecoes' => Selecao::query()->count(),
                'fases' => Fase::query()->count(),
                'jogos' => Jogo::query()->count(),
                'regras_pontuacao' => RegraPontuacao::query()->count(),
            ],
        ]);
    }

    public function atualizarComprasAbertas(AtualizarComprasCupomRequest $request, Torneio $torneio): JsonResponse
    {
        $torneio->forceFill(['compras_abertas' => $request->boolean('compras_abertas')])->save();

        return response()->json(['torneio' => $torneio->fresh()]);
    }

    public function atualizarFechamentoPodio(AtualizarFechamentoPodioRequest $request, Torneio $torneio): JsonResponse
    {
        $torneio->forceFill([
            'data_fechamento_podio' => $request->date('data_fechamento_podio'),
        ])->save();

        return response()->json(['torneio' => $torneio->fresh()]);
    }

    public function dados(Request $request): JsonResponse
    {
        $torneio = Torneio::query()
            ->when(
                $request->filled('torneio_id'),
                fn ($query) => $query->whereKey($request->integer('torneio_id')),
            )
            ->with([
                'resultadoTorneio',
                'grupos.selecoes.jogadores',
                'fases' => fn ($query) => $query->orderBy('ordem'),
                'fases.rodadas' => fn ($query) => $query->orderBy('ordem'),
                'jogos' => fn ($query) => $query->orderBy('data_hora_inicio'),
                'jogos.fase',
                'jogos.rodada',
                'jogos.grupo',
                'jogos.selecaoMandante',
                'jogos.selecaoVisitante',
                'jogos.resultado',
                'regrasPontuacao' => fn ($query) => $query->withCount('eventosPontuacao')->orderBy('chave'),
            ])
            ->latest('id')
            ->firstOrFail();

        $participantesPorJogo = $this->servicoResultadosTorneio->participantesPorJogo($torneio);
        $torneio->jogos->each(function (Jogo $jogo) use ($participantesPorJogo): void {
            $participantes = $participantesPorJogo[$jogo->id] ?? ['mandante' => null, 'visitante' => null];
            $jogo->setAttribute('participantes_admin', collect([$participantes['mandante'], $participantes['visitante']])
                ->filter()
                ->map(fn (Selecao $selecao) => [
                    'id' => $selecao->id,
                    'nome' => $selecao->nome,
                    'sigla' => $selecao->sigla,
                ])
                ->values()
                ->all());
        });

        return response()->json([
            'torneio' => $torneio,
            'chaves_disponiveis' => collect(self::CHAVES_PONTUACAO)
                ->map(fn (string $label, string $chave) => ['chave' => $chave, 'label' => $label])
                ->values(),
        ]);
    }

    public function salvarResultadoJogo(SalvarResultadoJogoRequest $request, Jogo $jogo): JsonResponse
    {
        $jogo->loadMissing('fase', 'torneio');

        $resultado = $this->servicoSincronizacao->aplicarResultado(
            $jogo,
            $request->integer('placar_mandante'),
            $request->integer('placar_visitante'),
            $this->resolverClassificadoResultado($request, $jogo),
        );

        return response()->json([
            'resultado' => $resultado,
        ]);
    }

    public function limparResultadoJogo(Jogo $jogo): JsonResponse
    {
        $this->servicoSincronizacao->limparResultado($jogo);

        return response()->json([
            'mensagem' => 'Resultado removido. Recalculo enviado para processamento.',
        ]);
    }

    public function vincularEventoJogo(Request $request, Jogo $jogo): JsonResponse
    {
        $validado = $request->validate([
            'id_evento_externo' => [
                'present',
                'nullable',
                'integer',
                // Unicidade POR TORNEIO (o mesmo evento da Copa é usado nos dois bolões;
                // o índice do banco é composto torneio_id+id_evento_externo).
                Rule::unique('jogos', 'id_evento_externo')
                    ->where('torneio_id', $jogo->torneio_id)
                    ->ignore($jogo->id),
            ],
        ]);

        $idEvento = $validado['id_evento_externo'];

        // Código vazio → limpa o slot (volta a "A definir"): zera vínculo, times e o
        // resultado (que pode ter vindo de um vínculo errado), com recálculo de pontos.
        if ($idEvento === null) {
            if ($jogo->resultado()->exists()) {
                $this->servicoSincronizacao->limparResultado($jogo);
            }

            // Restaura a data-âncora da fase (config), em vez de deixar pendurada a
            // data do evento que estava vinculado — senão o slot "limpo" fica com data
            // de outro jogo.
            $jogo->loadMissing('fase');
            $ancora = config('calendario_mata_mata.jogos.'.$jogo->fase?->slug.'.'.($jogo->ordem_na_fase - 1));

            $jogo->forceFill([
                'id_evento_externo' => null,
                'selecao_mandante_id' => null,
                'selecao_visitante_id' => null,
                'data_hora_inicio' => $ancora ?? $jogo->data_hora_inicio,
            ])->save();

            return response()->json([
                'jogo' => $jogo->only('id', 'id_evento_externo', 'selecao_mandante_id', 'selecao_visitante_id', 'data_hora_inicio'),
            ]);
        }

        // Código preenchido → o evento é a fonte da verdade: traz times + data dele,
        // mantendo time e vínculo SEMPRE coerentes (mesma origem).
        $evento = $this->api->lookupEvento($idEvento);
        if ($evento === null) {
            throw ValidationException::withMessages([
                'id_evento_externo' => 'Evento não encontrado na TheSportsDB.',
            ]);
        }

        $mandante = $this->selecaoPorIdExterno($jogo, $evento['idHomeTeam'] ?? null);
        $visitante = $this->selecaoPorIdExterno($jogo, $evento['idAwayTeam'] ?? null);

        // Par mudou → resultado anterior ficou obsoleto: limpa antes (recalcula pontos).
        $mudouPar = (int) $jogo->selecao_mandante_id !== (int) $mandante?->id
            || (int) $jogo->selecao_visitante_id !== (int) $visitante?->id;
        if ($mudouPar && $jogo->resultado()->exists()) {
            $this->servicoSincronizacao->limparResultado($jogo);
        }

        $jogo->forceFill([
            'id_evento_externo' => $idEvento,
            'selecao_mandante_id' => $mandante?->id,
            'selecao_visitante_id' => $visitante?->id,
            'data_hora_inicio' => $this->dataBrtDoEvento($evento) ?? $jogo->data_hora_inicio,
        ])->save();

        return response()->json([
            'jogo' => $jogo->only('id', 'id_evento_externo', 'selecao_mandante_id', 'selecao_visitante_id', 'data_hora_inicio'),
        ]);
    }

    private function selecaoPorIdExterno(Jogo $jogo, mixed $idExterno): ?Selecao
    {
        if (! $idExterno) {
            return null;
        }

        return Selecao::query()
            ->where('torneio_id', $jogo->torneio_id)
            ->where('id_externo', (int) $idExterno)
            ->first();
    }

    /**
     * Data do evento (UTC na API) → wall-clock de Brasília "Y-m-d H:i:s" (convenção
     * de data_hora_inicio). Usa strTimestamp; senão dateEvent + strTime.
     *
     * @param  array<string,mixed>  $evento
     */
    private function dataBrtDoEvento(array $evento): ?string
    {
        $iso = $evento['strTimestamp'] ?? null;
        if (! $iso) {
            $dia = $evento['dateEvent'] ?? null;
            if (! $dia) {
                return null;
            }
            $iso = trim($dia.' '.($evento['strTime'] ?? '00:00:00'));
        }

        try {
            return Carbon::parse($iso, 'UTC')->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public function salvarResultadoTorneio(SalvarResultadoTorneioRequest $request, Torneio $torneio): JsonResponse
    {
        $dadosDerivados = $this->resolverPodioReal($torneio->loadMissing(['jogos.fase', 'jogos.resultado']));
        $resultado = ResultadoTorneio::query()->updateOrCreate(
            ['torneio_id' => $torneio->id],
            [
                'campeao_selecao_id' => $dadosDerivados['campeao_selecao_id'],
                'vice_campeao_selecao_id' => $dadosDerivados['vice_campeao_selecao_id'],
                'terceiro_colocado_selecao_id' => $dadosDerivados['terceiro_colocado_selecao_id'],
                'artilheiro_jogador_id' => $request->validated('artilheiro_jogador_id'),
            ],
        );

        RecalcularPontuacaoTorneioJob::dispatchAfterResponse($torneio->id);

        return response()->json([
            'resultado_torneio' => $resultado,
        ]);
    }

    public function atualizarRegraPontuacao(AtualizarRegraPontuacaoRequest $request, RegraPontuacao $regraPontuacao): JsonResponse
    {
        $regraPontuacao->fill($request->validated());
        $regraPontuacao->save();

        RecalcularPontuacaoTorneioJob::dispatchAfterResponse($regraPontuacao->torneio()->firstOrFail()->id);

        return response()->json([
            'regra_pontuacao' => $regraPontuacao,
        ]);
    }

    public function criarRegraPontuacao(Request $request): JsonResponse
    {
        $torneio = $request->filled('torneio_id')
            ? Torneio::query()->findOrFail($request->integer('torneio_id'))
            : Torneio::query()->latest('id')->firstOrFail();

        $dados = $request->validate([
            'torneio_id' => ['nullable', 'integer', 'exists:torneios,id'],
            'chave' => ['required', 'string', Rule::in(array_keys(self::CHAVES_PONTUACAO))],
            'fase_id' => ['nullable', 'integer', Rule::exists('fases', 'id')->where('torneio_id', $torneio->id)],
            'nome' => ['required', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'pontos' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $faseId = $dados['fase_id'] ?? null;

        $jaExiste = RegraPontuacao::query()
            ->where('torneio_id', $torneio->id)
            ->where('chave', $dados['chave'])
            ->when($faseId !== null, fn ($q) => $q->where('fase_id', $faseId), fn ($q) => $q->whereNull('fase_id'))
            ->exists();

        if ($jaExiste) {
            throw ValidationException::withMessages([
                'chave' => 'Ja existe uma regra com essa chave para esta fase.',
            ]);
        }

        $regra = RegraPontuacao::query()->create([
            'torneio_id' => $torneio->id,
            'fase_id' => $faseId,
            'chave' => $dados['chave'],
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'pontos' => $dados['pontos'],
            'ativo' => true,
        ]);

        RecalcularPontuacaoTorneioJob::dispatchAfterResponse($torneio->id);

        return response()->json([
            'regra_pontuacao' => $regra,
        ], 201);
    }

    public function excluirRegraPontuacao(RegraPontuacao $regraPontuacao): JsonResponse
    {
        if ($regraPontuacao->eventosPontuacao()->exists()) {
            throw ValidationException::withMessages([
                'regra' => 'Esta regra ja foi aplicada (gerou pontos) e nao pode ser excluida.',
            ]);
        }

        $torneioId = $regraPontuacao->torneio_id;
        $regraPontuacao->delete();

        RecalcularPontuacaoTorneioJob::dispatchAfterResponse($torneioId);

        return response()->json([
            'mensagem' => 'Regra excluida.',
        ]);
    }

    public function pagamentos(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('busca', ''));

        $cupons = Cupom::query()
            ->with(['usuario:id,nome,email,telefone', 'pedidoCheckout:id,valor,status'])
            ->when($busca !== '', function ($query) use ($busca) {
                $query->where(function ($q) use ($busca) {
                    $q->where('codigo', 'like', "%{$busca}%")
                        ->orWhereHas('usuario', fn ($u) => $u
                            ->where('nome', 'like', "%{$busca}%")
                            ->orWhere('email', 'like', "%{$busca}%")
                            ->orWhere('telefone', 'like', "%{$busca}%"));
                });
            })
            ->latest('id')
            ->limit(300)
            ->get();

        return response()->json([
            'cupons' => $cupons,
        ]);
    }

    public function marcarCupomNaoPago(Cupom $cupom): JsonResponse
    {
        $cupom = $this->servicoCheckout->marcarCupomComoNaoPago($cupom);

        return response()->json([
            'cupom' => $cupom->load(['usuario:id,nome,email,telefone', 'pedidoCheckout:id,valor,status']),
        ]);
    }

    /**
     * @return array{campeao_selecao_id:?int,vice_campeao_selecao_id:?int,terceiro_colocado_selecao_id:?int}
     */
    private function resolverPodioReal(Torneio $torneio): array
    {
        return $this->servicoResultadosTorneio->resolverPodio($torneio);
    }

    private function resolverClassificadoResultado(SalvarResultadoJogoRequest $request, Jogo $jogo): ?int
    {
        if ($jogo->fase?->tipo === 'grupos') {
            return $request->filled('selecao_classificada_id') ? $request->integer('selecao_classificada_id') : null;
        }

        $participantes = $this->servicoResultadosTorneio->participantesDoJogo($jogo);
        $mandante = $participantes['mandante']?->id;
        $visitante = $participantes['visitante']?->id;

        if (! $mandante || ! $visitante) {
            throw ValidationException::withMessages([
                'selecao_classificada_id' => 'Os participantes deste confronto ainda nao estao definidos.',
            ]);
        }

        if ($request->filled('selecao_classificada_id')) {
            return $request->integer('selecao_classificada_id');
        }

        $placarMandante = $request->integer('placar_mandante');
        $placarVisitante = $request->integer('placar_visitante');

        if ($placarMandante === $placarVisitante) {
            throw ValidationException::withMessages([
                'selecao_classificada_id' => 'Jogos eliminatorios empatados exigem selecao classificada.',
            ]);
        }

        return $placarMandante > $placarVisitante ? (int) $mandante : (int) $visitante;
    }
}
