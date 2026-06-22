<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da TheSportsDB (v1) focado nos dados da Copa.
 *
 * Os métodos retornam o array bruto de "events" da API (ou vazio). A
 * interpretação/casamento fica nos commands que consomem este serviço.
 */
class ServicoTheSportsDb
{
    private function clienteHttp(): PendingRequest
    {
        $base = rtrim((string) config('thesportsdb.base_v1'), '/')
            .'/'.config('thesportsdb.chave_api');

        return Http::baseUrl($base)
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 500, throw: false);
    }

    private function idLigaCopa(): int
    {
        return (int) config('thesportsdb.id_liga_copa');
    }

    /**
     * Calendário + resultados completos de uma temporada (1 request traz tudo).
     *
     * @return array<int,array<string,mixed>>
     */
    public function eventosDaTemporada(?string $temporada = null): array
    {
        $temporada ??= (string) config('thesportsdb.temporada_copa');

        $resposta = $this->clienteHttp()->get('/eventsseason.php', [
            'id' => $this->idLigaCopa(),
            's' => $temporada,
        ]);

        return $resposta->json('events') ?? [];
    }

    /**
     * Últimos jogos encerrados da liga (bom para polling de resultados no Free).
     *
     * @return array<int,array<string,mixed>>
     */
    public function eventosPassadosDaLiga(): array
    {
        $resposta = $this->clienteHttp()->get('/eventspastleague.php', [
            'id' => $this->idLigaCopa(),
        ]);

        return $resposta->json('events') ?? [];
    }

    /**
     * Próximos jogos agendados da liga.
     *
     * @return array<int,array<string,mixed>>
     */
    public function eventosFuturosDaLiga(): array
    {
        $resposta = $this->clienteHttp()->get('/eventsnextleague.php', [
            'id' => $this->idLigaCopa(),
        ]);

        return $resposta->json('events') ?? [];
    }

    /**
     * Jogos de uma rodada (intRound) da temporada. É o único endpoint sem teto
     * no plano free — cada rodada da fase de grupos traz os 24 jogos completos.
     *
     * @return array<int,array<string,mixed>>
     */
    public function eventosDaRodada(int $rodada, ?string $temporada = null): array
    {
        $temporada ??= (string) config('thesportsdb.temporada_copa');

        $resposta = $this->clienteHttp()->get('/eventsround.php', [
            'id' => $this->idLigaCopa(),
            'r' => $rodada,
            's' => $temporada,
        ]);

        return $resposta->json('events') ?? [];
    }

    /**
     * Une os jogos de várias rodadas, deduplicando por idEvent.
     *
     * @param  array<int,int>  $rodadas
     * @return array<int,array<string,mixed>>
     */
    public function eventosDasRodadas(array $rodadas, ?string $temporada = null): array
    {
        $porId = [];

        foreach (array_unique($rodadas) as $rodada) {
            foreach ($this->eventosDaRodada((int) $rodada, $temporada) as $evento) {
                $id = (int) ($evento['idEvent'] ?? 0);
                if ($id !== 0) {
                    $porId[$id] = $evento;
                }
            }
        }

        return array_values($porId);
    }
}
