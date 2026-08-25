<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('selecoes', function (Blueprint $table) {
            // Escudo do clube (strBadge da TheSportsDB). Para a Copa as bandeiras
            // eram resolvidas no frontend pela sigla; clubes usam imagem da API.
            $table->string('escudo_url')->nullable()->after('slug');
        });

        Schema::table('jogadores', function (Blueprint $table) {
            // idPlayer da TheSportsDB: chave do casamento com a timeline de gols.
            $table->unsignedBigInteger('id_externo')->nullable()->after('selecao_id');
            // Foto cutout (strCutout/strThumb) exibida no seletor de goleadores.
            $table->string('foto_url')->nullable()->after('numero_camisa');
            $table->index('id_externo');
        });

        Schema::table('resultados_jogos', function (Blueprint $table) {
            // Autores reais dos gols, um item por gol do placar, na ordem cronologica.
            // Elemento: {"jogador_id": int|null, "contra": bool} — contra=true e um
            // gol contra (jogador_id, se presente, e de quem marcou contra).
            $table->json('goleadores_mandante')->nullable()->after('placar_visitante');
            $table->json('goleadores_visitante')->nullable()->after('goleadores_mandante');
        });
    }

    public function down(): void
    {
        Schema::table('resultados_jogos', function (Blueprint $table) {
            $table->dropColumn(['goleadores_mandante', 'goleadores_visitante']);
        });

        Schema::table('jogadores', function (Blueprint $table) {
            $table->dropIndex(['id_externo']);
            $table->dropColumn(['id_externo', 'foto_url']);
        });

        Schema::table('selecoes', function (Blueprint $table) {
            $table->dropColumn('escudo_url');
        });
    }
};
