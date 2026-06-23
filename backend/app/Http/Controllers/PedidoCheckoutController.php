<?php

namespace App\Http\Controllers;

use App\Exceptions\ExcecaoAsaas;
use App\Http\Requests\CriarPedidoCheckoutRequest;
use App\Models\Cupom;
use App\Models\PedidoCheckout;
use App\Models\Torneio;
use App\Services\ServicoAsaas;
use App\Services\ServicoCheckout;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PedidoCheckoutController extends Controller
{
    public function __construct(
        private readonly ServicoCheckout $servicoCheckout,
        private readonly ServicoAsaas $servicoAsaas,
    ) {}

    public function store(CriarPedidoCheckoutRequest $request): JsonResponse
    {
        $torneio = Torneio::query()->findOrFail($request->integer('torneio_id'));
        $this->garantirComprasAbertas($torneio);

        if ($request->input('forma_pagamento') === 'pix_direto') {
            $cupomDireto = $this->servicoCheckout->criarPedidoPixDireto($request->user(), $torneio);

            return response()->json([
                'pedido' => $cupomDireto->pedidoCheckout()->first()?->loadMissing('cupons'),
                'cupom' => $cupomDireto,
            ], 201);
        }

        $cupom = $request->filled('cupom_id')
            ? Cupom::query()->findOrFail($request->integer('cupom_id'))
            : null;

        abort_if($cupom && $cupom->usuario_id !== $request->user()->id, 403);

        try {
            $pedido = $this->servicoCheckout->criarPedido($request->user(), $torneio, $cupom);
        } catch (ExcecaoAsaas $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'codigo' => $exception->codigoAsaas(),
            ], $exception->statusCode());
        } catch (RequestException) {
            return response()->json([
                'message' => 'Nao foi possivel conectar ao Asaas. Tente novamente em instantes.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json([
            'pedido' => $pedido->loadMissing('cupons'),
        ], 201);
    }

    public function show(Request $request, PedidoCheckout $pedidoCheckout): JsonResponse
    {
        abort_unless($pedidoCheckout->usuario_id === $request->user()->id, 403);

        $sincronizacaoErro = null;

        if ($request->boolean('sincronizar')) {
            try {
                $pedidoCheckout = $this->servicoCheckout->sincronizarPagamentoAsaas($pedidoCheckout);
            } catch (ExcecaoAsaas|RequestException|RuntimeException $exception) {
                $sincronizacaoErro = $exception instanceof ExcecaoAsaas
                    ? $exception->getMessage()
                    : 'Nao foi possivel consultar o pagamento no Asaas agora.';
                $pedidoCheckout = $pedidoCheckout->fresh('cupons');
            }
        }

        return response()->json([
            'pedido' => $pedidoCheckout->load('cupons'),
            'cupom' => $pedidoCheckout->cupons->first(),
            'sincronizacao_erro' => $sincronizacaoErro,
        ]);
    }

    public function pagamentoCupom(Request $request, Cupom $cupom): JsonResponse
    {
        abort_unless($cupom->usuario_id === $request->user()->id, 403);

        $torneio = $cupom->torneio()->firstOrFail();
        $this->garantirComprasAbertas($torneio);

        try {
            $pedido = $this->servicoCheckout->criarPedido($request->user(), $torneio, $cupom);
        } catch (ExcecaoAsaas $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'codigo' => $exception->codigoAsaas(),
            ], $exception->statusCode());
        } catch (RequestException) {
            return response()->json([
                'message' => 'Nao foi possivel conectar ao Asaas. Tente novamente em instantes.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json([
            'pedido' => $pedido->loadMissing('cupons'),
            'cupom' => $cupom->fresh('pedidoCheckout'),
        ]);
    }

    private function garantirComprasAbertas(Torneio $torneio): void
    {
        abort_unless(
            $torneio->status === 'publicado' && (bool) $torneio->compras_abertas,
            403,
            'A compra de cupons esta encerrada.'
        );
    }

    public function confirmarSandbox(Request $request, PedidoCheckout $pedidoCheckout): JsonResponse
    {
        abort_unless($pedidoCheckout->usuario_id === $request->user()->id, 403);
        abort_unless((bool) $pedidoCheckout->asaas_pagamento_id, 422, 'Pedido ainda nao possui cobranca Asaas.');

        try {
            $pagamento = $this->servicoAsaas->confirmarPagamentoSandbox($pedidoCheckout->asaas_pagamento_id);
            $cupom = $this->servicoCheckout->marcarComoPago($pedidoCheckout, $pagamento['status'] ?? 'RECEIVED');
        } catch (ExcecaoAsaas $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'codigo' => $exception->codigoAsaas(),
            ], $exception->statusCode());
        } catch (RequestException) {
            return response()->json([
                'message' => 'Nao foi possivel confirmar o pagamento no sandbox Asaas.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'pedido' => $pedidoCheckout->fresh('cupons'),
            'cupom' => $cupom,
        ]);
    }

}
