<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (! Schema::hasColumn('usuarios', 'cpf_cnpj')) {
                $table->string('cpf_cnpj', 20)->nullable()->after('telefone');
            }

            if (! Schema::hasColumn('usuarios', 'asaas_cliente_id')) {
                $table->string('asaas_cliente_id', 50)->nullable()->after('cpf_cnpj')->index();
            }
        });

        Schema::table('pedidos_checkout', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos_checkout', 'forma_pagamento')) {
                $table->string('forma_pagamento', 20)->default('pix')->after('status');
            }

            if (! Schema::hasColumn('pedidos_checkout', 'asaas_pagamento_id')) {
                $table->string('asaas_pagamento_id', 50)->nullable()->after('referencia_checkout')->unique();
            }

            if (! Schema::hasColumn('pedidos_checkout', 'asaas_status')) {
                $table->string('asaas_status', 50)->nullable()->after('asaas_pagamento_id');
            }

            if (! Schema::hasColumn('pedidos_checkout', 'invoice_url')) {
                $table->string('invoice_url')->nullable()->after('asaas_status');
            }

            if (! Schema::hasColumn('pedidos_checkout', 'pix_copia_cola')) {
                $table->text('pix_copia_cola')->nullable()->after('invoice_url');
            }

            if (! Schema::hasColumn('pedidos_checkout', 'pix_qr_code_base64')) {
                $table->longText('pix_qr_code_base64')->nullable()->after('pix_copia_cola');
            }

            if (! Schema::hasColumn('pedidos_checkout', 'pix_expira_at')) {
                $table->timestamp('pix_expira_at')->nullable()->after('pix_qr_code_base64');
            }

            if (! Schema::hasColumn('pedidos_checkout', 'erro_pagamento')) {
                $table->text('erro_pagamento')->nullable()->after('pix_expira_at');
            }
        });

        if (! Schema::hasTable('eventos_webhook_asaas')) {
            Schema::create('eventos_webhook_asaas', function (Blueprint $table) {
                $table->id();
                $table->string('asaas_evento_id', 100)->unique();
                $table->string('evento', 100);
                $table->string('asaas_pagamento_id', 50)->nullable()->index();
                $table->json('payload');
                $table->string('status', 30)->default('pendente')->index();
                $table->timestamp('processado_at')->nullable();
                $table->timestamps();
            });
        }

        $this->regularizarCuponsExistentes();
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_webhook_asaas');

        Schema::table('pedidos_checkout', function (Blueprint $table) {
            foreach ([
                'erro_pagamento',
                'pix_expira_at',
                'pix_qr_code_base64',
                'pix_copia_cola',
                'invoice_url',
                'asaas_status',
                'asaas_pagamento_id',
                'forma_pagamento',
            ] as $column) {
                if (Schema::hasColumn('pedidos_checkout', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('usuarios', function (Blueprint $table) {
            foreach (['asaas_cliente_id', 'cpf_cnpj'] as $column) {
                if (Schema::hasColumn('usuarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function regularizarCuponsExistentes(): void
    {
        $valorCupom = DB::table('torneios')
            ->where('status', 'publicado')
            ->latest('id')
            ->value('valor_cupom') ?? 10.00;

        DB::table('cupons')
            ->whereNull('pedido_checkout_id')
            ->orderBy('id')
            ->each(function (object $cupom) use ($valorCupom) {
                $pedidoId = DB::table('pedidos_checkout')->insertGetId([
                    'usuario_id' => $cupom->usuario_id,
                    'valor' => $valorCupom,
                    'status' => 'pendente',
                    'forma_pagamento' => 'pix',
                    'referencia_checkout' => 'cupom-retroativo:'.$cupom->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('cupons')
                    ->where('id', $cupom->id)
                    ->update([
                        'pedido_checkout_id' => $pedidoId,
                        'status' => 'aguardando_pagamento',
                        'updated_at' => now(),
                    ]);
            });

        DB::table('pedidos_checkout')
            ->join('cupons', 'cupons.pedido_checkout_id', '=', 'pedidos_checkout.id')
            ->where('pedidos_checkout.status', 'pago')
            ->update([
                'pedidos_checkout.status' => 'pendente',
                'pedidos_checkout.forma_pagamento' => 'pix',
                'pedidos_checkout.pago_at' => null,
                'pedidos_checkout.updated_at' => now(),
                'cupons.status' => 'aguardando_pagamento',
                'cupons.updated_at' => now(),
            ]);
    }
};
