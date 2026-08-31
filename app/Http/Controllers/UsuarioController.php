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
            ->with('roles:id,name')
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
        ]);

        if ($request->hasFile('foto')) {
            $user->foto = $request->file('foto')->store('avatars', 'public');
            $user->save();
        }

        $user->syncRoles([$datos['rol']]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function update(UpdateUserRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->validated();

        $usuario->fill([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'estado' => $datos['estado'],
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

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
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
