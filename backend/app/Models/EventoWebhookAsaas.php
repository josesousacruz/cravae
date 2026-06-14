<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoWebhookAsaas extends Model
{
    protected $table = 'eventos_webhook_asaas';

    protected $fillable = [
        'asaas_evento_id',
        'evento',
        'asaas_pagamento_id',
        'payload',
        'status',
        'processado_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processado_at' => 'datetime',
        ];
    }
}
