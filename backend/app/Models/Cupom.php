<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cupom extends Model
{
    protected $table = 'cupons';

    protected $fillable = [
        'usuario_id',
        'torneio_id',
        'pedido_checkout_id',
        'codigo',
        'status',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function torneio(): BelongsTo
    {
        return $this->belongsTo(Torneio::class, 'torneio_id');
    }

    public function pedidoCheckout(): BelongsTo
    {
        return $this->belongsTo(PedidoCheckout::class, 'pedido_checkout_id');
    }

    public function apostas(): HasMany
    {
        return $this->hasMany(Aposta::class, 'cupom_id');
    }

    public function logsApostas(): HasMany
    {
        return $this->hasMany(LogAposta::class, 'cupom_id');
    }

    public function eventosPontuacao(): HasMany
    {
        return $this->hasMany(EventoPontuacao::class, 'cupom_id');
    }

    public function pontuacao(): HasOne
    {
        return $this->hasOne(PontuacaoCupom::class, 'cupom_id');
    }
}
