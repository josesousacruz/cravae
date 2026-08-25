<?php

namespace App\Console\Commands;

use App\Models\Aposta;
use App\Models\Jogo;
use App\Models\ResultadoJogo;
use App\Services\ServicoPontuacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Troca os palpites (apostas de placar) entre dois jogos cujos confrontos foram
 * corrigidos/invertidos no banco DEPOIS que os usuários já haviam palpitado.
 *
 * Cenário que motivou o comando: dois jogos do mata-mata estavam com os times
 * trocados entre si; ao corrigir as seleções nos registros de `jogos`, as
 * apostas continuaram apontando para o jogo_id antigo — o conteudo
 * (placares + selecao_classificada_id) passou a não bater com os times do
 * jogo, e o frontend exibe "Repalpite necessário" para todo mundo.
 *
 * O que ele faz: troca o jogo_id (e fase/rodada/grupo, alinhados ao jogo de
 * destino) entre as apostas dos dois jogos. O conteudo NÃO é alterado: os
 * placares e o classificado referem-se aos times, que voltam a corresponder.
 *
 * Não toca em eventos_pontuacao/pontuacoes_cupons — rode com --recalcular (ou
 * dispare RecalcularPontuacaoTorneioJob) se já houver resultado lançado.
 *
 *   php artisan apostas:trocar-palpites-jogos {jogoA} {jogoB} --dry-run
 *   php artisan apostas:trocar-palpites-jogos {jogoA} {jogoB}
 *   php artisan apostas:trocar-palpites-jogos {jogoA} {jogoB} --trocar-resultados --recalcular
 */
class TrocarPalpitesEntreJogos extends Command
{
    protected $signature = 'apostas:trocar-palpites-jogos
        {jogoA : ID do primeiro jogo}
        {jogoB : ID do segundo jogo}
        {--dry-run : Mostra o que mudaria sem gravar}
        {--trocar-resultados : Troca também os resultados_jogos entre os dois jogos}
        {--recalcular : Recalcula a pontuação do torneio ao final (síncrono)}';

    protected $description = 'Troca os palpites de placar entre dois jogos cujos confrontos foram corrigidos após as apostas.';

    public function handle(ServicoPontuacao $servicoPontuacao): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $jogoA = Jogo::query()->with(['selecaoMandante', 'selecaoVisitante', 'resultado'])->find($this->argument('jogoA'));
        $jogoB = Jogo::query()->with(['selecaoMandante', 'selecaoVisitante', 'resultado'])->find($this->argument('jogoB'));

        if (! $jogoA || ! $jogoB) {
            $this->error('Jogo não encontrado.');

            return self::FAILURE;
        }

        if ($jogoA->torneio_id !== $jogoB->torneio_id) {
            $this->error('Os jogos pertencem a torneios diferentes. Abortando.');

            return self::FAILURE;
        }

        $this->descreverJogo('Jogo A', $jogoA);
        $this->descreverJogo('Jogo B', $jogoB);

        $apostasA = $this->apostasDoJogo($jogoA);
        $apostasB = $this->apostasDoJogo($jogoB);

        $this->line(sprintf('Apostas no jogo A: %d | no jogo B: %d', $apostasA->count(), $apostasB->count()));

        // Sanidade: um palpite de eliminatória só pertence ao jogo de destino se o
        // classificado salvo estiver entre os times de lá. Contamos antes de trocar.
        $okParaB = $apostasA->filter(fn (Aposta $a) => $this->classificadoBateComJogo($a, $jogoB))->count();
        $okParaA = $apostasB->filter(fn (Aposta $a) => $this->classificadoBateComJogo($a, $jogoA))->count();
        $this->line(sprintf(
            'Classificado compatível com o destino: A->B %d/%d | B->A %d/%d',
            $okParaB,
            $apostasA->count(),
            $okParaA,
            $apostasB->count(),
        ));

