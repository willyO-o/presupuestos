<?php

namespace App\Http\Requests\OrdenCompraCliente;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrdenCompraClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ordenes-compra-cliente.crear');
    }

    /**
     * `cliente_id` lo deriva el controlador del pedido; `estado` nace
     * PENDIENTE. `pedido_id` es único: a lo sumo una OC por pedido.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pedido_id' => ['required', 'integer', Rule::exists('pedido', 'id'), Rule::unique('orden_compra_cliente', 'pedido_id')],
            'numero_oc' => ['required', 'string', 'max:255'],
            'fecha' => ['required', 'date'],
            'monto_total' => ['required', 'numeric', 'min:0'],
            'condicion_pago' => ['nullable', 'string', 'max:255'],
            'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }
}
