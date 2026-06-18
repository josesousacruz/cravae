<?php

namespace Tests\Feature;

use App\Models\Torneio;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFechamentoPodioTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_define_e_limpa_o_prazo_de_fechamento_do_podio(): void
    {
        $this->seed();
        $admin = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        $torneio = Torneio::query()->firstOrFail();

        Sanctum::actingAs($admin);

        // Define o prazo.
        $this->putJson("/api/admin/torneios/{$torneio->id}/fechamento-podio", [
            'data_fechamento_podio' => '2026-07-01T12:00:00Z',
        ])->assertOk();

        $this->assertNotNull($torneio->fresh()->data_fechamento_podio);

        // Limpa o prazo (volta ao automatico).
        $this->putJson("/api/admin/torneios/{$torneio->id}/fechamento-podio", [
            'data_fechamento_podio' => null,
        ])->assertOk();

        $this->assertNull($torneio->fresh()->data_fechamento_podio);
    }

    public function test_usuario_comum_nao_pode_definir_o_fechamento_do_podio(): void
    {
        $this->seed();
        $torneio = Torneio::query()->firstOrFail();

        $usuario = Usuario::query()->create([
            'nome' => 'Comum',
            'email' => 'comum-podio@teste.local',
            'telefone' => '71999999999',
            'cpf_cnpj' => '12345678901',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($usuario);

        $this->putJson("/api/admin/torneios/{$torneio->id}/fechamento-podio", [
            'data_fechamento_podio' => '2026-07-01T12:00:00Z',
        ])->assertForbidden();
    }
}
