<?php

namespace App\Http\Requests\SeguimientoPostventa;

use App\Models\SeguimientoPostventa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarSeguimientoRequest extends FormRequest
{
    /**
     * La ruta ya exige el permiso via middleware
     * `can:seguimientos-postventa.registrar`; se repite aquí porque es el
     * lugar recomendado por Laravel para esta comprobación y protege el Form
     * Request si algún día se usa desde otra ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('seguimientos-postventa.registrar');
    }

    /**
     * `empleado_id` y `fecha_contacto` NO se aceptan del cliente: los pone el
     * controlador con el usuario autenticado y la fecha del servidor, porque
     * son la constancia de quién llamó y cuándo.
     *
     * Con estado NO_CONTACTADO (no se logró hablar con el cliente) no tiene
     * sentido exigir satisfacción ni medio.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['REALIZADO', 'NO_CONTACTADO'])],
            'medio' => ['required_if:estado,REALIZADO', 'nullable', Rule::in(SeguimientoPostventa::MEDIOS)],
            'satisfaccion' => [
                'required_if:estado,REALIZADO', 'nullable', 'integer',
                'min:1', 'max:'.config('postventa.satisfaccion_maxima'),
            ],
            'requiere_accion' => ['required', Rule::in(['SI', 'NO'])],
            'oportunidad' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'medio' => 'medio de contacto',
            'satisfaccion' => 'satisfacción',
            'requiere_accion' => 'requiere acción',
        ];
    }
}
