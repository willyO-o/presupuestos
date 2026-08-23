<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // El rol super-admin pasa cualquier $user->can() / @can del backend
        // (debe devolver null, no false, para no romper el chequeo normal
        // de otros usuarios). Espejo del bypass que ya existe en el
        // frontend via la directiva v-can (resources/js/Directives/Can.js).
        Gate::before(fn ($user, string $ability) => $user->hasRole('super-admin') ? true : null);
    }
}
