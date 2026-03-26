<?php

namespace App\Providers;

use App\Models\Torneio;
use App\Models\Usuario;
use App\Policies\TorneioPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Torneio::class, TorneioPolicy::class);
        Gate::define('acessar-area-admin', fn (Usuario $usuario) => $usuario->perfil === 'administrador');
    }
}
