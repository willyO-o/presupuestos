<?php

namespace App\Http\Requests\Cotizador;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

/**
 * Estimación que el visitante decide guardar para retomarla con un vendedor.
 *
 * Hereda el detalle y todas sus defensas de `CalcularEstimacionRequest` y
 * agrega el contacto más dos trampas para bots, que son las que evitan que la
 * bandeja de ventas se llene de basura:
 *
 * 1. **Honeypot** (`sitio_web`): un campo que la persona no ve y el bot sí
 *    completa. Si viene lleno, es spam.
 * 2. **Tiempo mínimo**: la marca de tiempo vive en la SESIÓN
 *    (`cotizador.abierto_en`, la escribe CotizadorPublicoController::index),
 *    no en un input oculto — un campo del formulario lo falsifica cualquiera
 *    con las herramientas del navegador; la sesión, no.
 *
 * Ninguna de las dos devuelve un mensaje que explique qué la disparó: decirle
 * al bot cuál de las dos lo delató es enseñarle a esquivarla.
 */
class GuardarEstimacionRequest extends CalcularEstimacionRequest
{
    /**
     * Clave de sesión con el momento en que se abrió el formulario.
     */
    public const SESION_ABIERTO_EN = 'cotizador.abierto_en';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            'nombre' => ['required', 'string', 'max:120'],
            'empresa' => ['nullable', 'string', 'max:150'],
            // Se pide teléfono y no correo porque el seguimiento real de la
            // empresa es por WhatsApp; el correo queda opcional.
            'telefono' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\s-]{7,30}$/'],
            'email' => ['nullable', 'email:filter', 'max:150'],
            'mensaje' => ['nullable', 'string', 'max:1000'],

            // Honeypot: tiene que llegar vacío.
            'sitio_web' => ['nullable', 'prohibited'],
        ];
    }

    /**
     * Agrega el control de tiempo mínimo a las validaciones heredadas.
     */
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            if ($this->esDemasiadoRapido()) {
                $validator->errors()->add(
                    'nombre',
                    'No pudimos procesar el envío. Vuelve a intentarlo desde el formulario.',
                );
            }
        });
    }

    /**
     * true si el formulario se envió antes del tiempo mínimo, o si nunca se
     * abrió (no hay marca en sesión): un POST directo, sin pasar por la
     * página, es exactamente el comportamiento de un script.
     */
    private function esDemasiadoRapido(): bool
    {
        $abiertoEn = $this->session()->get(self::SESION_ABIERTO_EN);

        if (! is_int($abiertoEn)) {
            return true;
        }

        return (time() - $abiertoEn) < (int) config('cotizador.segundos_minimos', 4);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'nombre' => 'nombre',
            'telefono' => 'teléfono',
            'email' => 'correo',
            'mensaje' => 'mensaje',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'nombre.required' => 'Necesitamos tu nombre para saber con quién hablamos.',
            'telefono.required' => 'Déjanos un teléfono o WhatsApp para responderte.',
            'telefono.regex' => 'Escribe el teléfono solo con números, espacios o los signos + ( ) -.',
            'email.email' => 'Ese correo no parece válido.',
            'sitio_web.prohibited' => 'No pudimos procesar el envío. Vuelve a intentarlo desde el formulario.',
        ];
    }
}
