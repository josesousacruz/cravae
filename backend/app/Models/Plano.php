<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plano extends Model
{
    protected $table = 'planos';

    protected $fillable = [
        'nome',
        'slug',
        'preco_centavos',
        'preco_fundador_centavos',
        'vagas_fundador',
        'max_cupons',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function assinaturas(): HasMany
    {
        return $this->hasMany(Assinatura::class, 'plano_id');
    }

    /**
     * Vagas de fundador ainda disponíveis: só assinaturas de fundador não
     * canceladas ocupam vaga (cancelou, perdeu a condição e libera a vaga).
     */
    public function vagasFundadorRestantes(): int
    {
        $ocupadas = $this->assinaturas()
            ->where('fundador', true)
            ->where('status', '!=', Assinatura::STATUS_CANCELADA)
            ->count();

        return max(0, $this->vagas_fundador - $ocupadas);
    }
}
