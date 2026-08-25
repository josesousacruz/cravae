<?php

namespace Tests\Feature;

use App\Models\Assinatura;
use App\Models\CicloAssinatura;
use App\Models\Cupom;
use App\Models\Plano;
use App\Models\Torneio;
use App\Models\Usuario;
use App\Services\ServicoAssinatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CravaeAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    private Plano $plano;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Testa o domínio sem tocar a API do Asaas (modo manual/sandbox local).
        config(['services.asaas.access_token' => '']);

        $this->plano = Plano::query()->create([
            'nome' => 'Assinante',
            'slug' => 'assinante',
            'preco_centavos' => 990,
            'preco_fundador_centavos' => 495,
            'vagas_fundador' => 2,
            'max_cupons' => 1,
            'ativo' => true,
        ]);

        $this->usuario = Usuario::query()->create([
            'nome' => 'Palpiteiro',
            'email' => 'palpiteiro@cravae.local',
            'password' => Hash::make('segredo123'),
            'perfil' => 'usuario',
        ]);
    }

    private function servico(): ServicoAssinatura
    {
        return app(ServicoAssinatura::class);
    }

    public function test_contratar_dentro_das_vagas_recebe_preco_de_fundador(): void
    {
        $assinatura = $this->servico()->contratar($this->usuario, $this->plano);

        $this->assertTrue($assinatura->fundador);
        $this->assertSame(495, $assinatura->preco_centavos_contratado);
        $this->assertSame(Assinatura::STATUS_ATIVA, $assinatura->status);
        $this->assertSame(1, $assinatura->ciclos()->count());
        $this->assertSame(1, $this->plano->vagasFundadorRestantes());
    }

    public function test_esgotadas_as_vagas_o_preco_e_cheio(): void
    {
        foreach (range(1, 2) as $indice) {
            $outro = Usuario::query()->create([
                'nome' => "Fundador {$indice}",
                'email' => "fundador{$indice}@cravae.local",
                'password' => Hash::make('segredo123'),
                'perfil' => 'usuario',
            ]);
            $this->servico()->contratar($outro, $this->plano);
        }

        $assinatura = $this->servico()->contratar($this->usuario, $this->plano);

        $this->assertFalse($assinatura->fundador);
        $this->assertSame(990, $assinatura->preco_centavos_contratado);
        $this->assertSame(0, $this->plano->vagasFundadorRestantes());
    }

    public function test_cancelar_libera_vaga_de_fundador(): void
    {
        $assinatura = $this->servico()->contratar($this->usuario, $this->plano);
        $this->assertSame(1, $this->plano->vagasFundadorRestantes());

        $this->servico()->cancelar($assinatura);

        $this->assertSame(Assinatura::STATUS_CANCELADA, $assinatura->refresh()->status);
        $this->assertSame(2, $this->plano->vagasFundadorRestantes());
    }

    public function test_nao_permite_duas_assinaturas_em_vigor(): void
    {
        $this->servico()->contratar($this->usuario, $this->plano);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->servico()->contratar($this->usuario, $this->plano);
    }

    public function test_elegibilidade_exige_ciclo_pago_da_competencia(): void
    {
        $assinatura = $this->servico()->contratar($this->usuario, $this->plano);
        $competencia = $this->servico()->competenciaCorrente();

        $this->assertFalse($this->servico()->usuarioElegivelNaCompetencia($this->usuario->id));

        $this->servico()->registrarPagamentoCiclo($assinatura, $competencia);

        $this->assertTrue($this->servico()->usuarioElegivelNaCompetencia($this->usuario->id));
        $this->assertContains($this->usuario->id, $this->servico()->usuariosElegiveisNaCompetencia());
    }

    public function test_pagamento_reativa_assinatura_inadimplente(): void
    {
        $assinatura = $this->servico()->contratar($this->usuario, $this->plano);
        $assinatura->forceFill(['status' => Assinatura::STATUS_INADIMPLENTE])->save();

        $this->servico()->registrarPagamentoCiclo($assinatura, $this->servico()->competenciaCorrente());

        $this->assertSame(Assinatura::STATUS_ATIVA, $assinatura->refresh()->status);
    }

    public function test_fechar_ciclo_rebaixa_quem_nao_pagou_o_anterior(): void
    {
        $assinatura = $this->servico()->contratar($this->usuario, $this->plano);

        // Ciclo corrente (aberto na contratação) fica pendente; a virada do mês
        // seguinte deve rebaixar.
        $proxima = now()->addMonthNoOverflow()->format('Y-m');
        $resultado = $this->servico()->fecharCiclo($proxima);

        $this->assertSame(1, $resultado['rebaixadas']);
        $this->assertSame(Assinatura::STATUS_INADIMPLENTE, $assinatura->refresh()->status);
        $this->assertNotNull($assinatura->cicloDaCompetencia($proxima));
    }

    public function test_cupom_gratuito_e_unico_por_usuario_e_torneio(): void
    {
        $torneio = Torneio::query()->create([
            'nome' => 'Brasileirão Teste',
            'edicao' => '2026',
            'status' => 'publicado',
            'valor_cupom' => 10,
        ]);

        $primeiro = $this->servico()->garantirCupomGratuito($this->usuario, $torneio);
        $segundo = $this->servico()->garantirCupomGratuito($this->usuario, $torneio);

        $this->assertSame($primeiro->id, $segundo->id);
        $this->assertSame('gratuito', $primeiro->origem);
        $this->assertSame('ativo', $primeiro->status);
        $this->assertSame(1, Cupom::query()->where('usuario_id', $this->usuario->id)->count());
    }

    public function test_cadastro_cria_cupom_gratuito_nos_torneios_publicados(): void
    {
        Torneio::query()->create([
            'nome' => 'Brasileirão Teste',
            'edicao' => '2026',
            'status' => 'publicado',
            'valor_cupom' => 10,
        ]);

        $resposta = $this->postJson('/api/cadastro', [
            'nome' => 'Novata',
            'email' => 'novata@cravae.local',
            'telefone' => '71999990000',
            'cpf_cnpj' => '52998224725',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ]);

        $resposta->assertCreated();

        $novata = Usuario::query()->where('email', 'novata@cravae.local')->firstOrFail();
        $this->assertSame(1, Cupom::query()->where('usuario_id', $novata->id)->where('origem', 'gratuito')->count());
    }

    public function test_api_contrata_cancela_e_confirma_ciclo_sandbox(): void
    {
        $resposta = $this->actingAs($this->usuario)->postJson('/api/assinaturas', [
            'plano_id' => $this->plano->id,
        ]);

        $resposta->assertCreated()->assertJsonPath('assinatura.fundador', true);

        $confirmacao = $this->actingAs($this->usuario)->postJson('/api/assinatura/confirmar-ciclo-sandbox');
        $confirmacao->assertOk()->assertJsonPath('ciclo.status', CicloAssinatura::STATUS_PAGO);

        $minha = $this->actingAs($this->usuario)->getJson('/api/assinatura');
        $minha->assertOk()->assertJsonPath('elegivel_no_mes', true);

        $cancelamento = $this->actingAs($this->usuario)->postJson('/api/assinatura/cancelar');
        $cancelamento->assertOk()->assertJsonPath('assinatura.status', Assinatura::STATUS_CANCELADA);
    }

    public function test_compra_de_cupom_responde_410(): void
    {
        $torneio = Torneio::query()->create([
            'nome' => 'Brasileirão Teste',
            'edicao' => '2026',
            'status' => 'publicado',
            'valor_cupom' => 10,
        ]);

        $this->actingAs($this->usuario)
            ->postJson('/api/pedidos-checkout', ['torneio_id' => $torneio->id])
            ->assertStatus(410);
    }
}
