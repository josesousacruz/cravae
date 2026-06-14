<?php

namespace Tests\Feature;

use App\Models\Cupom;
use App\Models\PedidoCheckout;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPagamentoCupomTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lista_pendentes_e_marca_cupom_como_pago(): void
    {
        $this->seed();

        $admin = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        [$usuario, $cupom, $pedido] = $this->criarCupomPendente('pendente@teste.local');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/cupons-pendentes')
            ->assertOk()
            ->assertJsonPath('cupons.0.id', $cupom->id)
            ->assertJsonPath('cupons.0.usuario.nome', $usuario->nome)
            ->assertJsonPath('cupons.0.usuario.telefone', $usuario->telefone);

        $this->postJson("/api/admin/cupons/{$cupom->id}/marcar-pago")
            ->assertOk()
            ->assertJsonPath('cupom.status', 'ativo');

        $this->assertDatabaseHas('cupons', ['id' => $cupom->id, 'status' => 'ativo']);
        $this->assertDatabaseHas('pedidos_checkout', ['id' => $pedido->id, 'status' => 'pago']);

        // Apos pago nao aparece mais na lista de pendentes.
        $this->getJson('/api/admin/cupons-pendentes')
            ->assertOk()
            ->assertJsonCount(0, 'cupons');
    }

    public function test_busca_filtra_por_email_do_usuario(): void
    {
        $this->seed();

        $admin = Usuario::query()->where('perfil', 'administrador')->firstOrFail();
        $this->criarCupomPendente('alvo@teste.local');
        $this->criarCupomPendente('outro@teste.local');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/cupons-pendentes?busca=alvo@teste.local')
            ->assertOk()
            ->assertJsonCount(1, 'cupons')
            ->assertJsonPath('cupons.0.usuario.email', 'alvo@teste.local');
    }

    public function test_usuario_comum_nao_acessa_rotas_de_pagamento(): void
    {
        $this->seed();

        [, $cupom] = $this->criarCupomPendente('comum@teste.local');
        $intruso = Usuario::factory()->create([
            'email' => 'intruso-admin@teste.local',
            'telefone' => '11999990009',
            'perfil' => 'usuario',
        ]);

        Sanctum::actingAs($intruso);

        $this->getJson('/api/admin/cupons-pendentes')->assertForbidden();
        $this->postJson("/api/admin/cupons/{$cupom->id}/marcar-pago")->assertForbidden();

        $this->assertDatabaseHas('cupons', ['id' => $cupom->id, 'status' => 'aguardando_pagamento']);
    }

    /**
     * @return array{0: Usuario, 1: Cupom, 2: PedidoCheckout}
     */
    private function criarCupomPendente(string $email): array
    {
        $usuario = Usuario::query()->create([
            'nome' => 'Usuario '.strtok($email, '@'),
            'email' => $email,
            'telefone' => '71988887777',
            'cpf_cnpj' => '12345678901',
            'password' => '12345678',
            'perfil' => 'usuario',
        ]);

        $pedido = PedidoCheckout::query()->create([
            'usuario_id' => $usuario->id,
            'valor' => 10.00,
            'status' => 'pendente',
            'forma_pagamento' => 'pix',
            'referencia_checkout' => 'teste-'.$usuario->id,
        ]);

        $cupom = Cupom::query()->create([
            'usuario_id' => $usuario->id,
            'pedido_checkout_id' => $pedido->id,
            'codigo' => strtoupper('TST'.$usuario->id),
            'status' => 'aguardando_pagamento',
        ]);

        return [$usuario, $cupom, $pedido];
    }
}
