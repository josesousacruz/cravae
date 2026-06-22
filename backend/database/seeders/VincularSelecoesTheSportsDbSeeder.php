<?php

namespace Database\Seeders;

use App\Models\Selecao;
use Illuminate\Database\Seeder;

/**
 * Nível 1 — vincula cada seleção ao idTeam da TheSportsDB (coluna id_externo).
 *
 * Idempotente: pode rodar quantas vezes quiser. Casa pela sigla FIFA, então
 * funciona em qualquer torneio/edição. Seleções "A Definir" (sem entrada no
 * de-para) permanecem com id_externo nulo.
 *
 *   php artisan db:seed --class=VincularSelecoesTheSportsDbSeeder
 */
class VincularSelecoesTheSportsDbSeeder extends Seeder
{
    public function run(): void
    {
        $mapa = config('thesportsdb.selecoes');
        $vinculadas = 0;

        foreach ($mapa as $sigla => $idExterno) {
            $vinculadas += Selecao::query()
                ->where('sigla', $sigla)
                ->update(['id_externo' => $idExterno]);
        }

        $semVinculo = Selecao::query()->whereNull('id_externo')->pluck('sigla');

        $this->command?->info("Seleções vinculadas: {$vinculadas}");
        $this->command?->warn(
            'Sem vínculo (A Definir): '.($semVinculo->isEmpty() ? 'nenhuma' : $semVinculo->implode(', '))
        );
    }
}
