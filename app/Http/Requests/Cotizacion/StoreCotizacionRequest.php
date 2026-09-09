<?php

namespace App\Http\Requests\Cotizacion;

use App\Models\CotizacionDetalleItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCotizacionRequest extends FormRequest
{
    /**
     * La ruta ya exige el permiso via middleware `can:cotizaciones.crear`; se
     * repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('cotizaciones.crear');
    }

    /**
     * `codigo_verificacion`, `subtotal`, `iva`, `it`/`iue`/
     * `utilidad_real`, `estado_margen` y `total` NO se aceptan del cliente:
     * el controlador los deriva de los insumos de cada línea con el motor de
     * margen, para que ni el precio ni la rentabilidad dependan de lo que
     * mande el navegador (ver database-design.md §8, CotizacionController y
     * App\Services\Calculo\MotorMargenService).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('cliente', 'id')],
            'empleado_id' => ['required', 'integer', Rule::exists('empleado', 'id')],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursal', 'id')],
            'fecha' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'aplicar_iva' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:2000'],

            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['nullable', 'integer', Rule::exists('producto', 'id')],
            'detalles.*.tipo_proyecto_id' => ['nullable', 'integer', Rule::exists('tipo_proyecto', 'id')],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.ancho' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.alto' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.instalacion' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.precio_manual' => ['nullable', Rule::in(['SI', 'NO'])],
            // Solo obligatorio cuando el vendedor fija el precio a mano; si
            // no, lo calcula el motor de margen a partir de los insumos.
            'detalles.*.precio_unitario' => ['required_if:detalles.*.precio_manual,SI', 'nullable', 'numeric', 'min:0'],

            'detalles.*.items' => ['nullable', 'array'],
            'detalles.*.items.*.material_id' => ['nullable', 'integer', Rule::exists('material', 'id')],
            'detalles.*.items.*.tipo' => ['required', Rule::in(CotizacionDetalleItem::TIPOS)],
            'detalles.*.items.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.items.*.unidad' => ['nullable', 'string', 'max:50'],
            'detalles.*.items.*.cantidad' => ['required', 'numeric', 'min:0'],
            'detalles.*.items.*.costo_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'detalles.*.producto_id' => 'producto',
            'detalles.*.tipo_proyecto_id' => 'tipo de proyecto',
            'detalles.*.descripcion' => 'descripción',
            'detalles.*.cantidad' => 'cantidad',
            'detalles.*.precio_unitario' => 'precio unitario',
            'detalles.*.instalacion' => 'instalación',
            'detalles.*.items.*.descripcion' => 'descripción del insumo',
            'detalles.*.items.*.cantidad' => 'cantidad del insumo',
            'detalles.*.items.*.costo_unitario' => 'costo unitario del insumo',
        ];
    }
}
