<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            // Fase 09 (Palpite Primeiro): o torneio principal é o exibido na home
            // e o alvo do cupom gratuito automático do /meu-cupom.
            $table->boolean('principal')->default(false)->after('compras_abertas');
        });

        // Marca o Brasileirão Série A publicado como principal (o de menor id,
        // caso mais de um torneio case com o nome).
        $id = DB::table('torneios')
            ->where('nome', 'like', '%Brasileirão%')
            ->where('status', 'publicado')
            ->orderBy('id')
            ->value('id');

        if ($id !== null) {
            DB::table('torneios')->where('id', $id)->update(['principal' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->dropColumn('principal');
        });
    }
};
