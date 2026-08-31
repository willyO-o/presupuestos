<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rol\SaveRolRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RolController extends Controller
{
    /**
     * El rol `super-admin` no se edita ni se borra desde la UI: tiene bypass
     * total vía Gate::before y no depende de la lista de permisos.
     */
    private const PROTEGIDO = 'super-admin';

    public function index(): Response
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return inertia('Roles/Index', [
            'roles' => $roles,
            'pageTitle' => 'Roles y permisos',
            'breadcrumbs' => ['Administración', 'Roles y permisos'],
        ]);
    }

    public function create(): Response
    {
        return inertia('Roles/Edit', [
            'rol' => null,
            'modulos' => $this->modulos(),
            'pageTitle' => 'Nuevo rol',
            'breadcrumbs' => ['Administración', 'Roles y permisos', 'Nuevo'],
        ]);
    }

    public function store(SaveRolRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $rol = Role::create(['name' => $datos['name'], 'guard_name' => 'web']);
        $rol->syncPermissions($datos['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', "Rol «{$rol->name}» creado.");
    }

    public function edit(Role $rol): Response|RedirectResponse
    {
        if ($rol->name === self::PROTEGIDO) {
            return redirect()->route('roles.index')
                ->with('error', 'El rol super-admin no se edita: tiene acceso total por diseño.');
        }

        return inertia('Roles/Edit', [
            'rol' => [
                'id' => $rol->id,
                'name' => $rol->name,
                'permissions' => $rol->permissions->pluck('name'),
            ],
            'modulos' => $this->modulos(),
            'pageTitle' => "Editar rol {$rol->name}",
            'breadcrumbs' => ['Administración', 'Roles y permisos', $rol->name],
        ]);
    }

    public function update(SaveRolRequest $request, Role $rol): RedirectResponse
    {
        abort_if($rol->name === self::PROTEGIDO, HttpResponse::HTTP_FORBIDDEN);

        $datos = $request->validated();

        $rol->update(['name' => $datos['name']]);
        $rol->syncPermissions($datos['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', "Rol «{$rol->name}» actualizado.");
    }

    public function destroy(Role $rol): RedirectResponse
    {
        if ($rol->name === self::PROTEGIDO) {
            return redirect()->route('roles.index')->with('error', 'El rol super-admin no se puede eliminar.');
        }

        if ($rol->users()->exists()) {
            return redirect()->route('roles.index')
                ->with('error', 'No se puede eliminar un rol con usuarios asignados.');
        }

        $nombre = $rol->name;
        $rol->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('success', "Rol «{$nombre}» eliminado.");
    }

    /**
     * Módulos y sus acciones desde config/acl.php, para pintar la matriz de
     * checkboxes (etiqueta del módulo + acción → permiso).
     *
     * @return list<array{clave: string, label: string, permisos: list<array{name: string, label: string}>}>
     */
    private function modulos(): array
    {
        return collect(config('acl.modules', []))
            ->map(fn (array $modulo, string $clave): array => [
                'clave' => $clave,
                'label' => $modulo['label'],
                'permisos' => collect($modulo['permissions'])
                    ->map(fn (string $label, string $name): array => ['name' => $name, 'label' => $label])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
