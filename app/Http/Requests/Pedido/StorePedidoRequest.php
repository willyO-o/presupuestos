<?php

namespace App\Http\Requests\Pedido;

use App\Http\Requests\Concerns\ValidaAlcanceSucursal;
use App\Models\Cotizacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePedidoRequest extends FormRequest
{
    use ValidaAlcanceSucursal;

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
            // Convertir en pedido la cotización de otra sucursal es escribir
            // sobre su cartera (ver PedidoController::create).
            'cotizacion_id' => [
                'required', 'integer', Rule::exists('cotizacion', 'id'),
                $this->registroVisible(Cotizacion::class, 'Esa cotización no es de una sucursal que administres.'),
            ],
            'fecha_entrega_estimada' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
