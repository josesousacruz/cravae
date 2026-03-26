<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultadoTorneio extends Model
{
    protected $table = 'resultados_torneio';

    protected $fillable = [
        'torneio_id',
        'campeao_selecao_id',
        'vice_campeao_selecao_id',
        'terceiro_colocado_selecao_id',
        'artilheiro_jogador_id',
    ];

    public function torneio(): BelongsTo
    {
        return $this->belongsTo(Torneio::class, 'torneio_id');
    }
}
