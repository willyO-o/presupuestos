<?php

namespace App\Http\Requests\Pedido;

use App\Models\PedidoDetalle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarEstadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pedidos.actualizar_estado');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estado_item' => ['required', Rule::in(PedidoDetalle::ESTADOS)],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
