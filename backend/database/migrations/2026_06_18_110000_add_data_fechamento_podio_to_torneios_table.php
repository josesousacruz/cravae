<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            // Override opcional do fechamento do palpite de podio (campeao/vice/3o).
            // NULL = automatico (1h antes do 1o jogo do mata-mata).
            $table->dateTime('data_fechamento_podio')->nullable()->after('data_fim');
        });
    }

    public function down(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->dropColumn('data_fechamento_podio');
        });
    }
};
