<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('selecoes', function (Blueprint $table) {
            // idTeam da TheSportsDB. Nulo enquanto a seleção não estiver
            // definida (placeholders de repescagem/intercontinental).
            $table->unsignedBigInteger('id_externo')->nullable()->after('sigla');

            // Uma seleção por idTeam dentro do mesmo torneio. Como é nullable,
            // o MySQL permite múltiplos NULL (os placeholders convivem).
            $table->unique(['torneio_id', 'id_externo']);
        });
    }

    public function down(): void
    {
        Schema::table('selecoes', function (Blueprint $table) {
            $table->dropUnique(['torneio_id', 'id_externo']);
            $table->dropColumn('id_externo');
        });
    }
};
