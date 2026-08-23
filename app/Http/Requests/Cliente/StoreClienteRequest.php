<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * La ruta ya exige el permiso via middleware `can:clientes.crear`; se
     * repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('clientes.crear');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['NATURAL', 'JURIDICO'])],
            'razon_social' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:255', Rule::unique('cliente', 'nit')],
            'contacto_nombre' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
