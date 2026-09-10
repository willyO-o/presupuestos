<?php

namespace App\Http\Requests\Usuario;

use App\Http\Requests\Concerns\ValidaAlcanceSucursal;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ValidaAlcanceSucursal;

    public function authorize(): bool
    {
        return $this->user()->can('usuarios.editar');
    }

    /**
     * `password` es opcional al editar: vacío = se conserva la actual.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($this->route('usuario')->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'estado' => ['required', Rule::in(User::ESTADOS)],
            'rol' => ['required', 'string', Rule::exists('roles', 'name')],
            'foto' => ['nullable', 'image', 'max:2048'],
            ...$this->reglasDeAlcance(),
        ];
    }

    /**
     * Alcance de sucursales (ver App\Models\Concerns\TieneAlcanceSucursal).
     * Idénticas a las de StoreUserRequest.
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
     * Nadie reparte más alcance del que tiene. Ver StoreUserRequest.
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
