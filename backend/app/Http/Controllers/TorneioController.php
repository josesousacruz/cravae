<?php

namespace App\Http\Controllers;

use App\Models\PontuacaoCupom;
use App\Models\Torneio;
use Illuminate\Http\JsonResponse;

class TorneioController extends Controller
{
    public function publico(): JsonResponse
    {
        return response()->json([
            'torneio' => $this->carregarTorneio(),
        ]);
    }

    public function ranking(Torneio $torneio): JsonResponse
    {
        $ranking = PontuacaoCupom::query()
            ->with(['cupom.usuario'])
            ->whereHas('cupom.apostas', fn ($query) => $query->where('torneio_id', $torneio->id))
            ->orderByDesc('pontuacao_total')
            ->orderByDesc('quantidade_placares_exatos')
            ->orderByDesc('quantidade_classificados_corretos')
            ->orderByDesc('quantidade_palpites_finais_corretos')
            ->get();

        return response()->json([
            'ranking' => $ranking,
        ]);
    }

    private function carregarTorneio(): Torneio
    {
        return Torneio::query()
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
                'regrasPontuacao' => fn ($query) => $query->orderBy('chave'),
            ])
            ->where('status', 'publicado')
            ->latest('id')
            ->firstOrFail();
    }
}
