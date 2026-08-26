<?php

namespace App\Models;

use Database\Factories\FormulaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nombre',
    'expresion',
    'descripcion',
    'estado',
])]

class Formula extends Model
{
    /** @use HasFactory<FormulaFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'formula';

    /**
     * Líneas de BOM (producto_material) que calculan su cantidad usando
     * esta fórmula en vez de un factor fijo.
     */
    public function productoMateriales(): HasMany
    {
        return $this->hasMany(ProductoMaterial::class);
    }

    /**
     * Filtra por coincidencia parcial en nombre o expresión. Sin término,
     * no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('nombre', 'like', "%{$term}%")
                    ->orWhere('expresion', 'like', "%{$term}%");
            });
        });
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
