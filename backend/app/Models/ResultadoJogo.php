<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultadoJogo extends Model
{
    protected $table = 'resultados_jogos';

    protected $fillable = [
        'jogo_id',
        'placar_mandante',
        'placar_visitante',
        'goleadores_mandante',
        'goleadores_visitante',
        'selecao_classificada_id',
        'encerrado_at',
    ];

    protected function casts(): array
    {
        return [
            'goleadores_mandante' => 'array',
            'goleadores_visitante' => 'array',
            'encerrado_at' => 'datetime',
        ];
    }

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class, 'jogo_id');
    }

    public function selecaoClassificada(): BelongsTo
    {
        return $this->belongsTo(Selecao::class, 'selecao_classificada_id');
    }
}
