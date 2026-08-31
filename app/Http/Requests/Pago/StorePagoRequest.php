<?php

namespace App\Http\Requests\Pago;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pagos.registrar');
    }

    /**
     * `estado` lo calcula el controlador según el saldo del pedido tras
     * este pago — no se acepta del cliente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pedido_id' => ['required', 'integer', Rule::exists('pedido', 'id')],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'metodo_pago' => ['required', Rule::in(['EFECTIVO', 'TRANSFERENCIA', 'QR', 'TARJETA', 'CHEQUE'])],
            'comprobante' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ];
    }
}
