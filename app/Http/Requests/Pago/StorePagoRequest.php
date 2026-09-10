<?php

namespace App\Http\Requests\Pago;

use App\Http\Requests\Concerns\ValidaAlcanceSucursal;
use App\Models\Pedido;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagoRequest extends FormRequest
{
    use ValidaAlcanceSucursal;

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
            // Sin esto se cobra contra el pedido de otra sucursal mandando su id.
            'pedido_id' => [
                'required', 'integer', Rule::exists('pedido', 'id'),
                $this->registroVisible(Pedido::class, 'Ese pedido no es de una sucursal que administres.'),
            ],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'metodo_pago' => ['required', Rule::in(['EFECTIVO', 'TRANSFERENCIA', 'QR', 'TARJETA', 'CHEQUE'])],
            'comprobante' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ];
    }
}
