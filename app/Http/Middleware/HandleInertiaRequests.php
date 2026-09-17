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
            // Campanita del topbar (Components/Layout/Topbar.vue): últimas N +
            // el conteo de no leídas, en cada visita. Closures por la misma
            // razón que 'flash' — no evaluar para invitados/portal cliente.
            'notificaciones' => fn () => $this->notificacionesTopbar($user),
        ];
    }

    /**
     * @return array{no_leidas: int, recientes: list<array<string, mixed>>}|null
     */
    private function notificacionesTopbar(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'no_leidas' => $user->unreadNotifications()->count(),
            'recientes' => $user->notifications()
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'titulo' => $n->data['titulo'] ?? '',
                    'mensaje' => $n->data['mensaje'] ?? '',
                    'icono' => $n->data['icono'] ?? 'fa-solid fa-bell',
                    'color' => $n->data['color'] ?? 'primary',
                    'leida' => $n->read_at !== null,
                    'creada_hace' => $n->created_at?->toIso8601String(),
                    'url' => route('notificaciones.abrir', $n->id),
                ])
                ->all(),
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
