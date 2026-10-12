<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarAnomaliaRequest extends FormRequest
{
    /**
     * La ruta ya exige `can:reportes.asistente`; se repite aquí por si el
     * Form Request se usa desde otra ruta.
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
            'estado' => ['required', Rule::in(['NUEVA', 'REVISADA', 'DESCARTADA'])],
        ];
    }
}
