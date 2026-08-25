<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 09 (Palpite Primeiro): torneio principal na home e cupom gratuito
 * automático via /meu-cupom.
 */
class CravaePosLoginTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        // Testa o domínio sem tocar a API do Asaas (modo manual/sandbox local).
        config(['services.asaas.access_token' => '']);

        $this->usuario = Usuario::query()->create([
            'nome' => 'Palpiteiro',
            'email' => 'palpiteiro@cravae.local',
            'password' => Hash::make('segredo123'),
            'perfil' => 'usuario',
        ]);
    }

    private function criarTorneio(array $atributos = []): Torneio
    {
        return Torneio::query()->create(array_merge([
            'nome' => 'Brasileirão Teste',
            'edicao' => '2026',
            'status' => 'publicado',
            'valor_cupom' => 10,
        ], $atributos));
    }

    public function test_torneio_publico_retorna_o_principal_mesmo_com_outro_publicado_de_id_menor(): void
    {
        $antigo = $this->criarTorneio(['nome' => 'Copa Antiga']);
        $principal = $this->criarTorneio(['nome' => 'Brasileirão Principal', 'principal' => true]);

        $this->assertLessThan($principal->id, $antigo->id);

        $resposta = $this->getJson('/api/torneio');

        $resposta->assertOk()->assertJsonPath('torneio.id', $principal->id);
    }

    public function test_meu_cupom_cria_o_gratuito_na_primeira_chamada_e_reutiliza_na_segunda(): void
    {
        $torneio = $this->criarTorneio(['principal' => true]);

        $primeira = $this->actingAs($this->usuario)->getJson('/api/meu-cupom');

        $primeira->assertOk()
            ->assertJsonPath('cupom.torneio_id', $torneio->id)
            ->assertJsonPath('cupom.origem', 'gratuito');

        $cupomId = $primeira->json('cupom.id');
        $this->assertNotNull($cupomId);
        $this->assertSame(1, Cupom::query()->where('usuario_id', $this->usuario->id)->count());

        $segunda = $this->actingAs($this->usuario)->getJson('/api/meu-cupom');

        $segunda->assertOk()->assertJsonPath('cupom.id', $cupomId);
        $this->assertSame(1, Cupom::query()->where('usuario_id', $this->usuario->id)->count());
    }

    public function test_meu_cupom_responde_404_sem_torneio_principal(): void
    {
        // Há torneio publicado, mas nenhum marcado como principal.
        $this->criarTorneio(['nome' => 'Copa Sem Principal']);

        $resposta = $this->actingAs($this->usuario)->getJson('/api/meu-cupom');

        $resposta->assertNotFound()
            ->assertJsonPath('mensagem', 'Nenhum torneio principal publicado no momento.');
    }

    public function test_payload_do_meu_cupom_tem_as_mesmas_chaves_do_show(): void
    {
        $this->criarTorneio(['principal' => true]);

        $meu = $this->actingAs($this->usuario)->getJson('/api/meu-cupom');
        $meu->assertOk();

        $cupomId = $meu->json('cupom.id');

        $show = $this->actingAs($this->usuario)->getJson("/api/cupons/{$cupomId}");
        $show->assertOk();

        $this->assertSame(array_keys($show->json()), array_keys($meu->json()));
        $this->assertSame(array_keys($show->json('cupom')), array_keys($meu->json('cupom')));
    }
}
