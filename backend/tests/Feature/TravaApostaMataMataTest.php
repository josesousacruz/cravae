<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Fase;
use App\Models\Jogo;
use App\Models\Selecao;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoApostas;
use Database\Seeders\BolaoMataMataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravaApostaMataMataTest extends TestCase
{
    use RefreshDatabase;

    private function cupomDoMataMata(Torneio $torneio): array
    {
        $usuario = Usuario::query()->create([
            'nome' => 'Apostador MM',
            'email' => 'mm@teste.local',
            'telefone' => '71999999999',
            'cpf_cnpj' => '12345678901',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        $cupom = Cupom::query()->create([
            'torneio_id' => $torneio->id,
            'usuario_id' => $usuario->id,
            'codigo' => 'MM-TESTE-1',
            'status' => 'ativo',
        ]);

        return [$usuario, $cupom];
    }

    public function test_nao_cria_aposta_em_jogo_de_mata_mata_sem_participantes(): void
    {
        $this->seed(BolaoMataMataSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();
        [$usuario, $cupom] = $this->cupomDoMataMata($torneio);

        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $r32->id)->firstOrFail();
        $jogo->forceFill(['data_hora_inicio' => now()->addDays(10)])->save();
        $this->assertNull($jogo->selecao_mandante_id);

        app(ServicoApostas::class)->salvarLote($cupom, $usuario, [[
            'tipo' => 'placar_jogo_eliminatoria',
            'jogo_id' => $jogo->id,
            'placar_mandante' => 2,
            'placar_visitante' => 1,
        ]]);

        $this->assertDatabaseMissing('apostas', ['cupom_id' => $cupom->id, 'jogo_id' => $jogo->id]);
    }

    public function test_cria_aposta_quando_o_jogo_tem_times_definidos(): void
    {
        $this->seed(BolaoMataMataSeeder::class);
        $torneio = Torneio::query()->where('edicao', '2026-MM')->firstOrFail();
        [$usuario, $cupom] = $this->cupomDoMataMata($torneio);

        $bra = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'BRA')->firstOrFail();
        $jpn = Selecao::query()->where('torneio_id', $torneio->id)->where('sigla', 'JPN')->firstOrFail();
        $r32 = Fase::query()->where('torneio_id', $torneio->id)->where('slug', 'round_of_32')->firstOrFail();
        $jogo = Jogo::query()->where('torneio_id', $torneio->id)->where('fase_id', $r32->id)->firstOrFail();
        $jogo->forceFill([
            'selecao_mandante_id' => $bra->id,
            'selecao_visitante_id' => $jpn->id,
            'data_hora_inicio' => now()->addDays(10),
        ])->save();

        app(ServicoApostas::class)->salvarLote($cupom, $usuario, [[
            'tipo' => 'placar_jogo_eliminatoria',
            'jogo_id' => $jogo->id,
            'placar_mandante' => 2,
            'placar_visitante' => 1,
        ]]);

        $aposta = \App\Models\Aposta::query()->where('cupom_id', $cupom->id)->where('jogo_id', $jogo->id)->first();
        $this->assertNotNull($aposta, 'aposta deve ser criada quando o jogo tem times');
        $this->assertSame($bra->id, (int) ($aposta->conteudo['selecao_classificada_id'] ?? 0));
    }
}
