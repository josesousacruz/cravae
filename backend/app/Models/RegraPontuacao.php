<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegraPontuacao extends Model
{
    protected $table = 'regras_pontuacao';

    protected $fillable = [
        'torneio_id',
        'fase_id',
        'chave',
        'nome',
        'descricao',
        'pontos',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function torneio(): BelongsTo
    {
        return $this->belongsTo(Torneio::class, 'torneio_id');
    }

    public function fase(): BelongsTo
    {
        return $this->belongsTo(Fase::class, 'fase_id');
    }

    public function eventosPontuacao(): HasMany
    {
        return $this->hasMany(EventoPontuacao::class, 'regra_pontuacao_id');
    }
}
