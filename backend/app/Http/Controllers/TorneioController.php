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
        $torneio = Torneio::query()
            ->where('status', 'publicado')
            ->latest('id')
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
        // (kickoff no mata-mata; prazo do dia/rodada nos grupos). Trava no backend.
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

    private function carregarRelacionamentos(Torneio $torneio): Torneio
    {
        return $torneio->load([
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
            'regrasPontuacao' => fn ($query) => $query->orderBy('chave'),
        ]);
    }
}
