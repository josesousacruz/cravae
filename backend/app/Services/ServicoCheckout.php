<?php

namespace App\Services;

use App\Models\Cupom;
use App\Models\PedidoCheckout;
use App\Models\Torneio;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicoCheckout
{
    public function __construct(
        private readonly ServicoAsaas $servicoAsaas,
    ) {}

    public function criarPedido(Usuario $usuario, Torneio $torneio, ?Cupom $cupom = null): PedidoCheckout
    {
        $pedido = DB::transaction(function () use ($usuario, $torneio, $cupom) {
            if ($cupom?->pedido_checkout_id) {
                return $cupom->pedidoCheckout()->lockForUpdate()->firstOrFail();
            }

            $pedido = PedidoCheckout::query()->create([
                'usuario_id' => $usuario->id,
                'torneio_id' => $torneio->id,
                'valor' => $torneio->valor_cupom ?? 10,
                'status' => 'pendente',
                'forma_pagamento' => 'pix',
                'referencia_checkout' => (string) Str::uuid(),
            ]);

            if ($cupom) {
                $cupom->forceFill([
                    'pedido_checkout_id' => $pedido->id,
                    'torneio_id' => $torneio->id,
                    'status' => 'aguardando_pagamento',
                ])->save();
            }

            return $pedido;
        });

        return $this->prepararPagamentoPix($pedido);
    }

    /**
     * Cria pedido + cupom para pagamento via Pix direto (chave fixa, confirmação
     * manual). NÃO toca no Asaas e não exige CPF/CNPJ. Idempotente: reutiliza um
     * cupom pendente de pix_direto do mesmo usuário+torneio, se houver.
     */
    public function criarPedidoPixDireto(Usuario $usuario, Torneio $torneio): Cupom
    {
        return DB::transaction(function () use ($usuario, $torneio) {
            $cupomPendente = Cupom::query()
                ->where('usuario_id', $usuario->id)
                ->where('torneio_id', $torneio->id)
                ->where('status', 'aguardando_pagamento')
                ->whereHas('pedidoCheckout', fn ($q) => $q
                    ->where('forma_pagamento', 'pix_direto')
                    ->where('status', 'pendente'))
                ->latest('id')
                ->first();

            if ($cupomPendente) {
                return $cupomPendente;
            }

            $pedido = PedidoCheckout::query()->create([
                'usuario_id' => $usuario->id,
                'torneio_id' => $torneio->id,
                'valor' => $torneio->valor_cupom ?? 10,
                'status' => 'pendente',
                'forma_pagamento' => 'pix_direto',
                'referencia_checkout' => (string) Str::uuid(),
            ]);

            return Cupom::query()->create([
                'usuario_id' => $usuario->id,
                'torneio_id' => $torneio->id,
                'pedido_checkout_id' => $pedido->id,
                'codigo' => $this->gerarCodigoCupom(),
                'status' => 'aguardando_pagamento',
            ]);
        });
    }

    public function prepararPagamentoPix(PedidoCheckout $pedido): PedidoCheckout
    {
        $pedido->loadMissing('usuario');

        if ($pedido->asaas_pagamento_id && $pedido->pix_copia_cola && $pedido->pix_qr_code_base64) {
            return $pedido;
        }

        if (! $pedido->usuario->cpf_cnpj) {
            throw ValidationException::withMessages([
                'cpf_cnpj' => 'Informe o CPF/CNPJ para gerar o pagamento Pix.',
            ]);
        }

        if (! $pedido->usuario->asaas_cliente_id) {
            $cliente = $this->servicoAsaas->criarCliente($pedido->usuario);

            $pedido->usuario->forceFill([
                'asaas_cliente_id' => $cliente['id'] ?? null,
            ])->save();
        }

        $pedido->refresh()->loadMissing('usuario');

        $cobranca = $this->servicoAsaas->criarCobrancaPix($pedido);
        $qrCode = $this->servicoAsaas->obterQrCodePix($cobranca['id']);

        $pedido->forceFill([
            'status' => 'pendente',
            'forma_pagamento' => 'pix',
            'asaas_pagamento_id' => $cobranca['id'],
            'asaas_status' => $cobranca['status'] ?? null,
            'invoice_url' => $cobranca['invoiceUrl'] ?? null,
            'pix_copia_cola' => $qrCode['payload'] ?? null,
            'pix_qr_code_base64' => $qrCode['encodedImage'] ?? null,
            'pix_expira_at' => isset($qrCode['expirationDate']) ? Carbon::parse($qrCode['expirationDate']) : null,
            'erro_pagamento' => null,
        ])->save();

        return $pedido->fresh('cupons');
    }

    public function marcarComoPago(PedidoCheckout $pedido, ?string $asaasStatus = null): Cupom
    {
        return DB::transaction(function () use ($pedido, $asaasStatus) {
            $pedido = PedidoCheckout::query()->lockForUpdate()->findOrFail($pedido->id);

            $pedido->forceFill([
                'status' => 'pago',
                'asaas_status' => $asaasStatus ?? $pedido->asaas_status,
                'pago_at' => $pedido->pago_at ?? now(),
                'erro_pagamento' => null,
            ])->save();

            $cupom = Cupom::query()->where('pedido_checkout_id', $pedido->id)->lockForUpdate()->first();

            if ($cupom) {
                $cupom->forceFill(['status' => 'ativo'])->save();

                return $cupom;
            }

            return Cupom::query()->create([
                'usuario_id' => $pedido->usuario_id,
                'torneio_id' => $pedido->torneio_id,
                'pedido_checkout_id' => $pedido->id,
                'codigo' => $this->gerarCodigoCupom(),
                'status' => 'ativo',
            ]);
        });
    }

    public function marcarCupomComoPago(Cupom $cupom): Cupom
    {
        return DB::transaction(function () use ($cupom) {
            $cupom = Cupom::query()->lockForUpdate()->findOrFail($cupom->id);
            $cupom->forceFill(['status' => 'ativo'])->save();

            if ($cupom->pedido_checkout_id) {
                $pedido = PedidoCheckout::query()->lockForUpdate()->find($cupom->pedido_checkout_id);
                $pedido?->forceFill([
                    'status' => 'pago',
                    'pago_at' => $pedido->pago_at ?? now(),
                ])->save();
            }

            return $cupom->fresh();
        });
    }

    public function marcarCupomComoNaoPago(Cupom $cupom): Cupom
    {
        return DB::transaction(function () use ($cupom) {
            $cupom = Cupom::query()->lockForUpdate()->findOrFail($cupom->id);
            $cupom->forceFill(['status' => 'aguardando_pagamento'])->save();

            if ($cupom->pedido_checkout_id) {
                $pedido = PedidoCheckout::query()->lockForUpdate()->find($cupom->pedido_checkout_id);
                $pedido?->forceFill([
                    'status' => 'pendente',
                    'pago_at' => null,
                ])->save();
            }

            return $cupom->fresh();
        });
    }

    public function atualizarStatusAsaas(PedidoCheckout $pedido, string $statusPedido, ?string $asaasStatus = null): void
    {
        $pedido->forceFill([
            'status' => $statusPedido,
            'asaas_status' => $asaasStatus ?? $pedido->asaas_status,
        ])->save();
    }

    public function sincronizarPagamentoAsaas(PedidoCheckout $pedido): PedidoCheckout
    {
        if ($pedido->status === 'pago' || ! $pedido->asaas_pagamento_id) {
            return $pedido->fresh('cupons');
        }

        $pagamento = $this->servicoAsaas->consultarPagamento($pedido->asaas_pagamento_id);
        $asaasStatus = (string) ($pagamento['status'] ?? $pedido->asaas_status ?? '');
        $removido = (bool) ($pagamento['deleted'] ?? false) || $asaasStatus === 'DELETED';

        if ($asaasStatus === 'RECEIVED') {
            $this->marcarComoPago($pedido, $asaasStatus);

            return $pedido->fresh('cupons');
        }

        if ($asaasStatus === 'OVERDUE') {
            $this->atualizarStatusAsaas($pedido, 'expirado', $asaasStatus);

            return $pedido->fresh('cupons');
        }

        if ($asaasStatus === 'REFUNDED') {
            $this->atualizarStatusAsaas($pedido, 'estornado', $asaasStatus);

            return $pedido->fresh('cupons');
        }

        if ($removido) {
            $this->atualizarStatusAsaas($pedido, 'cancelado', $asaasStatus ?: 'DELETED');

            return $pedido->fresh('cupons');
        }

        $pedido->forceFill([
            'status' => 'pendente',
            'asaas_status' => $asaasStatus ?: $pedido->asaas_status,
            'erro_pagamento' => null,
        ])->save();

        return $pedido->fresh('cupons');
    }

    private function gerarCodigoCupom(): string
    {
        do {
            $codigo = strtoupper(Str::random(10));
        } while (Cupom::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
