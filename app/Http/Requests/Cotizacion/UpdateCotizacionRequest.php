<?php

namespace App\Http\Requests\Cotizacion;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCotizacionRequest extends FormRequest
{
    /**
     * La ruta ya exige el permiso via middleware `can:cotizaciones.editar`;
     * se repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware. Que la cotización siga siendo
     * editable (estado PENDIENTE) lo verifica el controlador.
     */
    public function authorize(): bool
    {
        return $this->user()->can('cotizaciones.editar');
    }

    /**
     * Mismas reglas que al crear: el detalle completo se reemplaza en cada
     * guardado y los montos se recalculan en el controlador.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('cliente', 'id')],
            'empleado_id' => ['required', 'integer', Rule::exists('empleado', 'id')],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursal', 'id')],
            'fecha' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'impuesto' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:2000'],

            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['nullable', 'integer', Rule::exists('producto', 'id')],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.ancho' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.alto' => ['nullable', 'numeric', 'min:0'],
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
            'detalles.*.producto_id' => 'producto',
            'detalles.*.descripcion' => 'descripción',
            'detalles.*.cantidad' => 'cantidad',
            'detalles.*.precio_unitario' => 'precio unitario',
        ];
    }
}
