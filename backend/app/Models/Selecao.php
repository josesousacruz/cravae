<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Selecao extends Model
{
    protected $table = 'selecoes';

    protected $fillable = [
        'torneio_id',
        'grupo_id',
        'nome',
        'sigla',
        'id_externo',
        'slug',
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

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function jogadores(): HasMany
    {
        return $this->hasMany(Jogador::class, 'selecao_id');
    }

    public function jogosMandante(): HasMany
    {
        return $this->hasMany(Jogo::class, 'selecao_mandante_id');
    }

    public function jogosVisitante(): HasMany
    {
        return $this->hasMany(Jogo::class, 'selecao_visitante_id');
    }
}
