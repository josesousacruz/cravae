<?php

namespace App\Services;

use App\Exceptions\ExcecaoAsaas;
use App\Models\PedidoCheckout;
use App\Models\Usuario;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ServicoAsaas
{
    public function criarCliente(Usuario $usuario): array
    {
        return $this->validarResposta($this->clienteHttp()->post('/v3/customers', [
            'name' => $usuario->nome,
            'cpfCnpj' => $this->somenteDigitos((string) $usuario->cpf_cnpj),
            'email' => $usuario->email,
            'mobilePhone' => $this->somenteDigitos((string) $usuario->telefone),
            'externalReference' => 'usuario:'.$usuario->id,
            'notificationDisabled' => true,
        ]));
    }

    public function criarCobrancaPix(PedidoCheckout $pedido): array
    {
        return $this->validarResposta($this->clienteHttp()->post('/v3/payments', [
            'customer' => $pedido->usuario->asaas_cliente_id,
            'billingType' => 'PIX',
            'value' => (float) $pedido->valor,
            'dueDate' => now()->addDay()->toDateString(),
            'description' => 'Cupom Inter World Cup 2026 - Pedido '.$pedido->id,
            'externalReference' => 'pedido_checkout:'.$pedido->id,
        ]));
    }

    public function obterQrCodePix(string $asaasPagamentoId): array
    {
        return $this->validarResposta($this->clienteHttp()
            ->withBody('')
            ->get("/v3/payments/{$asaasPagamentoId}/pixQrCode"));
    }

    public function consultarPagamento(string $asaasPagamentoId): array
    {
        return $this->validarResposta($this->clienteHttp()
            ->withBody('')
            ->get("/v3/payments/{$asaasPagamentoId}"));
    }

    public function confirmarPagamentoSandbox(string $asaasPagamentoId): array
    {
        $baseUrl = (string) config('services.asaas.base_url');

        if (! str_contains($baseUrl, 'api-sandbox.asaas.com')) {
            throw new RuntimeException('Confirmacao manual disponivel apenas no sandbox Asaas.');
        }

        return $this->validarResposta($this->clienteHttp()
            ->post("/v3/sandbox/payment/{$asaasPagamentoId}/confirm", []));
    }

    private function clienteHttp(): PendingRequest
    {
        $accessToken = (string) config('services.asaas.access_token');

        if ($accessToken === '') {
            throw new RuntimeException('Integracao Asaas nao configurada.');
        }

        return Http::baseUrl(rtrim((string) config('services.asaas.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->withHeaders([
                'access_token' => $accessToken,
                'User-Agent' => (string) config('services.asaas.user_agent'),
            ]);
    }

    private function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }

    private function validarResposta(Response $resposta): array
    {
        if ($resposta->successful()) {
            return $resposta->json();
        }

        $erros = $resposta->json('errors') ?? [];
        $erro = is_array($erros) ? ($erros[0] ?? []) : [];
        $codigo = is_array($erro) ? ($erro['code'] ?? null) : null;
        $descricao = is_array($erro) ? ($erro['description'] ?? null) : null;

        throw new ExcecaoAsaas(
            $descricao ? 'Asaas: '.$descricao : 'Asaas recusou a solicitacao de pagamento.',
            $resposta->status(),
            $codigo,
        );
    }
}
