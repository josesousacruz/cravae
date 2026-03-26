<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->decimal('valor_cupom', 10, 2)->default(10.00)->after('data_fim');
        });
    }

    public function down(): void
    {
        Schema::table('torneios', function (Blueprint $table) {
            $table->dropColumn('valor_cupom');
        });
    }
};
