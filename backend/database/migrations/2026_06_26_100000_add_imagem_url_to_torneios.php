<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            // Imagem do torneio (badge/banner da liga na TheSportsDB), usada nos cards.
            $table->string('imagem_url')->nullable()->after('temporada_externa');
        });
    }

    public function down(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->dropColumn('imagem_url');
        });
    }
};
