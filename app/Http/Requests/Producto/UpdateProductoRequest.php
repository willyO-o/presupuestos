<?php

namespace App\Http\Requests\Producto;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * La ruta ya exige el permiso via middleware `can:productos.editar`; se
     * repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('productos.editar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categoria_producto_id' => ['required', 'integer', Rule::exists('categoria_producto', 'id')],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'unidad_medida' => ['required', Rule::in(['M2', 'UNIDAD', 'METRO_LINEAL'])],
            'precio_base' => ['nullable', 'numeric', 'min:0'],
            'requiere_medidas' => ['required', Rule::in(['SI', 'NO'])],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
