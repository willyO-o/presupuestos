<?php

namespace App\Models;

use App\Models\Concerns\AcotaPorSucursal;
use Database\Factories\OrdenCompraClienteFactory;
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
    'numero_oc',
    'fecha',
    'monto_total',
    'condicion_pago',
    'archivo_pdf',
    'estado',
])]

class OrdenCompraCliente extends Model
{
    /** @use HasFactory<OrdenCompraClienteFactory> */
    use AcotaPorSucursal, HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'orden_compra_cliente';

    /**
     * Documento formal que envía el cliente (ej. "Orden de Compra 11021545"):
     * PENDIENTE al registrarla, VALIDADA cuando ventas la coteja contra el
     * pedido, ANULADA si el cliente la retira.
     */
    public const ESTADOS = ['PENDIENTE', 'VALIDADA', 'ANULADA'];

    /**
     * @var list<string>
     */
    protected $appends = ['archivo_url', 'cliente_razon_social'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto_total' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * Cliente de la OC: SIEMPRE el de la cotización que originó el pedido.
     * No hay `cliente_id` en la tabla (ver la migración
     * `drop_cliente_id_from_orden_compra_cliente_table`): esa FK permitía
     * que la OC apuntara a un cliente distinto del que firmó la cotización.
     * Se recorre la cadena real `pedido → cotizacion → cliente`, que no
     * puede desincronizarse. Requiere `with('pedido.cotizacion.cliente')`.
     */
    public function cliente(): ?Cliente
    {
        return $this->pedido?->cotizacion?->cliente;
    }

    /**
     * Razón social del cliente, para que las vistas no tengan que recorrer
     * tres niveles de relación.
     */
    protected function clienteRazonSocial(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->cliente()?->razon_social);
    }

    /**
     * true si el importe declarado en el documento del cliente NO coincide
     * con el total del pedido. `monto_total` se conserva justamente para
     * poder detectar esto al validar la OC — no es una copia del total.
     */
    public function difiereDelPedido(): bool
    {
        return $this->pedido !== null
            && abs((float) $this->monto_total - (float) $this->pedido->total) >= 0.01;
    }

    /**
     * URL pública del PDF adjunto (disco `public`), o null si no se subió.
     */
    protected function archivoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->archivo_pdf ? Storage::disk('public')->url($this->archivo_pdf) : null,
        );
    }

    /**
     * Filtra por coincidencia parcial en número de OC o razón social del
     * cliente. Sin término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('numero_oc', 'like', "%{$term}%")
                    ->orWhereHas('pedido.cotizacion.cliente', fn (Builder $q) => $q->where('razon_social', 'like', "%{$term}%"));
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
     * Cuelga del pedido, que llega a la sucursal por su cotizacion de origen.
     *
     * Ver App\Models\Concerns\AcotaPorSucursal.
     */
    protected static function rutaSucursal(): ?string
    {
        return 'pedido.cotizacion';
    }
}
