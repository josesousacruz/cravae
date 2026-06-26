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
        'liga_externa_id',
        'temporada_externa',
        'status',
        'data_inicio',
        'data_fim',
        'data_fechamento_podio',
        'valor_cupom',
        'compras_abertas',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'data_fechamento_podio' => 'datetime',
            'valor_cupom' => 'decimal:2',
            'compras_abertas' => 'boolean',
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
