<?php

namespace App\Http\Controllers;

use App\Http\Requests\AtualizarComprasCupomRequest;
use App\Http\Requests\AtualizarRegraPontuacaoRequest;
use App\Http\Requests\SalvarResultadoJogoRequest;
use App\Http\Requests\SalvarResultadoTorneioRequest;
use App\Jobs\RecalcularPontuacaoTorneioJob;
use App\Models\Cupom;
use App\Models\Fase;
use App\Models\Grupo;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\ResultadoJogo;
use App\Models\ResultadoTorneio;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoCheckout;
use App\Services\ServicoResultadosTorneio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function dados(): JsonResponse
    {
        $torneio = Torneio::query()
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
        $resultado = ResultadoJogo::query()->updateOrCreate(
            ['jogo_id' => $jogo->id],
            [
                'placar_mandante' => $request->integer('placar_mandante'),
                'placar_visitante' => $request->integer('placar_visitante'),
                'selecao_classificada_id' => $this->resolverClassificadoResultado($request, $jogo),
                'encerrado_at' => now(),
            ],
        );

        $jogo->forceFill(['status' => 'encerrado'])->save();
        $torneio = $jogo->torneio()->firstOrFail();
        $this->sincronizarResultadoTorneio($torneio);
        RecalcularPontuacaoTorneioJob::dispatchAfterResponse($torneio->id);

        return response()->json([
            'resultado' => $resultado,
        ]);
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
        $torneio = Torneio::query()->latest('id')->firstOrFail();

        $dados = $request->validate([
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

    private function sincronizarResultadoTorneio(Torneio $torneio): void
    {
        $torneio->loadMissing(['jogos.fase', 'jogos.resultado', 'resultadoTorneio']);
        $podio = $this->servicoResultadosTorneio->resolverPodio($torneio);

        ResultadoTorneio::query()->updateOrCreate(
            ['torneio_id' => $torneio->id],
            [
                'campeao_selecao_id' => $podio['campeao_selecao_id'],
                'vice_campeao_selecao_id' => $podio['vice_campeao_selecao_id'],
                'terceiro_colocado_selecao_id' => $podio['terceiro_colocado_selecao_id'],
                'artilheiro_jogador_id' => $torneio->resultadoTorneio?->artilheiro_jogador_id,
            ],
        );
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
