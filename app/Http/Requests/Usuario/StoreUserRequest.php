<?php

namespace App\Http\Requests\Usuario;

use App\Http\Requests\Concerns\ValidaAlcanceSucursal;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use ValidaAlcanceSucursal;

    public function authorize(): bool
    {
        return $this->user()->can('usuarios.crear');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'estado' => ['required', Rule::in(User::ESTADOS)],
            'rol' => ['required', 'string', Rule::exists('roles', 'name')],
            'foto' => ['nullable', 'image', 'max:2048'],
            ...$this->reglasDeAlcance(),
        ];
    }

    /**
     * Alcance de sucursales (ver App\Models\Concerns\TieneAlcanceSucursal).
     * Idénticas a las de UpdateUserRequest.
     *
     * @return array<string, array<mixed>>
     */
    private function reglasDeAlcance(): array
    {
        return [
            'alcance_sucursal' => ['required', Rule::in(User::ALCANCES), $this->soloReparteLoQueAdministra()],
            // Solo se exige la lista con ASIGNADAS: con PROPIA sale de la ficha
            // de empleado y con TODAS no hay filtro. Un array vacío cuenta como
            // ausente para `required_if`, que es justo lo que se quiere — una
            // cuenta ASIGNADAS sin nada marcado no vería ninguna sucursal.
            'sucursales' => ['nullable', 'array', 'required_if:alcance_sucursal,ASIGNADAS'],
            'sucursales.*' => ['integer', Rule::exists('sucursal', 'id'), $this->sucursalAdministrada()],
        ];
    }

    /**
     * Nadie reparte más alcance del que tiene.
     *
     * Sin esto, quien administra una sola sucursal pero tiene
     * `usuarios.crear`/`editar` se da acceso a toda la empresa creando (o
     * editándose) una cuenta con alcance TODAS. Hoy solo `administrador`
     * —que es global— tiene esos permisos, pero los roles son personalizables
     * desde la propia UI: el agujero estaría esperando a que alguien arme un
     * rol "supervisor".
     */
    private function soloReparteLoQueAdministra(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fail): void {
            if ($valor === 'TODAS' && ! $this->user()->veTodasLasSucursales()) {
                $fail('No puedes dar acceso a todas las sucursales si tú no lo tienes.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sucursales.required_if' => 'Elige al menos una sucursal para el alcance "Asignadas".',
        ];
    }
}
