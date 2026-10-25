<?php

namespace App\Models;

use Database\Factories\FirmaDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Firma de un documento en nombre de la empresa.
 *
 * Las firmas posibles son fijas (FIRMAS): la pantalla no crea ni borra,
 * solo cambia nombre y cargo. Mientras una firma no se haya editado no tiene
 * fila y firma el propietario (`config('sitio.empresa.propietario')`): hoy
 * él firma todo porque todavía no hay responsable de producción.
 */
#[Fillable([
    'clave',
    'nombre',
    'cargo',
])]
class FirmaDocumento extends Model
{
    /** @use HasFactory<FirmaDocumentoFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'firma_documento';

    /**
     * Cada firma: en qué documento va, qué hace quien firma (se imprime
     * debajo del nombre, después del cargo; null = el nombre de la empresa)
     * y una explicación para la pantalla.
     *
     * @var array<string, array{documento: string, funcion: ?string, detalle: string}>
     */
    public const FIRMAS = [
        'cotizacion' => ['documento' => 'Cotización', 'funcion' => null, 'detalle' => 'Firma al pie de la cotización que se envía al cliente.'],
        'orden_trabajo_produccion' => ['documento' => 'Orden de trabajo', 'funcion' => 'Recibe la orden', 'detalle' => 'Responsable de producción: recibe la orden de trabajo.'],
        'orden_trabajo_calidad' => ['documento' => 'Orden de trabajo', 'funcion' => 'Aprueba antes de entregar', 'detalle' => 'Control de calidad: aprueba el trabajo antes de entregarlo.'],
        'nota_entrega' => ['documento' => 'Nota de entrega', 'funcion' => null, 'detalle' => 'Firma por la empresa; al lado firma quien recibe.'],
    ];

    /** Cargo por defecto de quien firma sin haberse configurado. */
    public const CARGO_PROPIETARIO = 'Propietario';

    /**
     * Las firmas vigentes, todas: las editadas y, para el resto, el
     * propietario. Una sola consulta.
     *
     * @return array<string, array{nombre: string, cargo: string, documento: string, funcion: ?string, detalle: string}>
     */
    public static function vigentes(): array
    {
        $guardadas = static::query()->whereIn('clave', array_keys(self::FIRMAS))->get()->keyBy('clave');
        $propietario = (string) (config('sitio.empresa.propietario') ?: config('sitio.empresa.nombre'));

        return collect(self::FIRMAS)
            ->map(fn (array $firma, string $clave): array => [
                'nombre' => $guardadas[$clave]->nombre ?? $propietario,
                'cargo' => $guardadas[$clave]->cargo ?? self::CARGO_PROPIETARIO,
                ...$firma,
            ])
            ->all();
    }
}
