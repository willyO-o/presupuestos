<?php

namespace App\Models;

use Database\Factories\HistorialPrecioMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'material_id',
    'precio_presentacion',
    'precio_unitario',
    'vigente_desde',
])]

class HistorialPrecioMaterial extends Model
{
    /** @use HasFactory<HistorialPrecioMaterialFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'historial_precio_material';

    /**
     * Log de solo escritura: la migración solo trae `created_at`
     * (`useCurrent()`), no `updated_at`.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_presentacion' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'vigente_desde' => 'date',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
