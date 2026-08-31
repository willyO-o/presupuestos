<?php

namespace App\Models;

use Database\Factories\PedidoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'cotizacion_id',
    'numero_pedido',
    'fecha_pedido',
    'fecha_entrega_estimada',
    'fecha_entrega_real',
    'estado',
    'total',
])]

class Pedido extends Model
{
    /** @use HasFactory<PedidoFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'pedido';

    /**
     * Etapas del diagrama de flujo del proyecto (database-design.md §9/§12):
     * DISENO → ELABORACION → ACABADO → ENTREGADO. CANCELADO es terminal.
     */
    public const ESTADOS = ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO', 'CANCELADO'];

    /**
     * Orden de avance de las etapas productivas (sin CANCELADO): el estado
     * global del pedido es el de la etapa MENOS avanzada entre sus ítems.
     */
    public const FLUJO = ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_pedido' => 'date',
            'fecha_entrega_estimada' => 'date',
            'fecha_entrega_real' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Líneas del pedido (copia de cotizacion_detalle al convertir). Cada
     * ítem avanza por sus propias etapas.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    /**
     * Orden de compra formal del cliente (1:1 opcional), como respaldo del
     * pedido (database-design.md §10).
     */
    public function ordenCompra(): HasOne
    {
        return $this->hasOne(OrdenCompraCliente::class);
    }

    /**
     * Notas de entrega emitidas para este pedido (puede haber varias:
     * entregas parciales).
     */
    public function notasEntrega(): HasMany
    {
        return $this->hasMany(NotaEntrega::class);
    }

    /**
     * Cobros registrados contra este pedido.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Suma de lo cobrado hasta ahora.
     */
    public function totalPagado(): float
    {
        return round((float) $this->pagos()->sum('monto'), 2);
    }

    /**
     * Saldo pendiente de cobro (nunca negativo).
     */
    public function saldo(): float
    {
        return round(max((float) $this->total - $this->totalPagado(), 0), 2);
    }

    /**
     * Estado de cobranza del pedido: PENDIENTE (sin pagos), PARCIAL (algo
     * cobrado pero con saldo) o PAGADO (saldo cero).
     */
    public function estadoPago(): string
    {
        $pagado = $this->totalPagado();

        return match (true) {
            $pagado <= 0.0 => 'PENDIENTE',
            $this->saldo() <= 0.0 => 'PAGADO',
            default => 'PARCIAL',
        };
    }

    public function esCancelable(): bool
    {
        return ! in_array($this->estado, ['ENTREGADO', 'CANCELADO'], true);
    }

    /**
     * Recalcula el `estado` global a partir del `estado_item` menos
     * avanzado de sus líneas y persiste el cambio (y `fecha_entrega_real`
     * cuando todo queda ENTREGADO). No toca pedidos CANCELADOS.
     */
    public function recalcularEstado(): void
    {
        if ($this->estado === 'CANCELADO') {
            return;
        }

        $estados = $this->detalles()->pluck('estado_item');

        if ($estados->isEmpty()) {
            return;
        }

        $menosAvanzado = collect(self::FLUJO)
            ->first(fn (string $etapa) => $estados->contains($etapa)) ?? 'ENTREGADO';

        $this->estado = $menosAvanzado;
        $this->fecha_entrega_real = $menosAvanzado === 'ENTREGADO'
            ? ($this->fecha_entrega_real ?? now()->toDateString())
            : null;
        $this->save();
    }

    /**
     * Filtra por coincidencia parcial en número de pedido o razón social
     * del cliente. Sin término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('numero_pedido', 'like', "%{$term}%")
                    ->orWhereHas('cotizacion.cliente', fn (Builder $q) => $q->where('razon_social', 'like', "%{$term}%"));
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
     * Filtra por la sucursal de la cotización de origen.
     */
    #[Scope]
    protected function sucursalId(Builder $query, ?string $sucursalId): void
    {
        $query->when(
            $sucursalId,
            fn (Builder $query) => $query->whereHas('cotizacion', fn (Builder $q) => $q->where('sucursal_id', $sucursalId)),
        );
    }

    /**
     * Filtra por el cliente de la cotización de origen.
     */
    #[Scope]
    protected function clienteId(Builder $query, ?string $clienteId): void
    {
        $query->when(
            $clienteId,
            fn (Builder $query) => $query->whereHas('cotizacion', fn (Builder $q) => $q->where('cliente_id', $clienteId)),
        );
    }

    /**
     * Restringe a los pedidos que el usuario puede ver: todos si tiene
     * `pedidos.ver_todas_sucursales` (o es super-admin), si no, solo los de
     * la sucursal de su ficha de empleado. Un usuario sin ficha ni ese
     * permiso no ve ninguno.
     */
    #[Scope]
    protected function visiblePara(Builder $query, User $user): void
    {
        if ($user->hasRole('super-admin') || $user->can('pedidos.ver_todas_sucursales')) {
            return;
        }

        $sucursalId = $user->empleado?->sucursal_id;

        $query->when(
            $sucursalId,
            fn (Builder $query) => $query->whereHas('cotizacion', fn (Builder $q) => $q->where('sucursal_id', $sucursalId)),
            fn (Builder $query) => $query->whereRaw('1 = 0'),
        );
    }
}
