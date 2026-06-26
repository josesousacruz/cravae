<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            // Referência do torneio na TheSportsDB (liga + temporada). Permite que
            // cada bolão sincronize a partir do seu próprio torneio externo.
            $table->unsignedBigInteger('liga_externa_id')->nullable()->after('edicao');
            $table->string('temporada_externa')->nullable()->after('liga_externa_id');
        });

        Schema::table('jogos', function (Blueprint $table) {
            // Dois bolões podem apontar para a mesma Copa real, então o mesmo idEvent
            // aparece em jogos de torneios diferentes. O índice único passa a ser
            // composto por torneio.
            $table->dropUnique(['id_evento_externo']);
            $table->unique(['torneio_id', 'id_evento_externo']);
        });
    }

    public function down(): void
    {
        Schema::table('jogos', function (Blueprint $table) {
            $table->dropUnique(['torneio_id', 'id_evento_externo']);
            $table->unique(['id_evento_externo']);
        });

        Schema::table('torneios', function (Blueprint $table) {
            $table->dropColumn(['liga_externa_id', 'temporada_externa']);
        });
    }
};
