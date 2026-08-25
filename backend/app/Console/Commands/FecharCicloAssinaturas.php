<?php

namespace App\Console\Commands;

use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoAssinatura;
use Illuminate\Console\Command;

/**
 * Virada de competência do modelo de assinatura: abre os ciclos pendentes do
 * mês corrente e rebaixa a inadimplente quem não pagou o ciclo anterior
 * (inadimplente = tier gratuito: mantém palpites, sai da disputa).
 *
 * Com --cupons-gratuitos, também garante o cupom gratuito de usuários antigos
 * (criados antes da fase 08) nos torneios publicados.
 *
 *   php artisan assinaturas:fechar-ciclo
 *   php artisan assinaturas:fechar-ciclo --cupons-gratuitos
 */
class FecharCicloAssinaturas extends Command
{
    protected $signature = 'assinaturas:fechar-ciclo
        {--competencia= : Competência YYYY-MM (default: mês corrente)}
        {--cupons-gratuitos : Garante o cupom gratuito de todos os usuários nos torneios publicados}';

    protected $description = 'Abre os ciclos da competência corrente e rebaixa assinaturas sem pagamento do ciclo anterior.';

    public function handle(ServicoAssinatura $servicoAssinatura): int
    {
        $resultado = $servicoAssinatura->fecharCiclo($this->option('competencia') ?: null);

        $this->info("Ciclos abertos: {$resultado['ciclos_abertos']} | Rebaixadas por inadimplência: {$resultado['rebaixadas']}");

        if ($this->option('cupons-gratuitos')) {
            $criados = 0;
            $torneios = Torneio::query()->where('status', 'publicado')->get();

            Usuario::query()->each(function (Usuario $usuario) use ($torneios, $servicoAssinatura, &$criados) {
                foreach ($torneios as $torneio) {
                    $cupom = $servicoAssinatura->garantirCupomGratuito($usuario, $torneio);
                    if ($cupom->wasRecentlyCreated) {
                        $criados++;
                    }
                }
            });

            $this->info("Cupons gratuitos criados: {$criados}");
        }

        return self::SUCCESS;
    }
}
