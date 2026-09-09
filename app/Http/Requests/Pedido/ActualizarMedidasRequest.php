<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarMedidasRequest extends FormRequest
{
    /**
     * La ruta ya exige el permiso via middleware
     * `can:pedidos.actualizar_estado`; se repite aquí porque es el lugar
     * recomendado por Laravel para esta comprobación y protege el Form
     * Request si algún día se usa desde otra ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('pedidos.actualizar_estado');
    }

    /**
     * Medidas y cantidad REALES de producción. Es lo único que puede
     * diferir de la cotización que originó el pedido: el precio acordado con
     * el cliente NO cambia porque el taller haya cortado dos centímetros más
     * (por eso `pedido.total` sigue congelado desde la cotización).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:255'],
            'ancho' => ['nullable', 'numeric', 'min:0'],
            'alto' => ['nullable', 'numeric', 'min:0'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'descripcion' => 'descripción',
            'motivo' => 'motivo del ajuste',
        ];
    }
}
