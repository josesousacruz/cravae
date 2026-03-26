<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

class UsuarioAdministradorSeeder extends Seeder
{
    public function run(): void
    {
        Usuario::query()->updateOrCreate(
            ['email' => 'admin@interworldcup.local'],
            [
                'nome' => 'Administrador',
                'password' => '12345678',
                'perfil' => 'administrador',
            ],
        );
    }
}
