<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarConsumoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pedidos.actualizar_estado');
    }

    /**
     * `costo_real` es opcional: si no viene, el controlador lo calcula como
     * `cantidad_usada × material.precio_unitario` vigente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', Rule::exists('material', 'id')],
            'cantidad_usada' => ['required', 'numeric', 'min:0.01'],
            'costo_real' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
