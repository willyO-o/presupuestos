<?php

namespace App\Models;

use Database\Factories\PedidoDetalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'pedido_id',
    'cotizacion_detalle_id',
    'descripcion',
    'ancho',
    'alto',
    'cantidad',
    'estado_item',
])]

class PedidoDetalle extends Model
{
    /** @use HasFactory<PedidoDetalleFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'pedido_detalle';

    /**
     * Etapa productiva del ítem (database-design.md §9). El estado global
     * del `pedido` se deriva del menos avanzado (ver Pedido::recalcularEstado).
     */
    public const ESTADOS = ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ancho' => 'decimal:2',
            'alto' => 'decimal:2',
            'cantidad' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function cotizacionDetalle(): BelongsTo
    {
        return $this->belongsTo(CotizacionDetalle::class);
    }

    /**
     * Bitácora de avance por etapa/área (rombo "Define el área" del flujo).
     */
    public function seguimientos(): HasMany
    {
        return $this->hasMany(PedidoSeguimiento::class);
    }

    /**
     * Consumo REAL de materiales de este ítem (se compara contra el BOM
     * presupuestado para el análisis de rentabilidad del BI).
     */
    public function materialesUsados(): HasMany
    {
        return $this->hasMany(PedidoDetalleMaterial::class);
    }
}
