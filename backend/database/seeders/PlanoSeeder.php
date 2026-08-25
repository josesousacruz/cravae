<?php

namespace Database\Seeders;

use App\Models\Plano;
use Illuminate\Database\Seeder;

class PlanoSeeder extends Seeder
{
    /**
     * Plano único do lançamento (decisões do grilling 2026-08-25):
     * R$ 9,90/mês; preço de fundador R$ 4,95 para os 100 primeiros, vitalício
     * enquanto a assinatura estiver ativa. Valores ajustáveis em banco.
     */
    public function run(): void
    {
        Plano::query()->updateOrCreate(
            ['slug' => 'assinante'],
            [
                'nome' => 'Assinante',
                'preco_centavos' => 990,
                'preco_fundador_centavos' => 495,
                'vagas_fundador' => 100,
                'max_cupons' => 1,
                'ativo' => true,
            ],
        );
    }
}
