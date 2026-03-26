<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Torneio extends Model
{
    protected $table = 'torneios';

    protected $fillable = [
        'nome',
        'edicao',
        'status',
        'data_inicio',
        'data_fim',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
        ];
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'torneio_id');
    }

    public function selecoes(): HasMany
    {
        return $this->hasMany(Selecao::class, 'torneio_id');
    }

    public function fases(): HasMany
    {
        return $this->hasMany(Fase::class, 'torneio_id');
    }

    public function jogos(): HasMany
    {
        return $this->hasMany(Jogo::class, 'torneio_id');
    }

    public function regrasPontuacao(): HasMany
    {
        return $this->hasMany(RegraPontuacao::class, 'torneio_id');
    }

    public function resultadoTorneio(): HasOne
    {
        return $this->hasOne(ResultadoTorneio::class, 'torneio_id');
    }
}
