<?php

namespace App\Http\Requests\Reporte;

use App\Services\Reporte\InformeProyeccionesService;
use App\Services\Reporte\PeriodoReporte;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pedido del informe de proyecciones: el mismo filtro de fechas de las
 * pestañas del BI y qué secciones incluir (ninguna = todas).
 */
class InformeProyeccionesRequest extends FormRequest
{
    /**
     * La ruta ya exige `can:reportes.bi`; se repite aquí por si el Form
     * Request se usa desde otra ruta (mismo criterio que el resto).
     */
    public function authorize(): bool
    {
        return $this->user()->can('reportes.bi');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'secciones' => ['nullable', 'array'],
            'secciones.*' => ['string', Rule::in(array_keys(InformeProyeccionesService::SECCIONES))],
            'descargar' => ['nullable', 'boolean'],
        ];
    }

    public function periodo(): PeriodoReporte
    {
        return PeriodoReporte::desdeFiltros(
            $this->validated('desde'),
            $this->validated('hasta'),
            config('bi.meses_por_defecto'),
        );
    }

    /**
     * @return list<string>
     */
    public function secciones(): array
    {
        return array_values($this->validated('secciones') ?? []);
    }
}
