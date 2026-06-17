<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->boolean('compras_abertas')->default(false)->after('valor_cupom');
        });
    }

    public function down(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->dropColumn('compras_abertas');
        });
    }
};
