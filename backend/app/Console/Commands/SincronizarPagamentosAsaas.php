<?php

namespace App\Console\Commands;

use App\Models\PedidoCheckout;
use App\Services\ServicoCheckout;
use Illuminate\Console\Command;
use Throwable;

class SincronizarPagamentosAsaas extends Command
{
    protected $signature = 'asaas:sincronizar-pagamentos {--limite=100 : Quantidade maxima de pedidos consultados}';

    protected $description = 'Sincroniza no Asaas os pagamentos Pix pendentes dos pedidos de checkout.';

    public function handle(ServicoCheckout $servicoCheckout): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $resumo = [
            'consultados' => 0,
            'pagos' => 0,
            'expirados' => 0,
            'estornados' => 0,
            'cancelados' => 0,
            'pendentes' => 0,
            'erros' => 0,
        ];

        PedidoCheckout::query()
            ->where('status', 'pendente')
            ->whereNotNull('asaas_pagamento_id')
            ->oldest('id')
            ->limit($limite)
            ->get()
            ->each(function (PedidoCheckout $pedido) use ($servicoCheckout, &$resumo): void {
                $resumo['consultados']++;

                try {
                    $pedidoSincronizado = $servicoCheckout->sincronizarPagamentoAsaas($pedido);
                } catch (Throwable $exception) {
                    $resumo['erros']++;
                    $this->warn("Pedido {$pedido->id}: {$exception->getMessage()}");

                    return;
                }

                match ($pedidoSincronizado->status) {
                    'pago' => $resumo['pagos']++,
                    'expirado' => $resumo['expirados']++,
                    'estornado' => $resumo['estornados']++,
                    'cancelado' => $resumo['cancelados']++,
                    default => $resumo['pendentes']++,
                };
            });

        $this->info(sprintf(
            'Consultados: %d | Pagos: %d | Expirados: %d | Estornados: %d | Cancelados: %d | Pendentes: %d | Erros: %d',
            $resumo['consultados'],
            $resumo['pagos'],
            $resumo['expirados'],
            $resumo['estornados'],
            $resumo['cancelados'],
            $resumo['pendentes'],
            $resumo['erros'],
        ));

        return self::SUCCESS;
    }
}
