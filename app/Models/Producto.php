<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'categoria_producto_id',
    'nombre',
    'descripcion',
    'unidad_medida',
    'precio_base',
    'requiere_medidas',
    'estado',
])]

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'producto';

    public function categoriaProducto(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class);
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
     * Filtra por categoría de producto exacta. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function categoria(Builder $query, ?string $categoriaProductoId): void
    {
        $query->when($categoriaProductoId, fn (Builder $query) => $query->where('categoria_producto_id', $categoriaProductoId));
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
