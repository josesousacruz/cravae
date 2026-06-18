<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoCheckout extends Model
{
    protected $table = 'pedidos_checkout';

    protected $fillable = [
        'usuario_id',
        'torneio_id',
        'valor',
        'status',
        'forma_pagamento',
        'referencia_checkout',
        'asaas_pagamento_id',
        'asaas_status',
        'invoice_url',
        'pix_copia_cola',
        'pix_qr_code_base64',
        'pix_expira_at',
        'erro_pagamento',
        'pago_at',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'pix_expira_at' => 'datetime',
            'pago_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function torneio(): BelongsTo
    {
        return $this->belongsTo(Torneio::class, 'torneio_id');
    }

    public function cupons(): HasMany
    {
        return $this->hasMany(Cupom::class, 'pedido_checkout_id');
    }
}
