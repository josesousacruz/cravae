<?php

namespace App\Http\Controllers;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\EventoPontuacao;
use App\Models\Jogo;
use App\Models\PontuacaoCupom;
use App\Models\Torneio;
use App\Services\ServicoFechamentoApostas;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TorneioController extends Controller
{
    public function publico(): JsonResponse
    {
        // Fase 09 (Palpite Primeiro): a home mostra o torneio marcado como
        // principal; sem nenhum marcado, cai no publicado de menor id.
        $torneio = Torneio::query()
            ->where('status', 'publicado')
            ->orderByDesc('principal')
            ->orderBy('id')
            ->firstOrFail();

        return response()->json([
            'torneio' => $this->carregarRelacionamentos($torneio),
        ]);
    }

    public function show(Torneio $torneio): JsonResponse
    {
        abort_unless($torneio->status === 'publicado', 404);

        return response()->json([
            'torneio' => $this->carregarRelacionamentos($torneio),
        ]);
    }

    public function ranking(Torneio $torneio): JsonResponse
    {
        // Fase 10: ?competencia=YYYY-MM recorta a disputa do mês (prêmio mensal).
        $competencia = request()->query('competencia');

        if (is_string($competencia) && preg_match('/^\d{4}-\d{2}$/', $competencia)) {
            return $this->rankingDaCompetencia($torneio, $competencia);
        }

        $ranking = PontuacaoCupom::query()
            ->with([
                'cupom:id,usuario_id,codigo',
                'cupom.usuario:id,nome,foto',
            ])
            ->whereHas('cupom.apostas', fn ($query) => $query->where('torneio_id', $torneio->id))
            ->orderByDesc('pontuacao_total')
            ->orderByDesc('quantidade_placares_exatos')
            ->orderByDesc('quantidade_classificados_corretos')
            ->orderByDesc('quantidade_palpites_finais_corretos')
            ->get();

        // Fase 08: selo de assinante — elegível ao prêmio da competência corrente.
        $elegiveis = app(\App\Services\ServicoAssinatura::class)->usuariosElegiveisNaCompetencia();
        $ranking->each(function ($item) use ($elegiveis) {
            $item->setAttribute('assinante', in_array($item->cupom?->usuario_id, $elegiveis, true));
        });

        $usuarioId = Auth::guard('sanctum')->id();

        $minhaPosicao = null;

        if ($usuarioId) {
            $indice = $ranking->search(fn ($item) => $item->cupom?->usuario_id === $usuarioId);

            if ($indice !== false) {
                $minhaPosicao = [
                    'posicao' => $indice + 1,
                    'item' => $ranking[$indice],
                ];
            }
        }

        $totalPartidas = Jogo::query()->where('torneio_id', $torneio->id)->count();
        $partidasFinalizadas = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereHas('resultado', fn ($query) => $query->whereNotNull('placar_mandante')->whereNotNull('placar_visitante'))
            ->count();

        return response()->json([
            'ranking' => $ranking,
            'minha_posicao' => $minhaPosicao,
            'partidas' => [
                'finalizadas' => $partidasFinalizadas,
                'total' => $totalPartidas,
            ],
        ]);
    }

    /**
     * Disputa do mês: soma os eventos de pontuação de jogos que INICIAM na
     * competência. Eventos sem jogo (pódio/artilheiro/campeão) ficam fora do
     * recorte mensal — pertencem só ao ranking geral.
     */
    private function rankingDaCompetencia(Torneio $torneio, string $competencia): JsonResponse
    {
        $inicio = \Carbon\Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $linhas = \App\Models\EventoPontuacao::query()
            ->join('jogos', 'jogos.id', '=', 'eventos_pontuacao.jogo_id')
            ->join('regras_pontuacao', 'regras_pontuacao.id', '=', 'eventos_pontuacao.regra_pontuacao_id')
            ->where('jogos.torneio_id', $torneio->id)
            ->whereBetween('jogos.data_hora_inicio', [$inicio, $fim])
            ->groupBy('eventos_pontuacao.cupom_id')
            ->selectRaw('eventos_pontuacao.cupom_id')
            ->selectRaw('SUM(eventos_pontuacao.pontos) as pontuacao_total')
            ->selectRaw("SUM(CASE WHEN regras_pontuacao.chave LIKE 'placar_exato%' THEN 1 ELSE 0 END) as quantidade_placares_exatos")
            ->selectRaw('COUNT(*) as quantidade_acertos')
            ->orderByDesc('pontuacao_total')
            ->orderByDesc('quantidade_placares_exatos')
            ->orderByDesc('quantidade_acertos')
            ->get();

        $cupons = \App\Models\Cupom::query()
            ->with('usuario:id,nome,foto')
            ->whereIn('id', $linhas->pluck('cupom_id'))
            ->get()
            ->keyBy('id');

        $elegiveis = app(\App\Services\ServicoAssinatura::class)->usuariosElegiveisNaCompetencia($competencia);

        $ranking = $linhas->map(function ($linha) use ($cupons, $elegiveis) {
            $cupom = $cupons->get($linha->cupom_id);

            return [
                'id' => $linha->cupom_id,
                'cupom_id' => $linha->cupom_id,
                'pontuacao_total' => (string) $linha->pontuacao_total,
                'quantidade_placares_exatos' => (int) $linha->quantidade_placares_exatos,
                'quantidade_classificados_corretos' => 0,
                'quantidade_palpites_finais_corretos' => (int) $linha->quantidade_acertos,
                'assinante' => in_array($cupom?->usuario_id, $elegiveis, true),
                'cupom' => [
                    'id' => $cupom?->id,
                    'usuario_id' => $cupom?->usuario_id,
                    'codigo' => $cupom?->codigo,
                    'usuario' => [
                        'id' => $cupom?->usuario?->id,
                        'nome' => $cupom?->usuario?->nome,
                        'foto' => $cupom?->usuario?->foto,
                    ],
                ],
            ];
        })->values();

        $usuarioId = Auth::guard('sanctum')->id();
        $minhaPosicao = null;

        if ($usuarioId) {
            $indice = $ranking->search(fn ($item) => ($item['cupom']['usuario_id'] ?? null) === $usuarioId);

            if ($indice !== false) {
                $minhaPosicao = ['posicao' => $indice + 1, 'item' => $ranking[$indice]];
            }
        }

        $consultaPartidas = Jogo::query()
            ->where('torneio_id', $torneio->id)
            ->whereBetween('data_hora_inicio', [$inicio, $fim]);
        $totalPartidas = (clone $consultaPartidas)->count();
        $partidasFinalizadas = $consultaPartidas
            ->whereHas('resultado', fn ($query) => $query->whereNotNull('placar_mandante')->whereNotNull('placar_visitante'))
            ->count();

        return response()->json([
            'ranking' => $ranking,
            'minha_posicao' => $minhaPosicao,
            'competencia' => $competencia,
            'partidas' => [
                'finalizadas' => $partidasFinalizadas,
                'total' => $totalPartidas,
            ],
        ]);
    }

    public function eventosCupom(Cupom $cupom): JsonResponse
    {
        $eventos = EventoPontuacao::query()
            ->where('cupom_id', $cupom->id)
            ->with([
                'jogo.selecaoMandante',
                'jogo.selecaoVisitante',
                'jogo.resultado',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'cupom' => [
                'id' => $cupom->id,
                'codigo' => $cupom->codigo,
            ],
            'eventos_pontuacao' => $eventos,
        ]);
    }

    public function palpiteiros(Jogo $jogo, ServicoFechamentoApostas $fechamento): JsonResponse
    {
        // O palpite alheio só é revelado depois que o prazo de aposta do jogo fecha
        // (mata-mata: kickoff do primeiro jogo do dia; grupos: prazo do dia/rodada). Trava no backend.
        $jogo->loadMissing('fase');
        $tipo = $jogo->fase?->tipo === 'grupos' ? 'placar_jogo_grupos' : 'placar_jogo_eliminatoria';
        $revelar = $fechamento->prazoEncerrado(['tipo' => $tipo, 'jogo_id' => $jogo->id]);

        $palpiteiros = Aposta::query()
            ->where('jogo_id', $jogo->id)
            ->whereIn('tipo', ['placar_jogo_grupos', 'placar_jogo_eliminatoria'])
            ->with('cupom.usuario:id,nome')
            ->get()
            ->map(function (Aposta $a) use ($revelar) {
                $conteudo = $a->conteudo ?? [];

                return [
                    'nome' => $a->cupom?->usuario?->nome ?? 'Anonimo',
                    'cupom_codigo' => $a->cupom?->codigo,
                    'palpite' => $revelar ? [
                        'placar_mandante' => $conteudo['placar_mandante'] ?? null,
                        'placar_visitante' => $conteudo['placar_visitante'] ?? null,
                        'selecao_classificada_id' => $conteudo['selecao_classificada_id'] ?? null,
                    ] : null,
                ];
            })
            ->unique('cupom_codigo')
            ->values();

        return response()->json([
            'total' => $palpiteiros->count(),
            'revelado' => $revelar,
            'palpiteiros' => $palpiteiros,
        ]);
    }

    /**
     * Elencos dos dois clubes de um jogo, para o seletor de goleadores.
     * Apenas jogadores ativos (transferidos somem do seletor, mas palpites
     * já feitos com eles continuam valendo).
     */
    public function elencosDoJogo(Jogo $jogo): JsonResponse
    {
        $jogo->loadMissing(['selecaoMandante', 'selecaoVisitante']);

        $elenco = fn (?int $selecaoId) => $selecaoId
            ? \App\Models\Jogador::query()
                ->where('selecao_id', $selecaoId)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get(['id', 'selecao_id', 'nome', 'apelido', 'posicao', 'numero_camisa', 'foto_url'])
            : collect();

        return response()->json([
            'mandante' => [
                'selecao_id' => $jogo->selecao_mandante_id,
                'nome' => $jogo->selecaoMandante?->nome,
                'escudo_url' => $jogo->selecaoMandante?->escudo_url,
                'jogadores' => $elenco($jogo->selecao_mandante_id),
            ],
            'visitante' => [
                'selecao_id' => $jogo->selecao_visitante_id,
                'nome' => $jogo->selecaoVisitante?->nome,
                'escudo_url' => $jogo->selecaoVisitante?->escudo_url,
                'jogadores' => $elenco($jogo->selecao_visitante_id),
            ],
        ]);
    }

    private function carregarRelacionamentos(Torneio $torneio): Torneio
    {
        return $torneio->load([
            'resultadoTorneio',
            'grupos.selecoes.jogadores',
            // Lista flat de seleções: o pódio (campeão/vice/3º) precisa de todas as
            // seleções mesmo em torneios sem grupos (ex.: bolão só de mata-mata).
            'selecoes' => fn ($query) => $query->where('ativo', true)->orderBy('nome'),
            'fases' => fn ($query) => $query->orderBy('ordem'),
            'fases.rodadas' => fn ($query) => $query->orderBy('ordem'),
            'jogos' => fn ($query) => $query->orderBy('data_hora_inicio'),
            'jogos.fase',
            'jogos.rodada',
            'jogos.grupo',
            'jogos.selecaoMandante',
            'jogos.selecaoVisitante',
            'jogos.resultado',
            'regrasPontuacao' => fn ($query) => $query->orderBy('chave'),
        ]);
    }
}
