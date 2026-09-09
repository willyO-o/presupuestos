<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'categoria_producto_id',
    'nombre',
    'descripcion',
    'unidad_medida',
    'precio_base',
    'requiere_medidas',
    'cotizable_web',
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

    /**
     * Subconjunto del vocabulario canónico (Material::UNIDADES_MEDIDA) que
     * tiene sentido para vender: un producto no se cotiza por litro.
     * `CosteoProductoService::driver()` depende de estos valores.
     */
    public const UNIDADES_MEDIDA = ['M2', 'METRO_LINEAL', 'UNIDAD'];

    public function categoriaProducto(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class);
    }

    /**
     * Receta de costo (BOM): materiales que consume este producto, cada
     * uno con un factor fijo o una fórmula dinámica (ver
     * App\Models\ProductoMaterial::esDinamica()).
     */
    public function productoMateriales(): HasMany
    {
        return $this->hasMany(ProductoMaterial::class);
    }

    /**
     * Productos ofrecidos en el cotizador público (`/cotizador`): lista
     * blanca explícita (`cotizable_web`), activos y —lo importante— CON
     * receta cargada. Sin BOM el costo daría 0 y el motor devolvería un
     * precio de cero: publicar eso sería peor que no publicar nada.
     *
     * Ver App\Services\Cotizador\CotizadorPublicoService.
     */
    #[Scope]
    protected function cotizableWeb(Builder $query): void
    {
        $query->where('cotizable_web', 'SI')
            ->where('estado', 'ACTIVO')
            ->whereHas('productoMateriales');
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
