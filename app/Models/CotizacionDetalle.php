<?php

namespace App\Models;

use Database\Factories\CotizacionDetalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cotizacion_id',
    'producto_id',
    'descripcion',
    'ancho',
    'alto',
    'area_m2',
    'cantidad',
    'precio_unitario',
    'subtotal',
])]

class CotizacionDetalle extends Model
{
    /** @use HasFactory<CotizacionDetalleFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'cotizacion_detalle';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ancho' => 'decimal:2',
            'alto' => 'decimal:2',
            'area_m2' => 'decimal:2',
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Producto del catálogo, o `null` si es un ítem personalizado no
     * catalogado (nullOnDelete).
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
