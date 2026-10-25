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
            // El cliente manda su OC en PDF o como foto/escaneo (pedido del
            // usuario: antes solo se aceptaba PDF). La columna conserva su
            // nombre histórico `archivo_pdf`.
            'archivo_pdf' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['archivo_pdf' => 'archivo de la orden de compra'];
    }
}
