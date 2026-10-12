<?php

namespace App\Models;

use Database\Factories\CategoriaProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'familia_producto_id',
    'nombre',
    'estado',
])]

class CategoriaProducto extends Model
{
    /** @use HasFactory<CategoriaProductoFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'categoria_producto';

    /**
     * Familia comercial a la que pertenece (agrupación del BI). Nullable: una
     * categoría nueva queda "Sin familia" hasta que alguien la clasifique.
     */
    public function familia(): BelongsTo
    {
        return $this->belongsTo(FamiliaProducto::class, 'familia_producto_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    /**
     * Filtra por coincidencia parcial en nombre. Sin término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query) => $query->where('nombre', 'like', "%{$term}%"));
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
