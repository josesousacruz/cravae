<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jogador extends Model
{
    protected $table = 'jogadores';

    protected $fillable = [
        'selecao_id',
        'id_externo',
        'nome',
        'apelido',
        'posicao',
        'numero_camisa',
        'foto_url',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function selecao(): BelongsTo
    {
        return $this->belongsTo(Selecao::class, 'selecao_id');
    }
}
