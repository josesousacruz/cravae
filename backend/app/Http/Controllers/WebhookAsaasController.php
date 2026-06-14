<?php

namespace App\Http\Controllers;

use App\Models\EventoWebhookAsaas;
use App\Models\PedidoCheckout;
use App\Services\ServicoCheckout;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class WebhookAsaasController extends Controller
{
    public function __construct(
        private readonly ServicoCheckout $servicoCheckout,
    ) {
    }

    public function pagamentos(Request $request): JsonResponse
    {
        $tokenConfigurado = (string) config('services.asaas.webhook_token');

        abort_if(
            $tokenConfigurado === '' || ! hash_equals($tokenConfigurado, (string) $request->header('asaas-access-token')),
            401,
        );

        $payload = $request->all();
        $asaasEventoId = (string) Arr::get($payload, 'id');
        $evento = (string) Arr::get($payload, 'event');
        $asaasPagamentoId = Arr::get($payload, 'payment.id');

        abort_if($asaasEventoId === '' || $evento === '', 422);

        try {
            $registro = EventoWebhookAsaas::query()->create([
                'asaas_evento_id' => $asaasEventoId,
                'evento' => $evento,
                'asaas_pagamento_id' => $asaasPagamentoId,
                'payload' => $payload,
                'status' => 'pendente',
            ]);
        } catch (QueryException $exception) {
            if ($this->erroDuplicado($exception)) {
                return response()->json(['received' => true]);
            }

            throw $exception;
        }

        $this->processarEvento($registro, $payload);

        return response()->json(['received' => true]);
    }

    private function processarEvento(EventoWebhookAsaas $registro, array $payload): void
    {
        $pedido = $this->localizarPedido($payload);

        if (! $pedido) {
            $registro->forceFill([
                'status' => 'ignorado',
                'processado_at' => now(),
            ])->save();

            return;
        }

        $asaasStatus = Arr::get($payload, 'payment.status');

        match ($registro->evento) {
            'PAYMENT_RECEIVED' => $this->servicoCheckout->marcarComoPago($pedido, $asaasStatus),
            'PAYMENT_OVERDUE' => $this->servicoCheckout->atualizarStatusAsaas($pedido, 'expirado', $asaasStatus),
            'PAYMENT_DELETED' => $this->servicoCheckout->atualizarStatusAsaas($pedido, 'cancelado', $asaasStatus),
            'PAYMENT_REFUNDED' => $this->servicoCheckout->atualizarStatusAsaas($pedido, 'estornado', $asaasStatus),
            default => null,
        };

        $registro->forceFill([
            'status' => 'processado',
            'processado_at' => now(),
        ])->save();
    }

    private function localizarPedido(array $payload): ?PedidoCheckout
    {
        $asaasPagamentoId = Arr::get($payload, 'payment.id');

        if ($asaasPagamentoId) {
            $pedido = PedidoCheckout::query()->where('asaas_pagamento_id', $asaasPagamentoId)->first();

            if ($pedido) {
                return $pedido;
            }
        }

        $referenciaExterna = (string) Arr::get($payload, 'payment.externalReference');

        if (str_starts_with($referenciaExterna, 'pedido_checkout:')) {
            return PedidoCheckout::query()->find((int) substr($referenciaExterna, strlen('pedido_checkout:')));
        }

        return null;
    }

    private function erroDuplicado(QueryException $exception): bool
    {
        $codigo = (string) ($exception->errorInfo[0] ?? '');

        return in_array($codigo, ['23000', '23505'], true);
    }
}
