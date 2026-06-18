<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A coluna `jogos.data_hora_inicio` foi criada como `timestamp` simples; no MySQL
     * isso vira `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`, fazendo a data
     * do jogo ser RESETADA para "agora" a cada update da linha (ex.: admin lancando
     * resultado / marcando status). Removemos o comportamento automatico: a data passa
     * a ser definida apenas explicitamente pela aplicacao.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE jogos MODIFY data_hora_inicio TIMESTAMP NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jogos MODIFY data_hora_inicio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }
};
