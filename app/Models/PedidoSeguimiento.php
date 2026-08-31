<?php

namespace App\Models;

use Database\Factories\PedidoSeguimientoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pedido_detalle_id',
    'area_id',
    'empleado_id',
    'etapa',
    'fecha_inicio',
    'fecha_fin',
    'observaciones',
])]

class PedidoSeguimiento extends Model
{
    /** @use HasFactory<PedidoSeguimientoFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'pedido_seguimiento';

    /**
     * Etapas del flujo (database-design.md §9). Ojo: la última es ENTREGA
     * (no ENTREGADO, que es el `estado_item` de pedido_detalle).
     */
    public const ETAPAS = ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGA'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
        ];
    }

    public function pedidoDetalle(): BelongsTo
    {
        return $this->belongsTo(PedidoDetalle::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}
