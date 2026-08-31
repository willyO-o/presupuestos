<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pedidos.crear');
    }

    /**
     * El pedido se genera copiando la cotización aprobada — el controlador
     * valida el estado y el resto de datos (número, líneas, total) los
     * deriva, no se aceptan del cliente.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cotizacion_id' => ['required', 'integer', Rule::exists('cotizacion', 'id')],
            'fecha_entrega_estimada' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
