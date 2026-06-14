<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoPontuacao extends Model
{
    protected $table = 'eventos_pontuacao';

    protected $fillable = [
        'cupom_id',
        'regra_pontuacao_id',
        'jogo_id',
        'aposta_id',
        'pontos',
        'descricao',
    ];

    public function cupom(): BelongsTo
    {
        return $this->belongsTo(Cupom::class, 'cupom_id');
    }

    public function regraPontuacao(): BelongsTo
    {
        return $this->belongsTo(RegraPontuacao::class, 'regra_pontuacao_id');
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class, 'jogo_id');
    }
}
