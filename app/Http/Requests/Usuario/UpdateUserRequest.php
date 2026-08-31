<?php

namespace App\Http\Requests\Usuario;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
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
        ];
    }
}
