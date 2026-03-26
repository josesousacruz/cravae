<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultados_torneio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('torneio_id')->unique()->constrained('torneios')->cascadeOnDelete();
            $table->foreignId('campeao_selecao_id')->nullable()->constrained('selecoes')->nullOnDelete();
            $table->foreignId('vice_campeao_selecao_id')->nullable()->constrained('selecoes')->nullOnDelete();
            $table->foreignId('terceiro_colocado_selecao_id')->nullable()->constrained('selecoes')->nullOnDelete();
            $table->foreignId('artilheiro_jogador_id')->nullable()->constrained('jogadores')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados_torneio');
    }
};
