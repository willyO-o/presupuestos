<?php

namespace App\Http\Requests\Seguimiento;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta pública del estado de un pedido (`/seguimiento`).
 *
 * Solo valida la FORMA de los datos. Que el pedido exista y que los dígitos
 * coincidan lo decide `SeguimientoPublicoService::buscar()`, y a propósito
 * con un único mensaje para los dos casos: decir "ese pedido no existe" y
 * "los dígitos no coinciden" por separado le contaría a quien prueba números
 * cuáles son reales.
 */
class BuscarPedidoRequest extends FormRequest
{
    /**
     * Ruta pública. La protegen el segundo dato (los 4 dígitos) y el rate
     * limiter `seguimiento-buscar`, no la autorización.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El número se acepta como lo escriba la gente: en minúsculas, con
     * espacios alrededor o copiado de un WhatsApp con un espacio de más.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero' => mb_strtoupper(preg_replace('/\s+/', '', (string) $this->input('numero'))),
            'digitos' => preg_replace('/\D+/', '', (string) $this->input('digitos')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numero' => ['required', 'string', 'regex:/^PED-[0-9]{8}-[A-Z0-9]{5}$/'],
            'digitos' => ['required', 'digits:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero.required' => 'Escribe el número de tu pedido.',
            'numero.regex' => 'El número de pedido tiene la forma PED-20261020-AB12C.',
            'digitos.required' => 'Escribe los últimos 4 dígitos de tu teléfono o NIT.',
            'digitos.digits' => 'Son exactamente 4 dígitos: los últimos de tu teléfono o NIT.',
        ];
    }
}
