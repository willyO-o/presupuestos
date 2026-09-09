<?php

namespace App\Models;

use Database\Factories\CotizacionDetalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cotizacion_id',
    'producto_id',
    'tipo_proyecto_id',
    'descripcion',
    'ancho',
    'alto',
    'area_m2',
    'cantidad',
    'costo_base',
    'factor_complejidad',
    'margen_aplicado',
    'costo_ajustado',
    'precio_unitario',
    'precio_manual',
    'instalacion',
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
            'costo_base' => 'decimal:2',
            'factor_complejidad' => 'decimal:2',
            'margen_aplicado' => 'decimal:4',
            'costo_ajustado' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'instalacion' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * Nivel de complejidad con el que se cotizó la línea. `null` en las
     * líneas anteriores al motor de margen y en los ítems sueltos con precio
     * escrito a mano — el factor/margen realmente aplicados quedan igual
     * guardados en la propia línea.
     */
    public function tipoProyecto(): BelongsTo
    {
        return $this->belongsTo(TipoProyecto::class);
    }

    /**
     * Insumos que componen el `costo_base` de UNA unidad de esta línea
     * (columnas A-E de la hoja de costos). Pertenecen por completo a la
     * línea (cascadeOnDelete).
     */
    public function items(): HasMany
    {
        return $this->hasMany(CotizacionDetalleItem::class);
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
