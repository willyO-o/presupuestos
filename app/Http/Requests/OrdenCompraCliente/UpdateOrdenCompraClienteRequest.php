<?php

namespace App\Http\Requests\OrdenCompraCliente;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrdenCompraClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('ordenes-compra-cliente.crear');
    }

    /**
     * El pedido asociado no cambia; solo se editan los datos del documento.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numero_oc' => ['required', 'string', 'max:255'],
            'fecha' => ['required', 'date'],
            'monto_total' => ['required', 'numeric', 'min:0'],
            'condicion_pago' => ['nullable', 'string', 'max:255'],
            'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }
}
