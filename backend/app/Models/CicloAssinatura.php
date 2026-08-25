<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CicloAssinatura extends Model
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_PAGO = 'pago';
    public const STATUS_FALHOU = 'falhou';

    protected $table = 'ciclos_assinatura';

    protected $fillable = [
        'assinatura_id',
        'competencia',
        'status',
        'pago_em',
        'id_cobranca_asaas',
    ];

    protected function casts(): array
    {
        return [
            'pago_em' => 'datetime',
        ];
    }

    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(Assinatura::class, 'assinatura_id');
    }
}
