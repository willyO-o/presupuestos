<?php

namespace App\Http\Requests\CotizadorWeb;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajustes generales del cotizador web. `holgura` llega en PORCENTAJE
 * (15 = ±15 %) y el controlador la guarda como fracción.
 */
class UpdateAjustesCotizadorWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cotizador-web.configurar');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Se guarda el NOMBRE y no el id (ver CotizadorPublicoService::tipoProyecto()).
            'tipo_proyecto' => [
                'nullable', 'string',
                Rule::exists('tipo_proyecto', 'nombre')->where('estado', 'ACTIVO'),
            ],
            'holgura' => ['required', 'numeric', 'min:0', 'max:50'],
            'vigencia_dias' => ['required', 'integer', 'min:1', 'max:90'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo_proyecto' => 'nivel de complejidad',
            'holgura' => 'rango aproximado',
            'vigencia_dias' => 'vigencia',
        ];
    }
}
