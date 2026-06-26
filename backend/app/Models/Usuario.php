<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'cpf_cnpj',
        'foto',
        'asaas_cliente_id',
        'password',
        'perfil',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'foto',
    ];

    protected $appends = [
        'foto_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function fotoUrl(): Attribute
    {
        // Servido direto de public/uploads (sem symlink). Caminho relativo
        // (ex.: /uploads/avatares/x.jpg); o frontend prefixa com a origem do backend.
        return Attribute::get(fn (): ?string => $this->foto
            ? '/uploads/'.ltrim($this->foto, '/')
            : null);
    }

    public function pedidosCheckout(): HasMany
    {
        return $this->hasMany(PedidoCheckout::class, 'usuario_id');
    }

    public function cupons(): HasMany
    {
        return $this->hasMany(Cupom::class, 'usuario_id');
    }
}
