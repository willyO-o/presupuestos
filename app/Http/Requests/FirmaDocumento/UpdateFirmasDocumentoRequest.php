<?php

namespace App\Http\Requests\FirmaDocumento;

use App\Models\FirmaDocumento;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFirmasDocumentoRequest extends FormRequest
{
    /**
     * La ruta ya exige `can:firmas-documento.editar`; se repite aquí por si
     * el Form Request se usa desde otra ruta sin el middleware.
     */
    public function authorize(): bool
    {
        return $this->user()->can('firmas-documento.editar');
    }

    /**
     * Se mandan todas las firmas juntas, indexadas por su clave. Las claves
     * son las de FirmaDocumento::FIRMAS: una clave inventada no se acepta.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reglas = ['firmas' => ['required', 'array:'.implode(',', array_keys(FirmaDocumento::FIRMAS))]];

        foreach (array_keys(FirmaDocumento::FIRMAS) as $clave) {
            $reglas["firmas.{$clave}"] = ['required', 'array'];
            $reglas["firmas.{$clave}.nombre"] = ['required', 'string', 'max:150'];
            $reglas["firmas.{$clave}.cargo"] = ['required', 'string', 'max:100'];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $atributos = [];

        foreach (FirmaDocumento::FIRMAS as $clave => $firma) {
            $atributos["firmas.{$clave}.nombre"] = 'nombre';
            $atributos["firmas.{$clave}.cargo"] = 'cargo';
        }

        return $atributos;
    }

    /**
     * Sin espacios sobrantes: el nombre se imprime tal cual en el PDF.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'firmas' => collect($this->input('firmas', []))
                ->map(fn ($firma) => is_array($firma)
                    ? array_map(fn ($valor) => is_string($valor) ? preg_replace('/\s+/u', ' ', trim($valor)) : $valor, $firma)
                    : $firma)
                ->all(),
        ]);
    }
}
