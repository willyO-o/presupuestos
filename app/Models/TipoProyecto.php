<?php

namespace App\Models;

use Database\Factories\TipoProyectoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nombre',
    'descripcion',
    'factor_complejidad',
    'margen_minimo',
    'orden',
    'estado',
])]

class TipoProyecto extends Model
{
    /** @use HasFactory<TipoProyectoFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'tipo_proyecto';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor_complejidad' => 'decimal:2',
            // Fracción, no porcentaje: 0.4500 = 45 %.
            'margen_minimo' => 'decimal:4',
            'orden' => 'integer',
        ];
    }

    /**
     * Líneas de cotización cotizadas con este nivel de complejidad. La FK es
     * `restrictOnDelete`: un tipo con historial no se puede borrar.
     */
    public function cotizacionDetalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    /**
     * `margen_minimo` expresado en porcentaje, para mostrarlo en pantalla
     * sin repetir el ×100 en cada componente.
     */
    public function margenPorcentaje(): float
    {
        return round((float) $this->margen_minimo * 100, 2);
    }

    /**
     * Filtra por coincidencia parcial en nombre o descripción. Sin término,
     * no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('nombre', 'like', "%{$term}%")
                    ->orWhere('descripcion', 'like', "%{$term}%");
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

    /**
     * Orden de presentación del catálogo: el campo `orden` que administra el
     * usuario y, a igualdad, el nombre.
     */
    #[Scope]
    protected function ordenado(Builder $query): void
    {
        $query->orderBy('orden')->orderBy('nombre');
    }
}
