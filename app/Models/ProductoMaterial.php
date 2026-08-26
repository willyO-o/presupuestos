<?php

namespace App\Models;

use Database\Factories\ProductoMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'producto_id',
    'material_id',
    'formula_id',
    'cantidad_por_unidad',
])]

class ProductoMaterial extends Model
{
    /** @use HasFactory<ProductoMaterialFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'producto_material';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_por_unidad' => 'decimal:4',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }

    /**
     * true si la cantidad de esta línea se calcula dinámicamente con una
     * fórmula (App\Services\Calculo\FormulaCalculator) en vez del factor
     * fijo `cantidad_por_unidad`.
     */
    public function esDinamica(): bool
    {
        return $this->formula_id !== null;
    }
}
