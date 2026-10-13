<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtro del historial del asistente: un día (hora de la empresa) para ver
 * las consultas de esa fecha en lugar de las últimas.
 */
class HistorialAsistenteRequest extends FormRequest
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
            'fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now(config('ia.zona_horaria'))->toDateString()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.date_format' => 'La fecha no es válida.',
            'fecha.before_or_equal' => 'Elige una fecha de hoy o anterior.',
        ];
    }
}
