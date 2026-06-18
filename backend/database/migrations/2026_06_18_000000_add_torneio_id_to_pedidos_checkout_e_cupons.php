<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_checkout', function (Blueprint $table) {
            $table->foreignId('torneio_id')->nullable()->after('usuario_id')
                ->constrained('torneios')->cascadeOnDelete();
        });

        Schema::table('cupons', function (Blueprint $table) {
            $table->foreignId('torneio_id')->nullable()->after('usuario_id')
                ->constrained('torneios')->cascadeOnDelete();
        });

        $torneioId = DB::table('torneios')->where('status', 'publicado')->orderByDesc('id')->value('id');

        if ($torneioId !== null) {
            DB::table('pedidos_checkout')->whereNull('torneio_id')->update(['torneio_id' => $torneioId]);
            DB::table('cupons')->whereNull('torneio_id')->update(['torneio_id' => $torneioId]);
        }
    }

    public function down(): void
    {
        Schema::table('cupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('torneio_id');
        });
        Schema::table('pedidos_checkout', function (Blueprint $table) {
            $table->dropConstrainedForeignId('torneio_id');
        });
    }
};
