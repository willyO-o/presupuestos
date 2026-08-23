<?php

namespace App\Http\Middleware;

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
            ],
            // Mensajes flash de una sola vista tras un redirect()->with(...).
            // Closures para que se evaluen por request, no se cacheen.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
