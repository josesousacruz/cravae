<?php

namespace App\Services;

use App\Models\Cupom;
use App\Models\Jogo;

class ServicoBracketReal
{
    public function __construct(
        private readonly ServicoResultadosTorneio $servicoResultadosTorneio,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function gerar(Cupom $cupom): array
    {
        $torneio = $cupom->torneio()->with([
            'fases' => fn ($q) => $q->orderBy('ordem'),
            'jogos' => fn ($q) => $q->orderBy('ordem_na_fase'),
            'jogos.fase',
            'jogos.resultado',
        ])->first();

        if (! $torneio) {
            return [];
        }

        $participantes = $this->servicoResultadosTorneio->participantesPorJogo($torneio);
        $apostas = $cupom->apostas
            ->where('tipo', 'placar_jogo_eliminatoria')
            ->keyBy('jogo_id');

        return $torneio->jogos
            ->filter(fn (Jogo $jogo) => $jogo->fase && $jogo->fase->tipo !== 'grupos')
            ->sortBy(fn (Jogo $jogo) => [$jogo->fase->ordem, $jogo->ordem_na_fase])
            ->map(function (Jogo $jogo) use ($participantes, $apostas) {
                $par = $participantes[$jogo->id] ?? ['mandante' => null, 'visitante' => null];
                $aposta = $apostas->get($jogo->id);

                return [
                    'id' => $jogo->id,
                    'fase' => ['slug' => $jogo->fase->slug, 'nome' => $jogo->fase->nome, 'ordem' => $jogo->fase->ordem],
                    'data_hora_inicio' => $jogo->data_hora_inicio,
                    'selecao_mandante' => $par['mandante'],
                    'selecao_visitante' => $par['visitante'],
                    'resultado' => $jogo->resultado,
                    'palpite' => $aposta?->conteudo,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{podio_palpite:array{campeao:?int,vice:?int,terceiro:?int},podio_real:array{campeao:?int,vice:?int,terceiro:?int}}
     */
    public function resumo(Cupom $cupom): array
    {
        $torneio = $cupom->torneio()->with('resultadoTorneio')->first();
        $podioAposta = $cupom->apostas->firstWhere('tipo', 'podio');
        $c = $podioAposta?->conteudo ?? [];

        return [
            'podio_palpite' => [
                'campeao' => (int) ($c['campeao_selecao_id'] ?? 0) ?: null,
                'vice' => (int) ($c['vice_selecao_id'] ?? 0) ?: null,
                'terceiro' => (int) ($c['terceiro_selecao_id'] ?? 0) ?: null,
            ],
            'podio_real' => [
                'campeao' => $torneio?->resultadoTorneio?->campeao_selecao_id,
                'vice' => $torneio?->resultadoTorneio?->vice_campeao_selecao_id,
                'terceiro' => $torneio?->resultadoTorneio?->terceiro_colocado_selecao_id,
            ],
        ];
    }
}
