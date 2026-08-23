<?php

namespace App\Http\Requests\Material;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * La ruta ya exige el permiso via middleware `can:materiales.editar`; se
     * repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('materiales.editar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categoria_material_id' => ['required', 'integer', Rule::exists('categoria_material', 'id')],
            'nombre' => ['required', 'string', 'max:255'],
            'presentacion' => ['required', 'string', 'max:255'],
            'unidad_medida' => ['required', Rule::in(['M2', 'METRO', 'UNIDAD', 'LITRO'])],
            'precio_presentacion' => ['required', 'numeric', 'min:0'],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
            'stock_actual' => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
