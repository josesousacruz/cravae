<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fase extends Model
{
    protected $table = 'fases';

    protected $fillable = [
        'torneio_id',
        'nome',
        'slug',
        'ordem',
        'tipo',
        'data_fechamento',
    ];

    protected function casts(): array
    {
        return [
            'data_fechamento' => 'datetime',
        ];
    }

    public function torneio(): BelongsTo
    {
        return $this->belongsTo(Torneio::class, 'torneio_id');
    }

    public function rodadas(): HasMany
    {
        return $this->hasMany(Rodada::class, 'fase_id');
    }

    public function jogos(): HasMany
    {
        return $this->hasMany(Jogo::class, 'fase_id');
    }

    public function regrasPontuacao(): HasMany
    {
        return $this->hasMany(RegraPontuacao::class, 'fase_id');
    }
}
