<?php

namespace App\Policies;

use App\Models\Torneio;
use App\Models\Usuario;
class TorneioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->perfil === 'administrador';
    }

    public function view(Usuario $usuario, Torneio $torneio): bool
    {
        return $usuario->perfil === 'administrador';
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->perfil === 'administrador';
    }

    public function update(Usuario $usuario, Torneio $torneio): bool
    {
        return $usuario->perfil === 'administrador';
    }

    public function delete(Usuario $usuario, Torneio $torneio): bool
    {
        return $usuario->perfil === 'administrador';
    }
}
