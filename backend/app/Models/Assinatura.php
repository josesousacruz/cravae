<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assinatura extends Model
{
    public const STATUS_ATIVA = 'ativa';
    public const STATUS_INADIMPLENTE = 'inadimplente';
    public const STATUS_CANCELADA = 'cancelada';

    protected $table = 'assinaturas';

    protected $fillable = [
        'usuario_id',
        'plano_id',
        'status',
        'fundador',
        'preco_centavos_contratado',
        'id_assinatura_asaas',
        'assinada_em',
        'cancelada_em',
    ];

    protected function casts(): array
    {
        return [
            'fundador' => 'boolean',
            'assinada_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    public function ciclos(): HasMany
    {
        return $this->hasMany(CicloAssinatura::class, 'assinatura_id');
    }

    public function cicloDaCompetencia(string $competencia): ?CicloAssinatura
    {
        return $this->ciclos()->where('competencia', $competencia)->first();
    }
}
