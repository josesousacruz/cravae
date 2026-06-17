<?php

namespace Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.asaas.base_url' => 'https://api-sandbox.asaas.com',
            'services.asaas.access_token' => 'test-token',
            'services.asaas.webhook_token' => 'test-webhook-token-with-more-than-32-chars',
        ]);

        Http::fake(function (Request $request) {
            $url = (string) $request->url();

            if (str_ends_with($url, '/v3/customers')) {
                return Http::response(['id' => 'cus_test'], 200);
            }

            if (str_ends_with($url, '/v3/payments')) {
                $dados = $request->data();
                $referencia = (string) ($dados['externalReference'] ?? uniqid('pedido_', true));

                return Http::response([
                    'id' => 'pay_'.substr(md5($referencia), 0, 16),
                    'status' => 'PENDING',
                    'invoiceUrl' => 'https://sandbox.asaas.com/i/test',
                    'externalReference' => $referencia,
                ], 200);
            }

            if (str_contains($url, '/pixQrCode')) {
                return Http::response([
                    'encodedImage' => 'base64-qr-code',
                    'payload' => 'pix-copia-e-cola',
                    'expirationDate' => now()->addDay()->format('Y-m-d H:i:s'),
                ], 200);
            }

            if (str_contains($url, '/v3/sandbox/payment/') && str_ends_with($url, '/confirm')) {
                return Http::response([
                    'id' => basename(dirname($url)),
                    'status' => 'RECEIVED',
                ], 200);
            }

            return Http::response([], 404);
        });
    }

    /**
     * Em producao a compra de cupons fica fechada por padrao. Os testes exercitam o
     * fluxo de compra, entao abrimos a compra do torneio publicado apos cada seed.
     */
    public function seed($class = \Database\Seeders\DatabaseSeeder::class): void
    {
        parent::seed($class);

        \App\Models\Torneio::query()
            ->where('status', 'publicado')
            ->update(['compras_abertas' => true]);
    }
}
