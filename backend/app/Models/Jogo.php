<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Jogo extends Model
{
    protected $table = 'jogos';

    protected $fillable = [
        'torneio_id',
        'fase_id',
        'rodada_id',
        'grupo_id',
        'selecao_mandante_id',
        'selecao_visitante_id',
        'data_hora_inicio',
        'ordem_na_fase',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data_hora_inicio' => 'datetime',
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

    public function rodada(): BelongsTo
    {
        return $this->belongsTo(Rodada::class, 'rodada_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function selecaoMandante(): BelongsTo
    {
        return $this->belongsTo(Selecao::class, 'selecao_mandante_id');
    }

    public function selecaoVisitante(): BelongsTo
    {
        return $this->belongsTo(Selecao::class, 'selecao_visitante_id');
    }

    public function resultado(): HasOne
    {
        return $this->hasOne(ResultadoJogo::class, 'jogo_id');
    }

    public function apostas(): HasMany
    {
        return $this->hasMany(Aposta::class, 'jogo_id');
    }
}
