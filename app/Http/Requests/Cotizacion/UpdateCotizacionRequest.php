<?php

namespace App\Http\Requests\Cotizacion;

use App\Http\Requests\Concerns\ValidaAlcanceSucursal;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
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
            // Si la instalación entra en la base del IVA (solo cuenta con
            // `aplicar_iva`). Sin el campo, manda config('cotizacion.instalacion_con_iva').
            'instalacion_con_iva' => ['nullable', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            // Condiciones de venta de esta cotización (se imprimen en el PDF).
            'forma_pago' => ['nullable', 'string', 'max:255'],
            'tiempo_entrega' => ['nullable', 'string', 'max:255'],
            'lugar_entrega' => ['nullable', 'string', 'max:255'],

            'detalles' => ['required', 'array', 'min:1'],
            // Lonas y muebles no se mezclan: cada cotización solo acepta
            // productos de su tipo, y en una de muebles la plantilla es
            // obligatoria (de ahí salen los materiales predefinidos).
            'detalles.*.producto_id' => [
                $this->tipoCotizacion() === 'MUEBLE' ? 'required' : 'nullable',
                'integer',
                Rule::exists('producto', 'id')->where('modo_cotizacion', $this->tipoCotizacion()),
            ],
            'detalles.*.tipo_proyecto_id' => ['nullable', 'integer', Rule::exists('tipo_proyecto', 'id')],
            // Texto largo: la descripción de un exhibidor ocupa varios
            // párrafos (material, acabado, impresión) y el PDF respeta los
            // saltos de línea.
            'detalles.*.descripcion' => ['required', 'string', 'max:2000'],
            'detalles.*.ancho' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.alto' => ['nullable', 'numeric', 'min:0'],
            // Muebles y exhibidores: ancho × alto × profundidad y niveles.
            'detalles.*.profundidad' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.niveles' => ['nullable', 'integer', 'min:1', 'max:50'],
            // Imagen referencial de la línea: un archivo nuevo (se convierte
            // a JPG, ver App\Services\Imagen\ConvierteImagenAJpgService),
            // "usar imagen del producto" (la copia del catálogo), la que la
            // línea ya tenía (`imagen_actual`, para no perderla al reemplazar
            // el detalle entero), o ninguna. El controlador decide cuál gana
            // — ver CotizacionController::resolverImagenLinea.
            'detalles.*.imagen' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,bmp', 'max:4096'],
            'detalles.*.usar_imagen_producto' => ['nullable', 'boolean'],
            'detalles.*.imagen_actual' => ['nullable', 'string'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.instalacion' => ['nullable', 'numeric', 'min:0'],
            // Desglose opcional de la instalación. Si viene, el monto lo
            // calcula el servidor y se ignora `instalacion`.
            'detalles.*.instalacion_detalle' => ['nullable', 'array'],
            'detalles.*.instalacion_detalle.personas' => ['required_with:detalles.*.instalacion_detalle', 'integer', 'min:1', 'max:100'],
            'detalles.*.instalacion_detalle.horas' => ['required_with:detalles.*.instalacion_detalle', 'numeric', 'min:0', 'max:1000'],
            'detalles.*.instalacion_detalle.costo_hora' => ['required_with:detalles.*.instalacion_detalle', 'numeric', 'min:0', 'max:10000'],
            'detalles.*.instalacion_detalle.nocturna' => ['nullable', 'boolean'],
            'detalles.*.instalacion_detalle.recargo_nocturno' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'detalles.*.instalacion_detalle.transporte' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'detalles.*.precio_manual' => ['nullable', Rule::in(['SI', 'NO'])],
            'detalles.*.modalidad' => ['nullable', Rule::in(CotizacionDetalle::MODALIDADES)],
            // Solo obligatorio cuando el vendedor fija el precio a mano; si
            // no, lo calcula el motor de margen a partir de los insumos.
            'detalles.*.precio_unitario' => ['required_if:detalles.*.precio_manual,SI', 'required_if:detalles.*.modalidad,PRECIO_MANUAL,SUPERFICIE', 'nullable', 'numeric', 'min:0'],

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
            'detalles.*.profundidad' => 'profundidad',
            'detalles.*.niveles' => 'niveles',
            'detalles.*.precio_unitario' => 'precio unitario',
            'detalles.*.instalacion' => 'instalación',
            'detalles.*.instalacion_detalle.personas' => 'personas',
            'detalles.*.instalacion_detalle.horas' => 'horas',
            'detalles.*.instalacion_detalle.costo_hora' => 'costo por hora',
            'detalles.*.instalacion_detalle.recargo_nocturno' => 'recargo nocturno',
            'detalles.*.instalacion_detalle.transporte' => 'transporte',
            'detalles.*.items.*.descripcion' => 'descripción del insumo',
            'detalles.*.items.*.cantidad' => 'cantidad del insumo',
            'detalles.*.items.*.costo_unitario' => 'costo unitario del insumo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'detalles.*.producto_id.exists' => $this->tipoCotizacion() === 'MUEBLE'
                ? 'Elige una plantilla de mueble: este producto se cotiza en el formulario general.'
                : 'Este producto es una plantilla de mueble: se cotiza en "Cotizar mueble".',
            'detalles.*.producto_id.required' => 'Elige qué mueble vas a cotizar.',
        ];
    }

    /**
     * Tipo de la cotización que se edita: no cambia al editarla (una de
     * muebles sigue siendo de muebles), así que sale de la ruta y no del
     * formulario.
     */
    private function tipoCotizacion(): string
    {
        $cotizacion = $this->route('cotizacion');

        return $cotizacion instanceof Cotizacion ? $cotizacion->tipo : 'GENERAL';
    }
}
