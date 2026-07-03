<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAposta extends Model
{
    public $timestamps = false;

    protected $table = 'logs_apostas';

    protected $fillable = [
        'cupom_id',
        'aposta_id',
        'usuario_id',
        'acao',
        'conteudo_anterior',
        'conteudo_novo',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'conteudo_anterior' => 'array',
            'conteudo_novo' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function cupom(): BelongsTo
    {
        return $this->belongsTo(Cupom::class, 'cupom_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function aposta(): BelongsTo
    {
        return $this->belongsTo(Aposta::class, 'aposta_id');
    }
}
