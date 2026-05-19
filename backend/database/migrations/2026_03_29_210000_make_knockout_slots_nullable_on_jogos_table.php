<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->foreignId('selecao_mandante_id')->nullable()->change();
            $table->foreignId('selecao_visitante_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->foreignId('selecao_mandante_id')->nullable(false)->change();
            $table->foreignId('selecao_visitante_id')->nullable(false)->change();
        });
    }
};
