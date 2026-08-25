<?php

namespace Tests\Feature;

use App\Models\Aposta;
use App\Models\Cupom;
use App\Models\Fase;
use App\Models\Jogador;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\ResultadoJogo;
use App\Models\Rodada;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoApostas;
use App\Services\ServicoPontuacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CravaeGoleadoresTest extends TestCase
{
    use RefreshDatabase;

    private Torneio $torneio;

    private Jogo $jogo;

    private Cupom $cupom;

    private Usuario $usuario;

    private Jogador $atacanteMandante;

    private Jogador $zagueiroMandante;

    private Jogador $atacanteVisitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->torneio = Torneio::query()->create([
            'nome' => 'Brasileirão Série A',
            'edicao' => '2026',
            'status' => 'publicado',
        ]);

        $fase = Fase::query()->create([
            'torneio_id' => $this->torneio->id,
            'nome' => 'Pontos Corridos',
            'slug' => 'pontos-corridos',
            'ordem' => 1,
            'tipo' => 'pontos_corridos',
        ]);

        $rodada = Rodada::query()->create([
            'fase_id' => $fase->id,
            'nome' => 'Rodada 1',
            'ordem' => 1,
        ]);

        $mandante = Selecao::query()->create([
            'torneio_id' => $this->torneio->id,
            'nome' => 'Flamengo',
            'sigla' => 'FLA',
            'slug' => 'flamengo',
            'ativo' => true,
        ]);

        $visitante = Selecao::query()->create([
            'torneio_id' => $this->torneio->id,
            'nome' => 'Palmeiras',
            'sigla' => 'PAL',
            'slug' => 'palmeiras',
            'ativo' => true,
        ]);

        $this->atacanteMandante = Jogador::query()->create([
            'selecao_id' => $mandante->id, 'nome' => 'Pedro', 'ativo' => true,
        ]);
        $this->zagueiroMandante = Jogador::query()->create([
            'selecao_id' => $mandante->id, 'nome' => 'Léo Pereira', 'ativo' => true,
        ]);
        $this->atacanteVisitante = Jogador::query()->create([
            'selecao_id' => $visitante->id, 'nome' => 'Estêvão', 'ativo' => true,
        ]);

        $this->jogo = Jogo::query()->create([
            'torneio_id' => $this->torneio->id,
            'fase_id' => $fase->id,
            'rodada_id' => $rodada->id,
            'selecao_mandante_id' => $mandante->id,
            'selecao_visitante_id' => $visitante->id,
            'data_hora_inicio' => now('America/Sao_Paulo')->addDay()->format('Y-m-d H:i:s'),
            'status' => 'agendado',
        ]);

        foreach ([
            ['chave' => 'apenas_vencedor', 'pontos' => 10],
            ['chave' => 'empate_sem_placar', 'pontos' => 10],
            ['chave' => 'vencedor_e_acertou_gols', 'pontos' => 10],
            ['chave' => 'placar_exato_fase_grupos', 'pontos' => 25],
            ['chave' => 'goleador_acertado', 'pontos' => 5],
            ['chave' => 'placar_exato_goleadores', 'pontos' => 15],
        ] as $regra) {
            RegraPontuacao::query()->create([
                'torneio_id' => $this->torneio->id,
                'fase_id' => null,
                'chave' => $regra['chave'],
                'nome' => $regra['chave'],
                'pontos' => $regra['pontos'],
                'ativo' => true,
            ]);
        }

        $this->usuario = Usuario::query()->create([
            'nome' => 'Testador',
            'email' => 'testador@cravae.local',
            'password' => Hash::make('12345678'),
        ]);

        $this->cupom = Cupom::query()->create([
            'usuario_id' => $this->usuario->id,
            'torneio_id' => $this->torneio->id,
            'codigo' => 'CRAVAE01',
            'status' => 'ativo',
        ]);
    }

    private function salvarPalpite(array $extras = []): void
    {
        app(ServicoApostas::class)->salvarLote($this->cupom, $this->usuario, [array_merge([
            'tipo' => 'placar_jogo_grupos',
            'jogo_id' => $this->jogo->id,
            'placar_mandante' => 2,
            'placar_visitante' => 1,
            'goleadores_mandante' => [$this->atacanteMandante->id, $this->zagueiroMandante->id],
            'goleadores_visitante' => [$this->atacanteVisitante->id],
        ], $extras)]);
    }

    private function lancarResultado(int $placarMandante, int $placarVisitante, ?array $golsMandante, ?array $golsVisitante): void
    {
        ResultadoJogo::query()->updateOrCreate(['jogo_id' => $this->jogo->id], [
            'placar_mandante' => $placarMandante,
            'placar_visitante' => $placarVisitante,
            'goleadores_mandante' => $golsMandante,
            'goleadores_visitante' => $golsVisitante,
            'encerrado_at' => now(),
        ]);
    }

    private function pontuacaoTotal(): float
    {
        app(ServicoPontuacao::class)->recalcularCupom($this->cupom->fresh(), $this->torneio->fresh());

        return (float) $this->cupom->fresh()->pontuacao->pontuacao_total;
    }

    public function test_salva_palpite_com_goleadores_normalizados(): void
    {
        $this->salvarPalpite();

        $aposta = Aposta::query()->where('cupom_id', $this->cupom->id)->firstOrFail();

        $this->assertSame(
            [$this->atacanteMandante->id, $this->zagueiroMandante->id],
            $aposta->conteudo['goleadores_mandante'],
        );
        $this->assertSame([$this->atacanteVisitante->id], $aposta->conteudo['goleadores_visitante']);
    }

    public function test_rejeita_goleador_de_outro_clube(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->salvarPalpite(['goleadores_mandante' => [$this->atacanteVisitante->id, null]]);
    }

    public function test_placar_exato_com_todos_goleadores_leva_tudo(): void
    {
        $this->salvarPalpite();
        // Real: 2x1, Pedro e Léo Pereira; Estêvão para o visitante.
        $this->lancarResultado(2, 1,
            [['jogador_id' => $this->zagueiroMandante->id, 'contra' => false], ['jogador_id' => $this->atacanteMandante->id, 'contra' => false]],
            [['jogador_id' => $this->atacanteVisitante->id, 'contra' => false]],
        );

        // 25 (exato) + 3 goleadores x 5 + 15 (cheio) = 55. Ordem dos gols não importa.
        $this->assertSame(55.0, $this->pontuacaoTotal());
    }

    public function test_resultado_certo_com_um_goleador_soma_bonus_parcial(): void
    {
        $this->salvarPalpite();
        // Real: 3x1 (palpite 2x1 acerta só o vencedor + gols do visitante) e
        // dos autores palpitados só Pedro marcou.
        $this->lancarResultado(3, 1,
            [
                ['jogador_id' => $this->atacanteMandante->id, 'contra' => false],
                ['jogador_id' => 999999, 'contra' => false],
                ['jogador_id' => 999998, 'contra' => false],
            ],
            [['jogador_id' => $this->atacanteVisitante->id, 'contra' => false]],
        );

        // vencedor_e_acertou_gols (10) + 2 goleadores acertados (Pedro + Estêvão) x 5 = 20.
        $this->assertSame(20.0, $this->pontuacaoTotal());
    }

    public function test_gol_contra_pontua_quando_palpitado(): void
    {
        $this->salvarPalpite([
            'placar_mandante' => 1,
            'placar_visitante' => 0,
            'goleadores_mandante' => ['contra'],
            'goleadores_visitante' => [],
        ]);
        $this->lancarResultado(1, 0, [['jogador_id' => $this->atacanteVisitante->id, 'contra' => true]], []);

        // 25 (exato) + 1 goleador ('contra') x 5 + 15 (cheio) = 45.
        $this->assertSame(45.0, $this->pontuacaoTotal());
    }

    public function test_zero_a_zero_exato_leva_bonus_cheio(): void
    {
        $this->salvarPalpite([
            'placar_mandante' => 0,
            'placar_visitante' => 0,
            'goleadores_mandante' => [],
            'goleadores_visitante' => [],
        ]);
        // Placar importado sem timeline: goleadores null, mas 0 gols = conhecido.
        $this->lancarResultado(0, 0, null, null);

        // 25 (exato) + 15 (cheio trivial) = 40.
        $this->assertSame(40.0, $this->pontuacaoTotal());
    }

    public function test_sem_timeline_nao_da_bonus_nem_cheio(): void
    {
        $this->salvarPalpite();
        // Placar certo mas goleadores reais desconhecidos (import só de placar).
        $this->lancarResultado(2, 1, null, null);

        // Só o placar exato: 25. Bônus aguarda a timeline do sync.
        $this->assertSame(25.0, $this->pontuacaoTotal());
    }

    public function test_jogo_ja_iniciado_nao_aceita_palpite(): void
    {
        $this->jogo->forceFill([
            'data_hora_inicio' => now('America/Sao_Paulo')->subMinutes(5)->format('Y-m-d H:i:s'),
        ])->save();

        $this->salvarPalpite();

        $this->assertSame(0, Aposta::query()->where('cupom_id', $this->cupom->id)->count());
    }

    public function test_jogo_futuro_da_mesma_rodada_segue_aberto_apos_primeiro_jogo(): void
    {
        // Fechamento é POR JOGO: outro jogo da rodada já iniciado não fecha este.
        $outroJogo = Jogo::query()->create([
            'torneio_id' => $this->torneio->id,
            'fase_id' => $this->jogo->fase_id,
            'rodada_id' => $this->jogo->rodada_id,
            'selecao_mandante_id' => $this->jogo->selecao_visitante_id,
            'selecao_visitante_id' => $this->jogo->selecao_mandante_id,
            'data_hora_inicio' => now('America/Sao_Paulo')->subHours(2)->format('Y-m-d H:i:s'),
            'status' => 'em_andamento',
        ]);

        $this->salvarPalpite();

        $this->assertSame(1, Aposta::query()->where('cupom_id', $this->cupom->id)->count());
    }
}
