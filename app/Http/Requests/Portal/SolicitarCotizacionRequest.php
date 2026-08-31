<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('cliente') && $this->user()->cliente !== null;
    }

    /**
     * Solicitud de cotización desde el portal: el cliente describe lo que
     * necesita, ventas le pone precio después. No se aceptan montos.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['nullable', 'integer', Rule::exists('producto', 'id')],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.ancho' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.alto' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
