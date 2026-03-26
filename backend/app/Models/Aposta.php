<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aposta extends Model
{
    protected $table = 'apostas';

    protected $fillable = [
        'cupom_id',
        'tipo',
        'torneio_id',
        'fase_id',
        'rodada_id',
        'grupo_id',
        'jogo_id',
        'selecao_id',
        'jogador_id',
        'conteudo',
    ];

    protected function casts(): array
    {
        return [
            'conteudo' => 'array',
        ];
    }

    public function cupom(): BelongsTo
    {
        return $this->belongsTo(Cupom::class, 'cupom_id');
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

    public function jogo(): BelongsTo
    {
        return $this->belongsTo(Jogo::class, 'jogo_id');
    }

    public function selecao(): BelongsTo
    {
        return $this->belongsTo(Selecao::class, 'selecao_id');
    }

    public function jogador(): BelongsTo
    {
        return $this->belongsTo(Jogador::class, 'jogador_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogAposta::class, 'aposta_id');
    }
}
