<?php

namespace App\Http\Requests\Reporte;

use App\Services\Reporte\PeriodoReporte;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros globales del Panorama del BI: fechas, tipo de producto (familia o
 * categoría), cliente y sucursal. Todos opcionales y todos viajan en la URL,
 * para que una vista filtrada se pueda compartir.
 *
 * Una sucursal que el usuario no ve no da error: el servicio acota igual por
 * su alcance (`visiblePara`) y el resultado sale vacío, que es lo correcto.
 */
class PanoramaRequest extends FormRequest
{
    /** La ruta ya exige `can:reportes.bi`; se repite por si se reusa. */
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
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'familia_id' => ['nullable', 'integer', Rule::exists('familia_producto', 'id')],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categoria_producto', 'id')],
            'cliente_id' => ['nullable', 'integer', Rule::exists('cliente', 'id')],
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursal', 'id')],
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
     * @return array{familia_id: ?int, categoria_id: ?int, cliente_id: ?int, sucursal_id: ?int}
     */
    public function filtros(): array
    {
        $entero = fn (string $clave): ?int => $this->validated($clave) === null ? null : (int) $this->validated($clave);

        return [
            'familia_id' => $entero('familia_id'),
            'categoria_id' => $entero('categoria_id'),
            'cliente_id' => $entero('cliente_id'),
            'sucursal_id' => $entero('sucursal_id'),
        ];
    }
}
