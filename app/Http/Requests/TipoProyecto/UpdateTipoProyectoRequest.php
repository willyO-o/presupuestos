<?php

namespace App\Http\Requests\TipoProyecto;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTipoProyectoRequest extends FormRequest
{
    /**
     * La ruta ya exige el permiso via middleware `can:tipos-proyecto.editar`;
     * se repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('tipos-proyecto.editar');
    }

    /**
     * Mismas reglas que al crear, salvo que el nombre único ignora la propia
     * fila. Editar el factor/margen NO recalcula las cotizaciones ya
     * emitidas: cada línea guarda la foto de los valores con que se cotizó.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:255',
                Rule::unique('tipo_proyecto', 'nombre')->ignore($this->route('tipoProyecto')),
            ],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'factor_complejidad' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'margen_minimo' => ['required', 'numeric', 'min:0', 'max:500'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'factor_complejidad' => 'factor de complejidad',
            'margen_minimo' => 'margen mínimo',
        ];
    }
}
