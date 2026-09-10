<?php

namespace App\Http\Controllers;

use App\Http\Requests\Usuario\StoreUserRequest;
use App\Http\Requests\Usuario\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $usuarios = User::query()
            // `sucursales` alimenta el bloque de alcance del modal de edición;
            // `empleado.sucursal` muestra cuál es la "propia" sin que el
            // frontend tenga que cruzarlo contra el módulo de empleados.
            ->with(['roles:id,name', 'sucursales:id,nombre', 'empleado:id,user_id,sucursal_id', 'empleado.sucursal:id,nombre'])
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($request->query('rol'), fn ($q, $rol) => $q->role($rol))
            ->when($request->query('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return inertia('Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => Role::query()->orderBy('name')->pluck('name'),
            'estados' => User::ESTADOS,
            'alcances' => User::ALCANCES,
            // Solo se pueden REPARTIR las sucursales que uno administra: si no,
            // cualquiera con `usuarios.editar` se daría a sí mismo acceso a
            // toda la empresa creando una cuenta con alcance TODAS. Hoy solo
            // `administrador` (global) tiene ese permiso, pero los roles son
            // personalizables y el agujero estaría esperando.
            'sucursales' => $request->user()->sucursalesDisponibles(soloActivas: true),
            // Estos roles ven todas las sucursales por su rol: la pantalla lo
            // avisa en vez de deshabilitar el selector (un input deshabilitado
            // no se envía y haría fallar la validación de `required`).
            'rolesGlobales' => config('acl.sucursales.roles_globales', []),
            'filters' => $request->only(['search', 'rol', 'estado']),
            'pageTitle' => 'Usuarios',
            'breadcrumbs' => ['Administración', 'Usuarios'],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $user = User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => Hash::make($datos['password']),
            'estado' => $datos['estado'],
            'alcance_sucursal' => $datos['alcance_sucursal'],
        ]);

        if ($request->hasFile('foto')) {
            $user->foto = $request->file('foto')->store('avatars', 'public');
            $user->save();
        }

        $user->syncRoles([$datos['rol']]);
        $this->sincronizarSucursales($user, $datos);

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function update(UpdateUserRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->validated();

        $usuario->fill([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'estado' => $datos['estado'],
            'alcance_sucursal' => $datos['alcance_sucursal'],
        ]);

        if (! empty($datos['password'])) {
            $usuario->password = Hash::make($datos['password']);
        }

        if ($request->hasFile('foto')) {
            if ($usuario->foto) {
                Storage::disk('public')->delete($usuario->foto);
            }
            $usuario->foto = $request->file('foto')->store('avatars', 'public');
        }

        $usuario->save();
        $usuario->syncRoles([$datos['rol']]);
        $this->sincronizarSucursales($usuario, $datos);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Guarda las sucursales asignadas a mano.
     *
     * Con cualquier alcance que NO sea `ASIGNADAS` el pivote se vacía en vez de
     * dejarse como estaba: una fila huérfana ahí no hace nada hoy, pero le
     * daría esas sucursales de golpe a la cuenta si mañana alguien le cambia el
     * alcance (desde la UI o a mano en la base). Se prefiere que la lista sea
     * siempre un reflejo exacto de lo que muestra la pantalla.
     *
     * @param  array<string, mixed>  $datos
     */
    private function sincronizarSucursales(User $usuario, array $datos): void
    {
        $usuario->sucursales()->sync(
            $datos['alcance_sucursal'] === 'ASIGNADAS' ? ($datos['sucursales'] ?? []) : [],
        );

        // El alcance se memoriza por instancia: sin esto, cualquier lectura
        // posterior dentro de este mismo request devolvería el de ANTES de
        // guardar (ver TieneAlcanceSucursal::olvidarAlcanceSucursal).
        $usuario->olvidarAlcanceSucursal();
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return redirect()->route('usuarios.index')
                ->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($usuario->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            return redirect()->route('usuarios.index')
                ->with('error', 'Debe existir al menos un super administrador.');
        }

        if ($usuario->foto) {
            Storage::disk('public')->delete($usuario->foto);
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
