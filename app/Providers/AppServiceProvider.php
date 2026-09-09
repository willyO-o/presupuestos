<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->registrarLimitesCotizadorPublico();
    }

    /**
     * Rate limiters del cotizador público (`/cotizador`).
     *
     * Son los únicos endpoints del sistema que aceptan trabajo de un visitante
     * anónimo, así que se limitan por IP y con techos distintos según lo que
     * cuesta cada uno:
     *
     * - `calcular`: recorre el BOM y evalúa fórmulas, pero no escribe nada. Se
     *   llama a cada cambio de medida, así que el límite es holgado.
     * - `guardar`: escribe una fila y le llega a ventas. Lleva TRES límites a
     *   la vez, porque tapan agujeros distintos: por hora y por día contra una
     *   IP insistente, y uno global que no mira de dónde viene la petición —
     *   los límites por IP no frenan una avalancha repartida entre cientos de
     *   direcciones, que es exactamente lo que ahogaría la bandeja de ventas.
     * - `consultar`: solo lectura por código, pero es el que un script usaría
     *   para adivinar códigos ajenos a fuerza bruta.
     * - `descargar`: emitir el documento arma un presupuesto entero. Además
     *   del límite por IP, cada estimación tiene su propio tope de emisiones
     *   (`config('cotizador.descargas_maximas')`, columna `descargas`): sin
     *   eso, un solo código válido alcanza para pedirlo en bucle para siempre.
     *
     * Los números viven en `config/cotizador.php`.
     */
    private function registrarLimitesCotizadorPublico(): void
    {
        $throttle = config('cotizador.throttle');

        RateLimiter::for(
            'cotizador-calcular',
            fn (Request $request) => Limit::perMinute($throttle['calcular'])->by($request->ip()),
        );

        // Los `by()` van prefijados: varios límites en un mismo limiter tienen
        // que usar claves distintas o comparten contador y se pisan.
        RateLimiter::for('cotizador-guardar', fn (Request $request) => [
            Limit::perHour($throttle['guardar'])->by('hora:'.$request->ip()),
            Limit::perDay($throttle['guardar_dia'])->by('dia:'.$request->ip()),
            Limit::perHour($throttle['guardar_global'])->by('global'),
        ]);

        RateLimiter::for(
            'cotizador-consultar',
            fn (Request $request) => Limit::perMinute($throttle['consultar'])->by($request->ip()),
        );

        RateLimiter::for(
            'cotizador-descargar',
            fn (Request $request) => Limit::perHour($throttle['descargar'])->by($request->ip()),
        );
    }
}
