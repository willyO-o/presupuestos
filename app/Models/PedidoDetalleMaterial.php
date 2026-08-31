<?php

namespace App\Models;

use Database\Factories\PedidoDetalleMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pedido_detalle_id',
    'material_id',
    'cantidad_usada',
    'costo_real',
])]

class PedidoDetalleMaterial extends Model
{
    /** @use HasFactory<PedidoDetalleMaterialFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'pedido_detalle_material';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_usada' => 'decimal:2',
            'costo_real' => 'decimal:2',
        ];
    }

    public function pedidoDetalle(): BelongsTo
    {
        return $this->belongsTo(PedidoDetalle::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
