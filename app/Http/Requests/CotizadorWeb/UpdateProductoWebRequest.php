<?php

namespace App\Http\Requests\CotizadorWeb;

use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Cómo se publica UN producto en el cotizador web: si se ofrece y de dónde
 * sale su precio (ver Producto::MODOS_PRECIO_WEB).
 */
class UpdateProductoWebRequest extends FormRequest
{
    /** Variables de fórmula que el formulario público no pide. */
    private const VARIABLES_NO_PEDIDAS = ['profundo', 'niveles'];

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
            'cotizable_web' => ['required', Rule::in(['SI', 'NO'])],
            'modo_precio_web' => ['required', Rule::in(Producto::MODOS_PRECIO_WEB)],
            'precio_web' => ['nullable', 'required_if:modo_precio_web,PRECIO_FIJO', 'numeric', 'gt:0', 'max:1000000'],
            // Los rangos solo cuentan en su modo: el modal siempre manda sus
            // filas (para no perderlas al cambiar de modo) y en los otros
            // modos pueden estar a medio llenar.
            'rangos' => ['exclude_unless:modo_precio_web,RANGOS', 'required', 'array', 'min:1', 'max:10'],
            'rangos.*.area_hasta' => ['exclude_unless:modo_precio_web,RANGOS', 'nullable', 'numeric', 'gt:0', 'max:10000'],
            'rangos.*.precio_m2' => ['exclude_unless:modo_precio_web,RANGOS', 'required', 'numeric', 'gt:0', 'max:1000000'],
        ];
    }

    /**
     * Lo que depende del producto y de los rangos entre sí:
     *
     * 1. RANGOS solo para productos por m² que piden medidas: el rango se
     *    elige por el área.
     * 2. Un precio fijo por m² o metro lineal necesita las medidas.
     * 3. Los topes de los rangos van de menor a mayor y solo el último puede
     *    quedar sin tope.
     * 4. Publicar en modo MATERIALES exige receta o costo base, y una receta
     *    que no use medidas que el visitante no puede escribir.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Producto $producto */
            $producto = $this->route('producto');
            $modo = $this->input('modo_precio_web');

            if ($modo === 'RANGOS' && ($producto->unidad_medida !== 'M2' || $producto->requiere_medidas !== 'SI')) {
                $validator->errors()->add('modo_precio_web', 'Los rangos de área solo sirven para productos que se cobran por m² y piden medidas.');
            }

            if ($modo === 'PRECIO_FIJO' && $producto->unidad_medida !== 'UNIDAD' && $producto->requiere_medidas !== 'SI') {
                $validator->errors()->add('modo_precio_web', 'Un precio por m² o metro lineal necesita que el producto pida medidas.');
            }

            if ($modo === 'RANGOS') {
                $this->validarRangos($validator);
            }

            if ($modo === 'MATERIALES' && $this->input('cotizable_web') === 'SI') {
                $this->validarMateriales($validator, $producto);
            }
        });
    }

    private function validarRangos(Validator $validator): void
    {
        $rangos = array_values($this->input('rangos', []));
        $anterior = 0.0;

        foreach ($rangos as $indice => $rango) {
            $tope = $rango['area_hasta'] ?? null;
            $esUltimo = $indice === count($rangos) - 1;

            if ($tope === null || $tope === '') {
                if (! $esUltimo) {
                    $validator->errors()->add("rangos.{$indice}.area_hasta", 'Solo el último rango puede quedar sin tope.');
                }

                continue;
            }

            if ((float) $tope <= $anterior) {
                $validator->errors()->add("rangos.{$indice}.area_hasta", 'Cada tope tiene que ser mayor que el del rango anterior.');
            }

            $anterior = (float) $tope;
        }
    }

    private function validarMateriales(Validator $validator, Producto $producto): void
    {
        $producto->loadMissing('productoMateriales.formula');

        if ($producto->productoMateriales->isEmpty() && (float) $producto->precio_base <= 0) {
            $validator->errors()->add('modo_precio_web', 'Este producto no tiene materiales ni costo base: elige precio fijo o rangos, o carga sus materiales.');

            return;
        }

        if ((float) $producto->precio_base > 0) {
            return;
        }

        foreach ($producto->productoMateriales as $linea) {
            $expresion = (string) $linea->formula?->expresion;

            foreach (self::VARIABLES_NO_PEDIDAS as $variable) {
                if (preg_match('/\b'.$variable.'\b/', $expresion)) {
                    $validator->errors()->add('modo_precio_web', "Sus materiales usan «{$variable}», que el cotizador web no le pide al visitante: elige precio fijo o rangos.");

                    return;
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cotizable_web' => 'publicación',
            'modo_precio_web' => 'modo de precio',
            'precio_web' => 'precio',
            'rangos' => 'rangos de área',
            'rangos.*.area_hasta' => 'tope del rango',
            'rangos.*.precio_m2' => 'precio por m²',
        ];
    }
}
