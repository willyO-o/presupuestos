<?php

namespace App\Models;

use Database\Factories\CompraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'proveedor_id',
    'empleado_id',
    'numero_factura',
    'fecha',
    'total',
    'estado',
])]

class Compra extends Model
{
    /** @use HasFactory<CompraFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'compra';

    /**
     * PENDIENTE al registrarla; PAGADA cuando el contador la aprueba —
     * recién ahí impacta stock/precio del material (ver
     * CompraController::aprobar); ANULADA si se descarta.
     */
    public const ESTADOS = ['PENDIENTE', 'PAGADA', 'ANULADA'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    /**
     * Líneas de la compra (material, cantidad, precio). Pertenecen por
     * completo a la compra (cascadeOnDelete).
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class);
    }

    /**
     * true si todavía se puede editar/borrar: solo mientras está PENDIENTE.
     * Una vez PAGADA impactó el inventario y queda como documento histórico.
     */
    public function esEditable(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    /**
     * Filtra por coincidencia parcial en número de factura o nombre del
     * proveedor. Sin término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('numero_factura', 'like', "%{$term}%")
                    ->orWhereHas('proveedor', fn (Builder $query) => $query->where('nombre', 'like', "%{$term}%"));
            });
        });
    }

    /**
     * Filtra por estado exacto (ver ESTADOS). Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function estado(Builder $query, ?string $estado): void
    {
        $query->when($estado, fn (Builder $query) => $query->where('estado', $estado));
    }

    /**
     * Filtra por proveedor exacto. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function proveedorId(Builder $query, ?string $proveedorId): void
    {
        $query->when($proveedorId, fn (Builder $query) => $query->where('proveedor_id', $proveedorId));
    }
}
