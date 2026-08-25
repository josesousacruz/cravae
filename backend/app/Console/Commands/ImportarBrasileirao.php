<?php

namespace App\Console\Commands;

use App\Models\Fase;
use App\Models\Jogador;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\ResultadoJogo;
use App\Models\Rodada;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Services\ServicoTheSportsDb;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Importa o Brasileirão Série A da TheSportsDB: torneio, clubes (com escudo),
 * as 38 rodadas com jogos e placares já encerrados, elencos (com foto) e as
 * regras de pontuação do Cravaê.
 *
 * Idempotente: pode rodar de novo a qualquer momento (updateOrCreate por
 * chaves externas). Com chave free os elencos vêm cortados (~10 por clube);
 * rodar novamente com THESPORTSDB_KEY premium completa os elencos.
 */
class ImportarBrasileirao extends Command
{
    protected $signature = 'cravae:importar-brasileirao
        {--temporada= : Temporada (default: config thesportsdb.temporada_brasileirao)}
        {--sem-elencos : Pula a importação dos elencos}
        {--so-elencos : Importa apenas os elencos (torneio já importado)}';

    protected $description = 'Importa torneio, clubes, rodadas, jogos e elencos do Brasileirão (TheSportsDB)';

    public function __construct(private readonly ServicoTheSportsDb $servico)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $idLiga = (int) config('thesportsdb.id_liga_brasileirao');
        $temporada = (string) ($this->option('temporada') ?: config('thesportsdb.temporada_brasileirao'));
        $totalRodadas = (int) config('thesportsdb.total_rodadas_brasileirao', 38);

        $this->info("Importando Brasileirão Série A {$temporada} (liga {$idLiga})...");

        if ($this->option('so-elencos')) {
            $torneio = Torneio::query()
                ->where('liga_externa_id', $idLiga)
                ->where('temporada_externa', $temporada)
                ->firstOrFail();
            $this->importarElencos($torneio);

            return self::SUCCESS;
        }

        $torneio = Torneio::query()->updateOrCreate(
            ['liga_externa_id' => $idLiga, 'temporada_externa' => $temporada],
            [
                'nome' => 'Brasileirão Série A',
                'edicao' => $temporada,
                'status' => 'publicado',
                'valor_cupom' => 10,
                'compras_abertas' => true,
            ],
        );

        if (! $torneio->imagem_url && ($imagem = $this->servico->imagemDaLiga($idLiga))) {
            $torneio->forceFill(['imagem_url' => $imagem])->save();
        }

        $fase = Fase::query()->updateOrCreate(
            ['torneio_id' => $torneio->id, 'slug' => 'pontos-corridos'],
            ['nome' => 'Pontos Corridos', 'ordem' => 1, 'tipo' => 'pontos_corridos'],
        );

        $times = [];
        $jogosImportados = 0;
        $resultadosImportados = 0;

        for ($r = 1; $r <= $totalRodadas; $r++) {
            $eventos = $this->servico->eventosDaRodada($r, $temporada, $idLiga);

            $rodada = Rodada::query()->updateOrCreate(
                ['fase_id' => $fase->id, 'ordem' => $r],
                ['nome' => "Rodada {$r}"],
            );

            foreach ($eventos as $evento) {
                $idEvento = (int) ($evento['idEvent'] ?? 0);
                $idCasa = (int) ($evento['idHomeTeam'] ?? 0);
                $idFora = (int) ($evento['idAwayTeam'] ?? 0);

                if ($idEvento === 0 || $idCasa === 0 || $idFora === 0) {
                    continue;
                }

                foreach ([
                    [$idCasa, $evento['strHomeTeam'] ?? '', $evento['strHomeTeamBadge'] ?? null],
                    [$idFora, $evento['strAwayTeam'] ?? '', $evento['strAwayTeamBadge'] ?? null],
                ] as [$idTime, $nomeTime, $escudo]) {
                    if (! isset($times[$idTime]) && $nomeTime !== '') {
                        $times[$idTime] = ['nome' => $nomeTime, 'escudo' => $escudo];
                    }
                }

                $mandante = $this->selecaoDoTime($torneio, $idCasa, $times[$idCasa] ?? null);
                $visitante = $this->selecaoDoTime($torneio, $idFora, $times[$idFora] ?? null);

                if (! $mandante || ! $visitante) {
                    continue;
                }

                $jogo = Jogo::query()->updateOrCreate(
                    ['torneio_id' => $torneio->id, 'id_evento_externo' => $idEvento],
                    [
                        'fase_id' => $fase->id,
                        'rodada_id' => $rodada->id,
                        'grupo_id' => null,
                        'selecao_mandante_id' => $mandante->id,
                        'selecao_visitante_id' => $visitante->id,
                        'data_hora_inicio' => $this->dataBrtDoEvento($evento) ?? now()->format('Y-m-d H:i:s'),
                        'ordem_na_fase' => $r,
                        'status' => ($evento['strStatus'] ?? '') === 'FT' ? 'encerrado' : 'agendado',
                    ],
                );
                $jogosImportados++;

                if (($evento['strStatus'] ?? '') === 'FT'
                    && $evento['intHomeScore'] !== null
                    && $evento['intAwayScore'] !== null) {
                    ResultadoJogo::query()->updateOrCreate(
                        ['jogo_id' => $jogo->id],
                        [
                            'placar_mandante' => (int) $evento['intHomeScore'],
                            'placar_visitante' => (int) $evento['intAwayScore'],
                            'encerrado_at' => now(),
                        ],
                    );
                    $resultadosImportados++;
                }
            }

            $this->line("Rodada {$r}: ".count($eventos).' jogos');
            usleep(300_000);
        }

