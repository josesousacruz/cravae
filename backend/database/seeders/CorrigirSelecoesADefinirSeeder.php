<?php

namespace Database\Seeders;

use App\Models\Selecao;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Substitui os 6 placeholders "A Definir" pelas seleções reais que venceram
 * as repescagens (UEFA e intercontinentais) em março/2026.
 *
 *   UEFA Path A  -> Bosnia e Herzegovina (grupo B)
 *   UEFA Path B  -> Suecia               (grupo F)
 *   UEFA Path C  -> Turquia              (grupo D)
 *   UEFA Path D  -> Tchequia             (grupo A)
 *   Intercont. 1 -> RD Congo             (grupo K)
 *   Intercont. 2 -> Iraque               (grupo I)
 *
 * O id_externo (idTeam da TheSportsDB) é puxado de config('thesportsdb.selecoes')
 * pela sigla nova — então assim que essas 6 siglas forem adicionadas ao config,
 * basta rodar o VincularSelecoesTheSportsDbSeeder para completar o vínculo.
 *
 * Idempotente: após rodar, as siglas antigas deixam de existir.
 *
 *   php artisan db:seed --class=CorrigirSelecoesADefinirSeeder
 */
class CorrigirSelecoesADefinirSeeder extends Seeder
{
    public function run(): void
    {
        // sigla antiga => [nome real, sigla FIFA real]
        $correcoes = [
            'UA1' => ['Bosnia e Herzegovina', 'BIH'],
            'UB2' => ['Suecia', 'SWE'],
            'UC3' => ['Turquia', 'TUR'],
            'UD4' => ['Tchequia', 'CZE'],
            'IC1' => ['RD Congo', 'COD'],
            'IC2' => ['Iraque', 'IRQ'],
        ];

        $mapaExterno = config('thesportsdb.selecoes', []);
        $corrigidas = 0;

        foreach ($correcoes as $siglaAntiga => [$nome, $siglaNova]) {
            $afetadas = Selecao::query()
                ->where('sigla', $siglaAntiga)
                ->update([
                    'nome' => $nome,
                    'sigla' => $siglaNova,
                    'slug' => Str::slug($nome),
                    'id_externo' => $mapaExterno[$siglaNova] ?? null,
                ]);

            if ($afetadas > 0) {
                $this->command?->info("  {$siglaAntiga} -> {$nome} ({$siglaNova})");
                $corrigidas += $afetadas;
            }
        }

        $this->command?->info("Seleções corrigidas: {$corrigidas}");
    }
}