        if ($okParaB < $apostasA->count() || $okParaA < $apostasB->count()) {
            $this->warn('Há palpites cujo classificado NÃO bate com os times do jogo de destino. Confira antes de rodar sem --dry-run.');
        }

        DB::transaction(function () use ($jogoA, $jogoB, $apostasA, $apostasB, $dryRun) {
            $this->moverApostas($apostasA, $jogoB, $dryRun);
            $this->moverApostas($apostasB, $jogoA, $dryRun);

            if ($this->option('trocar-resultados')) {
                $this->trocarResultados($jogoA, $jogoB, $dryRun);
            }
        });

        if ($this->option('recalcular') && ! $dryRun) {
            $this->info('Recalculando pontuação do torneio...');
            $servicoPontuacao->recalcularTorneio($jogoA->torneio);
        }

        $this->info($dryRun ? '[DRY-RUN] Nada foi gravado.' : 'OK — palpites trocados.');

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Aposta>
     */
    private function apostasDoJogo(Jogo $jogo)
    {
        return Aposta::query()
            ->where('jogo_id', $jogo->id)
            ->whereIn('tipo', ['placar_jogo_grupos', 'placar_jogo_eliminatoria'])
            ->get();
    }

    private function classificadoBateComJogo(Aposta $aposta, Jogo $jogo): bool
    {
        $classificado = $aposta->conteudo['selecao_classificada_id'] ?? null;

        if ($classificado === null) {
            return true; // fase de grupos ou palpite antigo sem classificado: nada a validar
        }

        return in_array((int) $classificado, [
            (int) $jogo->selecao_mandante_id,
            (int) $jogo->selecao_visitante_id,
        ], true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Aposta>  $apostas
     */
    private function moverApostas($apostas, Jogo $destino, bool $dryRun): void
    {
        foreach ($apostas as $aposta) {
            $this->line(sprintf(
                '  aposta #%d (cupom %d): jogo %d -> %d',
                $aposta->id,
                $aposta->cupom_id,
                $aposta->jogo_id,
                $destino->id,
            ));

            if ($dryRun) {
                continue;
            }

            $aposta->forceFill([
                'jogo_id' => $destino->id,
                'fase_id' => $destino->fase_id,
                'rodada_id' => $destino->rodada_id,
                'grupo_id' => $destino->grupo_id,
            ])->save();
        }
    }

    private function trocarResultados(Jogo $jogoA, Jogo $jogoB, bool $dryRun): void
    {
        $resultadoA = ResultadoJogo::query()->where('jogo_id', $jogoA->id)->first();
        $resultadoB = ResultadoJogo::query()->where('jogo_id', $jogoB->id)->first();

        if (! $resultadoA && ! $resultadoB) {
            $this->line('Nenhum resultado lançado para os dois jogos. Nada a trocar.');

            return;
        }

        $this->line(sprintf(
            'Trocando resultados: jogo %d (%s) <-> jogo %d (%s)',
            $jogoA->id,
            $resultadoA ? 'com resultado' : 'sem resultado',
            $jogoB->id,
            $resultadoB ? 'com resultado' : 'sem resultado',
        ));

        if ($dryRun) {
            return;
        }

        $resultadoA?->forceFill(['jogo_id' => $jogoB->id])->save();
        $resultadoB?->forceFill(['jogo_id' => $jogoA->id])->save();
    }

    private function descreverJogo(string $rotulo, Jogo $jogo): void
    {
        $this->line(sprintf(
            '%s: #%d — %s x %s | %s | evento externo: %s | resultado: %s',
            $rotulo,
            $jogo->id,
            $jogo->selecaoMandante?->nome ?? '(indefinido)',
            $jogo->selecaoVisitante?->nome ?? '(indefinido)',
            $jogo->data_hora_inicio?->format('d/m H:i') ?? '(sem data)',
            $jogo->id_evento_externo ?? '(nenhum)',
            $jogo->resultado ? sprintf('%dx%d', $jogo->resultado->placar_mandante, $jogo->resultado->placar_visitante) : '(nenhum)',
        ));
    }
}