        // Janela do torneio (usada, entre outros, pelo fechamento do pódio).
        $inicio = Jogo::query()->where('torneio_id', $torneio->id)->min('data_hora_inicio');
        $fim = Jogo::query()->where('torneio_id', $torneio->id)->max('data_hora_inicio');
        if ($inicio && $fim) {
            $torneio->forceFill(['data_inicio' => $inicio, 'data_fim' => $fim])->save();
        }

        $this->completarSiglasEEscudos($torneio);

        if (! $this->option('sem-elencos')) {
            $this->importarElencos($torneio);
        }

        $this->seedRegrasPontuacao($torneio);

        $this->info("Concluído: {$jogosImportados} jogos, {$resultadosImportados} resultados, ".count($times).' clubes.');

        return self::SUCCESS;
    }

    /**
     * @param  array{nome:string,escudo:?string}|null  $dados
     */
    private function selecaoDoTime(Torneio $torneio, int $idExterno, ?array $dados): ?Selecao
    {
        $selecao = Selecao::query()
            ->where('torneio_id', $torneio->id)
            ->where('id_externo', $idExterno)
            ->first();

        if ($selecao) {
            if ($dados && ! $selecao->escudo_url && $dados['escudo']) {
                $selecao->forceFill(['escudo_url' => $dados['escudo']])->save();
            }

            return $selecao;
        }

        if (! $dados) {
            return null;
        }

        // Sigla provisória derivada do nome; lookupteam depois preenche a oficial.
        $sigla = $this->siglaDisponivel($torneio, Str::upper(Str::substr(Str::slug($dados['nome'], ''), 0, 3)));

        return Selecao::query()->create([
            'torneio_id' => $torneio->id,
            'grupo_id' => null,
            'nome' => $dados['nome'],
            'sigla' => $sigla,
            'id_externo' => $idExterno,
            'slug' => Str::slug($dados['nome']),
            'escudo_url' => $dados['escudo'],
            'ativo' => true,
        ]);
    }

    /**
     * Garante unicidade da sigla dentro do torneio (coluna char(3) com unique):
     * em colisão (ex.: Corinthians e Coritiba → COR), troca o último caractere
     * por um dígito incremental (CO1, CO2...).
     */
    private function siglaDisponivel(Torneio $torneio, string $sigla): string
    {
        $existe = fn (string $s) => Selecao::query()
            ->where('torneio_id', $torneio->id)
            ->where('sigla', $s)
            ->exists();

        if (! $existe($sigla)) {
            return $sigla;
        }

        for ($i = 1; $i <= 9; $i++) {
            $candidata = Str::substr($sigla, 0, 2).$i;
            if (! $existe($candidata)) {
                return $candidata;
            }
        }

        return Str::upper(Str::random(3));
    }

    private function completarSiglasEEscudos(Torneio $torneio): void
    {
        $this->info('Buscando siglas oficiais dos clubes (lookupteam)...');

        Selecao::query()
            ->where('torneio_id', $torneio->id)
            ->whereNotNull('id_externo')
            ->each(function (Selecao $selecao) {
                $time = $this->servico->lookupTime((int) $selecao->id_externo);

                if ($time) {
                    $sigla = Str::upper(Str::substr((string) ($time['strTeamShort'] ?: $selecao->sigla), 0, 3));

                    $emUso = Selecao::query()
                        ->where('torneio_id', $selecao->torneio_id)
                        ->where('sigla', $sigla)
                        ->where('id', '!=', $selecao->id)
                        ->exists();

                    $selecao->forceFill([
                        'sigla' => $emUso ? $selecao->sigla : $sigla,
                        'escudo_url' => $time['strBadge'] ?? $selecao->escudo_url,
                    ])->save();
                }

                usleep(500_000);
            });
    }

    private function importarElencos(Torneio $torneio): void
    {
        $this->info('Importando elencos (free tier corta em ~10 por clube; premium completa)...');

        Selecao::query()
            ->where('torneio_id', $torneio->id)
            ->whereNotNull('id_externo')
            ->each(function (Selecao $selecao) {
                $elenco = $this->servico->elencoDoTime((int) $selecao->id_externo);
                $importados = 0;

                foreach ($elenco as $atleta) {
                    $idExterno = (int) ($atleta['idPlayer'] ?? 0);
                    $nome = trim((string) ($atleta['strPlayer'] ?? ''));

                    if ($idExterno === 0 || $nome === '' || ($atleta['strSport'] ?? 'Soccer') !== 'Soccer') {
                        continue;
                    }

                    Jogador::query()->updateOrCreate(
                        ['id_externo' => $idExterno, 'selecao_id' => $selecao->id],
                        [
                            'nome' => $nome,
                            'posicao' => $atleta['strPosition'] ?? null,
                            'numero_camisa' => is_numeric($atleta['strNumber'] ?? null) ? (int) $atleta['strNumber'] : null,
                            'foto_url' => $atleta['strCutout'] ?: ($atleta['strThumb'] ?? null),
                            'ativo' => ($atleta['strStatus'] ?? 'Active') === 'Active',
                        ],
                    );
                    $importados++;
                }

                $this->line("  {$selecao->nome}: {$importados} jogadores");
                // Free tier limita ~30 req/min; 4s mantém folga.
                usleep(4_000_000);
            });
    }

    private function seedRegrasPontuacao(Torneio $torneio): void
    {
        // Valores decididos no grilling de 2026-07-21: níveis exclusivos
        // (resultado 10 < placar exato 25) + bônus por goleador (5) + bônus
        // cheio do placar exato com todos os autores (15). 0x0 exato = cheio.
        $regras = [
            ['chave' => 'apenas_vencedor', 'nome' => 'Acertou o vencedor', 'pontos' => 10, 'ativo' => true],
            ['chave' => 'empate_sem_placar', 'nome' => 'Acertou o empate (sem placar)', 'pontos' => 10, 'ativo' => true],
            ['chave' => 'vencedor_e_acertou_gols', 'nome' => 'Vencedor e gols de um dos times', 'pontos' => 10, 'ativo' => true],
            ['chave' => 'acertou_1_placar', 'nome' => 'Acertou um dos placares', 'pontos' => 0, 'ativo' => false],
            ['chave' => 'placar_exato_fase_grupos', 'nome' => 'Placar exato', 'pontos' => 25, 'ativo' => true],
            ['chave' => 'goleador_acertado', 'nome' => 'Goleador acertado (por gol)', 'pontos' => 5, 'ativo' => true],
            ['chave' => 'placar_exato_goleadores', 'nome' => 'Placar exato com todos os goleadores', 'pontos' => 15, 'ativo' => true],
        ];

        foreach ($regras as $regra) {
            RegraPontuacao::query()->updateOrCreate(
                ['torneio_id' => $torneio->id, 'fase_id' => null, 'chave' => $regra['chave']],
                ['nome' => $regra['nome'], 'pontos' => $regra['pontos'], 'ativo' => $regra['ativo'], 'descricao' => null],
            );
        }

        $this->info('Regras de pontuação do Cravaê aplicadas.');
    }

    /**
     * Converte a data do evento (UTC) para wall-clock de Brasília, convenção
     * de data_hora_inicio em todo o app (ver ServicoFechamentoApostas::comoBrt).
     *
     * @param  array<string,mixed>  $evento
     */
    private function dataBrtDoEvento(array $evento): ?string
    {
        $iso = $evento['strTimestamp'] ?? null;

        if (! $iso) {
            $dia = $evento['dateEvent'] ?? null;
            if (! $dia) {
                return null;
            }
            $iso = trim($dia.' '.($evento['strTime'] ?? '00:00:00'));
        }

        try {
            return Carbon::parse($iso, 'UTC')
                ->setTimezone('America/Sao_Paulo')
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
