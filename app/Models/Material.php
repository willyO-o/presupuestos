<?php

namespace App\Models;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'categoria_material_id',
    'nombre',
    'presentacion',
    'unidad_medida',
    'precio_presentacion',
    'precio_unitario',
    'stock_actual',
    'stock_minimo',
    'redondeo_compra',
    'estado',
])]

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'material';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'redondeo_compra' => 'decimal:4',
        ];
    }

    public function categoriaMaterial(): BelongsTo
    {
        return $this->belongsTo(CategoriaMaterial::class);
    }

    /**
     * Líneas de BOM (producto_material) que consumen este material.
     */
    public function productoMateriales(): HasMany
    {
        return $this->hasMany(ProductoMaterial::class);
    }

    /**
     * Filtra por coincidencia parcial en nombre o presentación. Sin
     * término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('nombre', 'like', "%{$term}%")
                    ->orWhere('presentacion', 'like', "%{$term}%");
            });
        });
    }

    /**
     * Filtra por categoría de material exacta. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function categoria(Builder $query, ?string $categoriaMaterialId): void
    {
        $query->when($categoriaMaterialId, fn (Builder $query) => $query->where('categoria_material_id', $categoriaMaterialId));
    }

    /**
     * Filtra por estado exacto (ACTIVO/INACTIVO). Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function estado(Builder $query, ?string $estado): void
    {
        $query->when($estado, fn (Builder $query) => $query->where('estado', $estado));
    }
}
