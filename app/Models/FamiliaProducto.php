<?php

namespace App\Models;

use Database\Factories\FamiliaProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nombre',
    'descripcion',
    'orden',
    'estado',
])]

/**
 * Familia comercial de productos: agrupa categorías parecidas (lonas y
 * gigantografías, letreros y letras 3D, muebles y exhibidores...).
 *
 * Existe para el BI: una categoría suelta vende pocas veces por mes y su
 * tendencia es ruido; la familia junta volumen suficiente para comparar
 * periodos, detectar caídas y, cuando haya historia, pronosticar.
 */
class FamiliaProducto extends Model
{
    /** @use HasFactory<FamiliaProductoFactory> */
    use HasFactory;

    /**
     * Rótulo con el que el BI agrupa las categorías todavía sin familia.
     */
    public const SIN_FAMILIA = 'Sin familia';

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'familia_producto';

    public function categorias(): HasMany
    {
        return $this->hasMany(CategoriaProducto::class);
    }

    /**
     * Orden de presentación: el campo `orden` y, a igualdad, el nombre.
     */
    #[Scope]
    protected function ordenado(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('nombre');
    }
}
