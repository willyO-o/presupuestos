<?php

namespace App\Http\Requests\Pedido;

use App\Models\PedidoSeguimiento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pedidos.asignar_area');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', Rule::exists('area', 'id')],
            'empleado_id' => ['required', 'integer', Rule::exists('empleado', 'id')],
            'etapa' => ['required', Rule::in(PedidoSeguimiento::ETAPAS)],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
