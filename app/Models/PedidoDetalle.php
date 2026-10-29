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
    public const ESTADOS = ['DISENO', 'ELABORACION', 'ACABADO', 'CONTROL_CALIDAD', 'ENTREGADO'];

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

    /**
     * Lo que dice la cotización para este ítem, si producción lo ajustó
     * ("Ajustar medidas reales"): descripción, medidas o cantidad distintas de
     * la línea de origen. `null` si no hubo ajuste o la línea ya no existe.
     *
     * El ítem guarda lo que se fabrica; la cotización, lo que se prometió
     * (ver `.ai/rules/controllers.md`). La pantalla del pedido y la orden de
     * trabajo muestran las dos cosas con esto.
     *
     * @return array{descripcion: string, ancho: float|null, alto: float|null, cantidad: float}|null
     */
    public function cotizadoSiSeAjusto(): ?array
    {
        $origen = $this->cotizacionDetalle;

        if ($origen === null) {
            return null;
        }

        $comoDato = fn (Model $linea): array => [
            'descripcion' => (string) $linea->descripcion,
            'ancho' => $linea->ancho !== null ? (float) $linea->ancho : null,
            'alto' => $linea->alto !== null ? (float) $linea->alto : null,
            'cantidad' => (float) $linea->cantidad,
        ];

        $cotizado = $comoDato($origen);

        return $cotizado === $comoDato($this) ? null : $cotizado;
    }
}
