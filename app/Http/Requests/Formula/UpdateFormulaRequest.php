<?php

namespace App\Http\Requests\Formula;

use App\Services\Calculo\FormulaCalculator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormulaRequest extends FormRequest
{
    /**
     * La ruta ya exige el permiso via middleware `can:formulas.editar`; se
     * repite aquí porque es el lugar recomendado por Laravel para esta
     * comprobación y protege el Form Request si algún día se usa desde otra
     * ruta que no lleve el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('formulas.editar');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'expresion' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($mensaje = app(FormulaCalculator::class)->mensajeError((string) $value)) {
                        $fail("La expresión no es válida: {$mensaje}");
                    }
                },
            ],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
