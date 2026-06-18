<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MultiBolaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cupons_e_pedidos_tem_coluna_torneio_id(): void
    {
        $this->assertTrue(Schema::hasColumn('cupons', 'torneio_id'));
        $this->assertTrue(Schema::hasColumn('pedidos_checkout', 'torneio_id'));
    }
}
