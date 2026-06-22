<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            // idEvent da TheSportsDB. Nulo enquanto o confronto não for casado
            // (ex.: mata-mata sem seleções definidas).
            $table->unsignedBigInteger('id_evento_externo')->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->dropUnique(['id_evento_externo']);
            $table->dropColumn('id_evento_externo');
        });
    }
};
