<?php

namespace App\Http\Requests\Compra;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompraRequest extends FormRequest
{
    /**
     * La ruta ya exige `can:compras.crear`; se repite aquí porque es el
     * lugar recomendado por Laravel y protege el Form Request si algún día
     * se usa desde otra ruta.
     */
    public function authorize(): bool
    {
        return $this->user()->can('compras.crear');
    }

    /**
     * `subtotal`, `total` y `estado` NO se aceptan del cliente — el
     * controlador calcula los montos del detalle y el estado siempre nace
     * PENDIENTE (ver CompraController).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'integer', Rule::exists('proveedor', 'id')],
            'empleado_id' => ['required', 'integer', Rule::exists('empleado', 'id')],
            'numero_factura' => ['nullable', 'string', 'max:255'],
            'fecha' => ['required', 'date'],

            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.material_id' => ['required', 'integer', Rule::exists('material', 'id')],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'detalles.*.material_id' => 'material',
            'detalles.*.cantidad' => 'cantidad',
            'detalles.*.precio_unitario' => 'precio unitario',
        ];
    }
}
