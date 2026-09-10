<?php

namespace App\Http\Requests\Cotizacion;

use App\Http\Requests\Concerns\ValidaAlcanceSucursal;
use App\Models\CotizacionDetalleItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCotizacionRequest extends FormRequest
{
    use ValidaAlcanceSucursal;

    /**
     * La ruta ya exige el permiso via middleware `can:cotizaciones.editar`;
     * se repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware. Que la cotización siga siendo
     * editable (estado PENDIENTE) lo verifica el controlador.
     */
    public function authorize(): bool
    {
        return $this->user()->can('cotizaciones.editar');
    }

    /**
     * Mismas reglas que al crear: el detalle completo (con sus insumos) se
     * reemplaza en cada guardado y los montos los recalcula el motor de
     * margen en el controlador.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('cliente', 'id')],
            'empleado_id' => ['required', 'integer', Rule::exists('empleado', 'id')],
            // Idéntica a StoreCotizacionRequest: sin esto se podría MOVER una
            // cotización propia a otra sucursal y perderla de vista.
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursal', 'id'), $this->sucursalAdministrada()],
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
