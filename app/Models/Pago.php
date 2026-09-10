<?php

namespace App\Models;

use App\Models\Concerns\AcotaPorSucursal;
use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'pedido_id',
    'monto',
    'fecha_pago',
    'metodo_pago',
    'comprobante_url',
])]

class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use AcotaPorSucursal, HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'pago';

    public const METODOS = ['EFECTIVO', 'TRANSFERENCIA', 'QR', 'TARJETA', 'CHEQUE'];

    /**
     * Estados de COBRANZA por los que se puede filtrar el listado. No son
     * un campo del pago: describen el saldo del pedido al que pertenece
     * (ver `Pedido::estadoPago()`). La columna `pago.estado` se eliminó
     * porque duplicaba ese derivado y quedaba desactualizada al corregir o
     * anular un pago — ver la migración `drop_estado_from_pago_table`.
     */
    public const ESTADOS_COBRANZA = ['PARCIAL', 'PAGADO'];

    /**
     * @var list<string>
     */
    protected $appends = ['comprobante_publico_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    protected function comprobantePublicoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->comprobante_url ? Storage::disk('public')->url($this->comprobante_url) : null,
        );
    }

    /**
     * Filtra los pagos según el estado de cobranza del PEDIDO al que
     * pertenecen: PAGADO si lo cobrado cubre el total, PARCIAL si todavía
     * hay saldo. Se resuelve con una subconsulta sobre la suma de pagos del
     * pedido, que es el dato real, en vez de leer una columna cacheada.
     */
    #[Scope]
    protected function estadoCobranza(Builder $query, ?string $estado): void
    {
        $query->when($estado, function (Builder $query) use ($estado) {
            $cobrado = '(select coalesce(sum(pago_saldo.monto), 0) from pago as pago_saldo where pago_saldo.pedido_id = pedido.id)';

            $query->whereHas('pedido', fn (Builder $pedido) => $estado === 'PAGADO'
                ? $pedido->whereRaw("{$cobrado} >= pedido.total")
                : $pedido->whereRaw("{$cobrado} < pedido.total"));
        });
    }

    /**
     * Filtra por método de pago exacto. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function metodo(Builder $query, ?string $metodo): void
    {
        $query->when($metodo, fn (Builder $query) => $query->where('metodo_pago', $metodo));
    }

    /**
     * Cuelga del pedido, que llega a la sucursal por su cotizacion de origen.
     *
     * OJO: acotar el listado no alcanza — los TOTALES de cobranza de
     * `PagoController::index` tambien pasan por `visiblePara`, o un vendedor
     * veria la caja de toda la empresa en las tarjetas de resumen.
     *
     * Ver App\Models\Concerns\AcotaPorSucursal.
     */
    protected static function rutaSucursal(): ?string
    {
        return 'pedido.cotizacion';
    }
}
