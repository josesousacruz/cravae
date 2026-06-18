<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Os palpites de grupos passaram a fechar por dia (1h antes do primeiro jogo do dia).
 * O data_fechamento por rodada virou apenas um override opcional, entao zeramos os
 * valores existentes (que fechavam a rodada inteira no dia de inicio) para a regra
 * por-dia valer em producao.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rodadas')->update(['data_fechamento' => null]);
    }

    public function down(): void
    {
        // Sem rollback: os valores antigos (fechamento por rodada) eram justamente o bug.
    }
};
