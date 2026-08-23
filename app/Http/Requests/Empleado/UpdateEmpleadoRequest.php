<?php

namespace App\Http\Requests\Empleado;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpleadoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * La ruta ya exige el permiso via middleware `can:empleados.editar`; se
     * repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('empleados.editar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursal', 'id')],
            'area_id' => ['required', 'integer', Rule::exists('area', 'id')],
            'nombre_completo' => ['required', 'string', 'max:255'],
            'ci' => ['required', 'string', 'max:255', Rule::unique('empleado', 'ci')->ignore($this->route('empleado'))],
            'cargo' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:25'],
            'fecha_ingreso' => ['required', 'date'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
