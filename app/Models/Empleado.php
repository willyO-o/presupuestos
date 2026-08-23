<?php

namespace App\Models;

use Database\Factories\EmpleadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sucursal_id',
    'area_id',
    'nombre_completo',
    'ci',
    'cargo',
    'telefono',
    'fecha_ingreso',
    'estado',
])]

class Empleado extends Model
{
    /** @use HasFactory<EmpleadoFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'empleado';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Filtra por coincidencia parcial en nombre completo, CI o cargo. Sin
     * término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('nombre_completo', 'like', "%{$term}%")
                    ->orWhere('ci', 'like', "%{$term}%")
                    ->orWhere('cargo', 'like', "%{$term}%");
            });
        });
    }

    /**
     * Filtra por sucursal exacta. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function sucursalId(Builder $query, ?string $sucursalId): void
    {
        $query->when($sucursalId, fn (Builder $query) => $query->where('sucursal_id', $sucursalId));
    }

    /**
     * Filtra por área exacta. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function areaId(Builder $query, ?string $areaId): void
    {
        $query->when($areaId, fn (Builder $query) => $query->where('area_id', $areaId));
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
