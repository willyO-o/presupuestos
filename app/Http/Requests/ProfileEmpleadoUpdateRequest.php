<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileEmpleadoUpdateRequest extends FormRequest
{
    /**
     * Solo quien tiene una ficha de empleado vinculada a su cuenta puede
     * actualizarla desde el perfil.
     */
    public function authorize(): bool
    {
        return $this->user()->empleado()->exists();
    }

    /**
     * Datos de la ficha que el propio empleado puede corregir desde su perfil.
     *
     * Cargo, sucursal, área y estado NO están aquí a propósito: definen el
     * rol y el alcance por sucursal de la cuenta, así que solo se cambian
     * desde el módulo Empleados. Al no estar en las reglas, `validated()` los
     * descarta aunque el navegador los mande.
     *
     * La fecha llega como dd-mm-aaaa (el formato que ve el usuario), no como
     * Y-m-d: el controlador la convierte antes de guardar.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:255'],
            'paterno' => ['nullable', 'string', 'max:255'],
            'materno' => ['nullable', 'string', 'max:255'],
            'ci' => [
                'required',
                'string',
                'max:255',
                Rule::unique('empleado', 'ci')->ignore($this->user()->empleado?->id),
            ],
            'telefono' => ['nullable', 'string', 'max:25'],
            'fecha_ingreso' => ['required', 'date_format:d-m-Y', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_ingreso.date_format' => 'Escribe la fecha como dd-mm-aaaa, por ejemplo 15-03-2024.',
            'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser posterior a hoy.',
        ];
    }
}
