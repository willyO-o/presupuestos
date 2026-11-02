<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileEmpleadoUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     *
     * `empleado` viaja aparte (no solo desde `auth.user`) con su sucursal y
     * área ya cargadas: es la ficha de RR.HH. vinculada a esta cuenta
     * (`empleado.user_id`). El propio usuario corrige aquí sus datos de
     * identificación y contacto (ver updateEmpleado()); cargo, sucursal,
     * área y estado solo se cambian desde el módulo Empleados.
     */
    public function edit(Request $request): Response
    {
        return inertia('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'empleado' => $request->user()->empleado()->with(['sucursal', 'area'])->first(),
        ]);
    }

    /**
     * Update the user's profile information (nombre, email y foto de perfil).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->except('foto'));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('foto')) {
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }

            $user->foto = $request->file('foto')->store('avatars', 'public');
        }

        $user->save();

        return redirect()->route('profile.edit')
            ->with('success', 'Perfil actualizado correctamente.');
    }

    /**
     * Update the editable part of the linked empleado ficha (nombres,
     * apellidos, CI, teléfono y fecha de ingreso). Cargo, sucursal, área y
     * estado quedan fuera: ver ProfileEmpleadoUpdateRequest.
     */
    public function updateEmpleado(ProfileEmpleadoUpdateRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $datos['fecha_ingreso'] = Carbon::createFromFormat('d-m-Y', $datos['fecha_ingreso'])->startOfDay();

        $request->user()->empleado->update($datos);

        return redirect()->route('profile.edit')
            ->with('success', 'Información laboral actualizada correctamente.');
    }
}
