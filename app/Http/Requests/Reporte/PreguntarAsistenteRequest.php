<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreguntarAsistenteRequest extends FormRequest
{
    /**
     * La ruta ya exige `can:reportes.asistente`; se repite aquí por si el
     * Form Request se usa desde otra ruta (mismo criterio que el resto).
     */
    public function authorize(): bool
    {
        return $this->user()->can('reportes.asistente');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pregunta' => ['required', 'string', 'min:3', 'max:500'],
            // El id de conversación lo genera el SDK (uuid); el servicio
            // comprueba además que sea del usuario antes de continuarla.
            'conversacion_id' => ['nullable', 'string', 'max:36'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pregunta.required' => 'Escribe una pregunta.',
            'pregunta.max' => 'La pregunta puede tener hasta 500 caracteres.',
        ];
    }
}
