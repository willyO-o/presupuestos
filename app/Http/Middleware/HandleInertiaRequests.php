<?php

namespace App\Http\Middleware;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                // Consumidos en el frontend por la directiva v-can
                // (resources/js/Directives/Can.js) para roles/permisos.
                'roles' => $user?->getRoleNames() ?? [],
                'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
                'is_super_admin' => $user?->hasRole('super-admin') ?? false,
                'sucursales' => $this->alcanceSucursal($user),
            ],
            // Mensajes flash de una sola vista tras un redirect()->with(...).
            // Closures para que se evaluen por request, no se cacheen.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Qué sucursales alcanza el usuario, para que la interfaz pueda ROTULAR lo
     * que está mostrando ("Viendo: El Alto, Santa Cruz") y filtrar sus propios
     * selectores de sucursal.
     *
     * No es un permiso: el filtrado de verdad pasa en el servidor
     * (`AcotaPorSucursal`). Esto es solo para que la pantalla no mienta sobre
     * el alcance de un número.
     *
     * `visibles` es `null` cuando ve todas — así no se consulta la tabla para
     * quien no tiene límite, que es el caso más común (super-admin, admin).
     *
     * @return array{ve_todas: bool, visibles: list<array{id: int, nombre: string}>|null}|null
     */
    private function alcanceSucursal(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $visibles = $user->sucursalesVisibles();

        return [
            've_todas' => $visibles === null,
            'visibles' => $visibles === null
                ? null
                : Sucursal::query()
                    ->whereIn('id', $visibles)
                    ->orderBy('nombre')
                    ->get(['id', 'nombre'])
                    ->all(),
        ];
    }
}
