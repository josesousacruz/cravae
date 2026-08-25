<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\EventoPontuacao;
use App\Models\Fase;
use App\Models\Jogo;
use App\Models\RegraPontuacao;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CravaeRankingMensalTest extends TestCase
{
    use RefreshDatabase;

    private Torneio $torneio;

    private RegraPontuacao $regraExato;

    private RegraPontuacao $regraResultado;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.access_token' => '']);

        $this->torneio = Torneio::query()->create([
            'nome' => 'Brasileirão Teste',
            'edicao' => '2026',
            'status' => 'publicado',
            'valor_cupom' => 10,
            'principal' => true,
        ]);

        $this->regraExato = RegraPontuacao::query()->create([
            'torneio_id' => $this->torneio->id,
            'chave' => 'placar_exato',
            'nome' => 'Placar exato',
            'pontos' => 10,
            'ativo' => true,
        ]);

        $this->regraResultado = RegraPontuacao::query()->create([
            'torneio_id' => $this->torneio->id,
            'chave' => 'vencedor',
            'nome' => 'Resultado certo',
            'pontos' => 5,
            'ativo' => true,
        ]);
    }

    private function criarCupom(string $nome): Cupom
    {
        $usuario = Usuario::query()->create([
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@cravae.local',
            'password' => Hash::make('segredo123'),
            'perfil' => 'usuario',
        ]);

        return Cupom::query()->create([
            'usuario_id' => $usuario->id,
            'torneio_id' => $this->torneio->id,
            'codigo' => strtoupper(\Illuminate\Support\Str::random(10)),
            'status' => 'ativo',
            'origem' => 'gratuito',
        ]);
    }

    private function criarJogo(string $dataHora): Jogo
    {
        $fase = Fase::query()->firstOrCreate(
            ['torneio_id' => $this->torneio->id, 'slug' => 'pontos_corridos'],
            ['nome' => 'Pontos Corridos', 'ordem' => 1, 'tipo' => 'pontos_corridos'],
        );

        return Jogo::query()->create([
            'torneio_id' => $this->torneio->id,
            'fase_id' => $fase->id,
            'data_hora_inicio' => $dataHora,
            'ordem_na_fase' => 1,
            'status' => 'agendado',
        ]);
    }

    private function pontuar(Cupom $cupom, Jogo $jogo, RegraPontuacao $regra): void
    {
        EventoPontuacao::query()->create([
            'cupom_id' => $cupom->id,
            'regra_pontuacao_id' => $regra->id,
            'jogo_id' => $jogo->id,
            'pontos' => $regra->pontos,
            'descricao' => $regra->nome,
        ]);
    }

    public function test_competencia_soma_apenas_jogos_do_mes(): void
    {
        $ana = $this->criarCupom('Ana Agosto');
        $beto = $this->criarCupom('Beto Julho');

        $jogoAgosto = $this->criarJogo('2026-08-10 16:00:00');
        $jogoJulho = $this->criarJogo('2026-07-10 16:00:00');

        $this->pontuar($ana, $jogoAgosto, $this->regraResultado);   // 5 pts em agosto
        $this->pontuar($beto, $jogoJulho, $this->regraExato);       // 10 pts em julho
        $this->pontuar($beto, $jogoAgosto, $this->regraResultado);  // 5 pts em agosto

        // Evento sem jogo (ex.: campeão) fica fora do recorte mensal.
        EventoPontuacao::query()->create([
            'cupom_id' => $ana->id,
            'regra_pontuacao_id' => $this->regraExato->id,
            'jogo_id' => null,
            'pontos' => 25,
            'descricao' => 'Campeã',
        ]);

        $resposta = $this->getJson("/api/torneios/{$this->torneio->id}/ranking?competencia=2026-08");

        $resposta->assertOk()->assertJsonPath('competencia', '2026-08');
        $ranking = $resposta->json('ranking');

        $this->assertCount(2, $ranking);
        $porNome = collect($ranking)->keyBy(fn ($item) => $item['cupom']['usuario']['nome']);
        $this->assertSame('5', (string) (int) $porNome['Ana Agosto']['pontuacao_total']);
        $this->assertSame('5', (string) (int) $porNome['Beto Julho']['pontuacao_total']);
    }

    public function test_desempate_mensal_por_placares_exatos(): void
    {
        $ana = $this->criarCupom('Ana Exata');
        $beto = $this->criarCupom('Beto Dobrado');

        $jogoA = $this->criarJogo('2026-08-05 16:00:00');
        $jogoB = $this->criarJogo('2026-08-12 16:00:00');

        $this->pontuar($ana, $jogoA, $this->regraExato);        // 10 (1 exato)
        $this->pontuar($beto, $jogoA, $this->regraResultado);   // 5
        $this->pontuar($beto, $jogoB, $this->regraResultado);   // 5 (total 10, 0 exatos)

        $ranking = $this->getJson("/api/torneios/{$this->torneio->id}/ranking?competencia=2026-08")->json('ranking');

        $this->assertSame('Ana Exata', $ranking[0]['cupom']['usuario']['nome']);
        $this->assertSame(1, $ranking[0]['quantidade_placares_exatos']);
    }

    public function test_competencia_invalida_cai_no_ranking_geral(): void
    {
        $resposta = $this->getJson("/api/torneios/{$this->torneio->id}/ranking?competencia=agosto");

        $resposta->assertOk();
        $this->assertArrayNotHasKey('competencia', $resposta->json());
    }
}
