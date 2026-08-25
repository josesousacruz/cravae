<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->unsignedInteger('preco_centavos');
            $table->unsignedInteger('preco_fundador_centavos')->nullable();
            $table->unsignedInteger('vagas_fundador')->default(0);
            $table->unsignedInteger('max_cupons')->default(1);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->foreignId('plano_id')->constrained('planos');
            $table->string('status')->default('ativa'); // ativa | inadimplente | cancelada
            $table->boolean('fundador')->default(false);
            $table->unsignedInteger('preco_centavos_contratado');
            $table->string('id_assinatura_asaas')->nullable()->index();
            $table->timestamp('assinada_em');
            $table->timestamp('cancelada_em')->nullable();
            $table->timestamps();

            // Uma assinatura não-cancelada por usuário é garantida na aplicação
            // (ServicoAssinatura); índice para as consultas de elegibilidade.
            $table->index(['usuario_id', 'status']);
        });

        Schema::create('ciclos_assinatura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assinatura_id')->constrained('assinaturas')->cascadeOnDelete();
            $table->string('competencia', 7); // YYYY-MM
            $table->string('status')->default('pendente'); // pendente | pago | falhou
            $table->timestamp('pago_em')->nullable();
            $table->string('id_cobranca_asaas')->nullable()->index();
            $table->timestamps();

            $table->unique(['assinatura_id', 'competencia']);
        });

        Schema::table('cupons', function (Blueprint $table) {
            $table->string('origem')->default('assinatura')->after('status'); // gratuito | assinatura
        });
    }

    public function down(): void
    {
        Schema::table('cupons', function (Blueprint $table) {
            $table->dropColumn('origem');
        });
        Schema::dropIfExists('ciclos_assinatura');
        Schema::dropIfExists('assinaturas');
        Schema::dropIfExists('planos');
    }
};
