<?php

namespace App\Models;

use Database\Factories\CotizacionDetalleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cotizacion_detalle_id',
    'material_id',
    'tipo',
    'descripcion',
    'unidad',
    'cantidad',
    'costo_unitario',
    'subtotal',
])]

class CotizacionDetalleItem extends Model
{
    /** @use HasFactory<CotizacionDetalleItemFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'cotizacion_detalle_item';

    /**
     * Columna "Tipo" de la hoja de costos. MANO_OBRA y SERVICIO no salen del
     * inventario (no llevan `material_id`).
     */
    public const TIPOS = ['MATERIAL', 'MANO_OBRA', 'IMPRESION', 'SERVICIO', 'OTRO'];

    /**
     * Etiqueta legible de cada tipo, para selects y documentos.
     */
    public const ETIQUETAS_TIPO = [
        'MATERIAL' => 'Material',
        'MANO_OBRA' => 'Mano de obra',
        'IMPRESION' => 'Impresión',
        'SERVICIO' => 'Servicio de terceros',
        'OTRO' => 'Otro',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
            'subtotal' => 'decimal:4',
        ];
    }

    public function cotizacionDetalle(): BelongsTo
    {
        return $this->belongsTo(CotizacionDetalle::class);
    }

    /**
     * Material del inventario del que salió el insumo, o `null` si es mano de
     * obra, un servicio de terceros o un ítem escrito a mano (nullOnDelete).
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
