<?php

namespace App\Http\Requests\ProductoMaterial;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductoMaterialRequest extends FormRequest
{
    /**
     * La ruta ya exige el permiso via middleware `can:productos.editar`;
     * se repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde
     * otra ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('productos.editar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', Rule::exists('material', 'id')],
            'formula_id' => ['nullable', 'integer', Rule::exists('formula', 'id')],
            'cantidad_por_unidad' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }

    /**
     * Una línea de BOM es estática O dinámica, nunca ambas ni ninguna —
     * no se puede expresar como XOR en las reglas de arriba (ver
     * .ai/rules/migrations.md).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tieneFormula = $this->filled('formula_id');
            $tieneFactorFijo = $this->filled('cantidad_por_unidad');

            if ($tieneFormula === $tieneFactorFijo) {
                $mensaje = $tieneFormula
                    ? 'Elegí una fórmula o un factor fijo, no ambos.'
                    : 'Elegí una fórmula o indicá un factor fijo (cantidad por unidad).';

                $validator->errors()->add('cantidad_por_unidad', $mensaje);
            }
        });
    }
}
