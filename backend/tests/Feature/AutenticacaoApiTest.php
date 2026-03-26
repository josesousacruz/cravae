<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutenticacaoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_administrador_consegue_entrar_e_receber_token(): void
    {
        $this->seed();

        $response = $this->postJson('/api/entrar', [
            'email' => 'admin@interworldcup.local',
            'password' => '12345678',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'usuario' => ['id', 'nome', 'email', 'perfil'],
            ]);
    }

    public function test_administrador_consegue_acessar_resumo_do_painel(): void
    {
        $this->seed();

        $administrador = Usuario::query()->where('email', 'admin@interworldcup.local')->firstOrFail();

        Sanctum::actingAs($administrador);

        $response = $this->getJson('/api/admin/resumo');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'metricas' => [
                    'usuarios',
                    'torneios',
                    'grupos',
                    'selecoes',
                    'fases',
                    'jogos',
                    'regras_pontuacao',
                ],
            ]);
    }
}
