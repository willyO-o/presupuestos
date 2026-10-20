<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pedido del PDF de un reporte (financiero o de producción).
 *
 * La pantalla le pasa la fecha a `Carbon::parse()` tal cual; el PDF no: una
 * fecha inventada en la URL tiene que dar un error de validación, no un 500
 * a mitad de armar el documento.
 */
class ReportePdfRequest extends FormRequest
{
    /**
     * Cada ruta exige su par de permisos (ver y exportar) con `can:`; este
     * Form Request lo comparten las dos, así que no repite la autorización.
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
        return [
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'descargar' => ['nullable', 'boolean'],
        ];
    }
}
