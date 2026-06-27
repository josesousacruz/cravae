<?php

namespace App\Console\Commands;

use App\Models\Fase;
use App\Models\Jogo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Aplica o calendário oficial do mata-mata (config/calendario_mata_mata.php) nos
 * jogos JÁ EXISTENTES, sem recriar nada e sem rodar o seeder completo.
 *
 * Por que existe: rodar `db:seed --class=TorneioMockadoSeeder` corrigiria as datas,
 * mas TAMBÉM zeraria ResultadoTorneio (campeão/artilheiro) e resetaria as
 * RegraPontuacao — corrompendo insumos de pontuação. Este comando toca APENAS:
 *   - jogos.data_hora_inicio   (casado por fase.slug + ordem_na_fase)
 *   - fases.data_fechamento
 * Nada de apostas, eventos_pontuacao, pontuacoes_cupons, regras_pontuacao ou
 * resultados_torneio é lido ou alterado. Idempotente.
 *
 *   php artisan jogos:corrigir-datas-mata-mata
 *   php artisan jogos:corrigir-datas-mata-mata --dry-run
 */
class CorrigirDatasMataMata extends Command
{
    protected $signature = 'jogos:corrigir-datas-mata-mata
        {--dry-run : Mostra o que mudaria sem gravar}';

    protected $description = 'Aplica as datas oficiais do mata-mata nos jogos existentes (sem tocar pontos/apostas).';

    public function handle(): int
    {
        $calendario = config('calendario_mata_mata.jogos');
        $fechamento = config('calendario_mata_mata.fechamento_fase');
        $dryRun = (bool) $this->option('dry-run');

        $fases = Fase::query()
            ->whereIn('slug', array_keys($calendario))
            ->get();

        if ($fases->isEmpty()) {
            $this->warn('Nenhuma fase de mata-mata encontrada. Nada a fazer.');

            return self::SUCCESS;
        }

        $totais = ['jogos' => 0, 'fases' => 0, 'sem_jogo' => 0, 'com_evento_real' => 0];

        DB::transaction(function () use ($fases, $calendario, $fechamento, $dryRun, &$totais) {
            foreach ($fases as $fase) {
                $datas = $calendario[$fase->slug] ?? [];

                foreach ($datas as $i => $data) {
                    $ordem = $i + 1;
                    $jogo = Jogo::query()
                        ->where('torneio_id', $fase->torneio_id)
                        ->where('fase_id', $fase->id)
                        ->where('ordem_na_fase', $ordem)
                        ->first();

                    if (! $jogo) {
                        $totais['sem_jogo']++;

                        continue;
                    }

                    // Jogo já casado com evento real da TheSportsDB tem a data REAL
                    // (gravada por jogos:vincular-eventos). Não sobrescrever com âncora —
                    // torna o comando seguro independente da ordem de execução.
                    if ($jogo->id_evento_externo) {
                        $totais['com_evento_real']++;

                        continue;
                    }

                    $atual = $jogo->data_hora_inicio?->format('Y-m-d H:i');
                    $novo = substr($data, 0, 16);

                    if ($atual !== $novo) {
                        $this->line(sprintf(
                            '  [T%d] %s #%d: %s -> %s',
                            $fase->torneio_id,
                            $fase->slug,
                            $ordem,
                            $atual ?? '(nulo)',
                            $novo,
                        ));

                        if (! $dryRun) {
                            // forceFill + save tocando só a coluna de data.
                            $jogo->forceFill(['data_hora_inicio' => $data])->save();
                        }
                        $totais['jogos']++;
                    }
                }

                $novoFechamento = $fechamento[$fase->slug] ?? null;
                if ($novoFechamento && (string) $fase->data_fechamento?->format('Y-m-d H:i:s') !== $novoFechamento) {
                    if (! $dryRun) {
                        $fase->forceFill(['data_fechamento' => $novoFechamento])->save();
                    }
                    $totais['fases']++;
                }
            }
        });

        $this->info(sprintf(
            '%s Jogos atualizados: %d | Fases (data_fechamento): %d | Preservados (evento real): %d | Slots sem jogo: %d',
            $dryRun ? '[DRY-RUN]' : 'OK —',
            $totais['jogos'],
            $totais['fases'],
            $totais['com_evento_real'],
            $totais['sem_jogo'],
        ));

        return self::SUCCESS;
    }
}
