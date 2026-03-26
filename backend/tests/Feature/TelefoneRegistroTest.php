<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelefoneRegistroTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastro_com_telefone_retorna_201_com_telefone(): void
    {
        $this->seed();

        $response = $this->postJson('/api/cadastro', [
            'nome' => 'Teste Usuario',
            'email' => 'teste@example.com',
            'telefone' => '11999998888',
            'password' => 'senhaforte123',
            'password_confirmation' => 'senhaforte123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('usuario.telefone', '11999998888');
    }

    public function test_cadastro_sem_telefone_retorna_422(): void
    {
        $this->seed();

        $response = $this->postJson('/api/cadastro', [
            'nome' => 'Teste Usuario',
            'email' => 'teste@example.com',
            'password' => 'senhaforte123',
            'password_confirmation' => 'senhaforte123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telefone']);
    }

    public function test_usuario_autenticado_retorna_telefone(): void
    {
        $this->seed();

        $usuario = Usuario::factory()->create([
            'nome' => 'Com Telefone',
            'email' => 'comtel@example.com',
            'telefone' => '21888887777',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/usuario');

        $response
            ->assertOk()
            ->assertJsonPath('usuario.telefone', '21888887777');
    }
}
