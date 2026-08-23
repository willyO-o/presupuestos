<?php

namespace App\Models;

use Database\Factories\EmpleadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'sucursal_id',
    'area_id',
    'nombres',
    'paterno',
    'materno',
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
     * Se agrega al array/JSON para no repetir la concatenación de
     * nombres/paterno/materno en cada pantalla (listado, perfil).
     *
     * @var list<string>
     */
    protected $appends = ['nombre_completo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Nombres + apellido paterno + apellido materno, sin espacios dobles
     * cuando paterno/materno vienen vacíos.
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: fn () => collect([$this->nombres, $this->paterno, $this->materno])
                ->filter()
                ->implode(' '),
        );
    }

    /**
     * Filtra por coincidencia parcial en nombres, apellidos, CI o cargo. Sin
     * término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('nombres', 'like', "%{$term}%")
                    ->orWhere('paterno', 'like', "%{$term}%")
                    ->orWhere('materno', 'like', "%{$term}%")
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
