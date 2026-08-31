<?php

namespace App\Models;

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
    'cliente_id',
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
    use HasFactory;

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
    protected $appends = ['archivo_url'];

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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
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
                    ->orWhereHas('cliente', fn (Builder $q) => $q->where('razon_social', 'like', "%{$term}%"));
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
}
