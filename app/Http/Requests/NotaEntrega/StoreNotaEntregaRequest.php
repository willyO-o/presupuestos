<?php

namespace App\Http\Requests\NotaEntrega;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotaEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('notas-entrega.crear');
    }

    /**
     * `numero_nota` lo genera el controlador. Las líneas referencian ítems
     * del pedido; el controlador marca esos `pedido_detalle` como
     * ENTREGADO y recalcula el estado del pedido.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pedido_id' => ['required', 'integer', Rule::exists('pedido', 'id')],
            'empleado_id' => ['required', 'integer', Rule::exists('empleado', 'id')],
            'fecha_entrega' => ['required', 'date'],
            'recibido_por' => ['nullable', 'string', 'max:255'],
            'cargo_receptor' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],

            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.pedido_detalle_id' => ['required', 'integer', Rule::exists('pedido_detalle', 'id')],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad_entregada' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.ubicacion' => ['nullable', 'string', 'max:255'],
            'detalles.*.foto' => ['nullable', 'image', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'detalles.*.pedido_detalle_id' => 'ítem del pedido',
            'detalles.*.descripcion' => 'descripción',
            'detalles.*.cantidad_entregada' => 'cantidad entregada',
        ];
    }
}
