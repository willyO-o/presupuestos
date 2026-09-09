<?php

namespace App\Http\Requests\Cotizador;

use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Detalle de una estimación del cotizador público.
 *
 * Es la puerta de entrada de un endpoint SIN AUTENTICACIÓN, así que todo lo
 * que no esté declarado acá no existe:
 *
 * - Los productos se validan contra la lista blanca (el scope `cotizableWeb`
 *   de App\Models\Producto), no contra la tabla entera: un
 *   `Rule::exists('producto', 'id')` a secas dejaría cotizar cualquier fila
 *   del catálogo interno probando ids.
 * - Los topes de `config('cotizador.limites')` acotan el trabajo por
 *   petición. Cada línea recorre el BOM y evalúa fórmulas; sin techo, una
 *   petición con miles de líneas es un ataque de CPU gratis.
 * - No se acepta NINGÚN precio del navegador. Lo calcula
 *   App\Services\Cotizador\CotizadorPublicoService en el servidor.
 *
 * `GuardarEstimacionRequest` hereda estas reglas y agrega el contacto.
 */
class CalcularEstimacionRequest extends FormRequest
{
    /**
     * Ruta pública: cualquiera puede pedir una estimación. Lo que la protege
     * no es la autorización sino el rate limiter (`cotizador-calcular`), la
     * lista blanca de productos y los topes de tamaño.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $limites = config('cotizador.limites');

        return [
            'lineas' => ['required', 'array', 'min:1', 'max:'.$limites['lineas']],
            // Que el id exista se comprueba acá; que además esté ofrecido en
            // la web lo decide `withValidator()` con el scope `cotizableWeb`,
            // para no reescribir la lista blanca en dos sitios.
            'lineas.*.producto_id' => ['required', 'integer', Rule::exists('producto', 'id')],
            // En METROS, como todo el resto del sistema (`cotizacion_detalle`
            // guarda ancho/alto en metros y `area_m2` es su producto).
            'lineas.*.ancho' => ['nullable', 'numeric', 'gt:0', 'max:'.$limites['dimension']],
            'lineas.*.alto' => ['nullable', 'numeric', 'gt:0', 'max:'.$limites['dimension']],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.$limites['cantidad']],
        ];
    }

    /**
     * Dos comprobaciones que las `rules()` no pueden expresar:
     *
     * 1. Que el producto esté realmente ofrecido en la web (scope
     *    `cotizableWeb`: marcado, activo y CON receta cargada). Sin esto, un
     *    producto marcado al que nadie le cargó el BOM pasaría la validación
     *    y reventaría después, dentro del servicio.
     * 2. Que traiga medidas si su ficha las exige. Sin esto llegaría sin
     *    ancho/alto hasta `CosteoProductoService::driver()`, que lanza una
     *    excepción con un mensaje escrito para un vendedor ("se cotiza por M2
     *    pero faltan ancho/alto"), no para un visitante.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $productos = $this->productosDelDetalle();

            foreach ($this->input('lineas', []) as $indice => $linea) {
                $producto = $productos->get((int) ($linea['producto_id'] ?? 0));

                if ($producto === null) {
                    $validator->errors()->add(
                        "lineas.{$indice}.producto_id",
                        'Ese trabajo no está disponible en el cotizador en línea. Escríbenos y lo cotizamos contigo.',
                    );

                    continue;
                }

                if ($producto->requiere_medidas !== 'SI') {
                    continue;
                }

                foreach (['ancho', 'alto'] as $medida) {
                    if (($linea[$medida] ?? null) === null || $linea[$medida] === '') {
                        $validator->errors()->add(
                            "lineas.{$indice}.{$medida}",
                            "«{$producto->nombre}» se cotiza por medidas: indica el {$medida} en metros.",
                        );
                    }
                }
            }
        });
    }

    /**
     * Los productos del detalle que SÍ están ofrecidos en la web, en una sola
     * consulta. Un id que no vuelva de acá es uno que no se puede cotizar en
     * línea, sea porque no está marcado, porque está inactivo o porque no
     * tiene receta.
     *
     * @return Collection<int, Producto>
     */
    private function productosDelDetalle(): Collection
    {
        $ids = array_filter(array_column($this->input('lineas', []), 'producto_id'));

        return $ids === []
            ? collect()
            : Producto::query()
                ->cotizableWeb()
                ->whereIn('id', array_unique($ids))
                ->get()
                ->keyBy('id');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lineas' => 'detalle',
            'lineas.*.producto_id' => 'producto',
            'lineas.*.ancho' => 'ancho',
            'lineas.*.alto' => 'alto',
            'lineas.*.cantidad' => 'cantidad',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lineas.required' => 'Agrega al menos un trabajo para estimar.',
            'lineas.max' => 'Puedes estimar hasta :max trabajos por vez. Para algo más grande, escríbenos y lo vemos contigo.',
            'lineas.*.producto_id.required' => 'Elige qué quieres cotizar.',
            'lineas.*.producto_id.exists' => 'Ese trabajo no está disponible en el cotizador en línea.',
            'lineas.*.ancho.max' => 'El ancho máximo del cotizador en línea es de :max metros.',
            'lineas.*.alto.max' => 'El alto máximo del cotizador en línea es de :max metros.',
            'lineas.*.cantidad.max' => 'Para más de :max unidades te hacemos un precio por volumen: escríbenos.',
        ];
    }
}
