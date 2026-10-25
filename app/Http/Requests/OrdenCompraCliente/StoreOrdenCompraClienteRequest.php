<?php

namespace App\Http\Requests\OrdenCompraCliente;

use App\Http\Requests\Concerns\ValidaAlcanceSucursal;
use App\Models\Pedido;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrdenCompraClienteRequest extends FormRequest
{
    use ValidaAlcanceSucursal;

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
            'pedido_id' => [
                'required', 'integer', Rule::exists('pedido', 'id'),
                Rule::unique('orden_compra_cliente', 'pedido_id'),
                $this->registroVisible(Pedido::class, 'Ese pedido no es de una sucursal que administres.'),
            ],
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
