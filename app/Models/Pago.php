<?php

namespace App\Models;

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
    'estado',
    'comprobante_url',
])]

class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'pago';

    public const METODOS = ['EFECTIVO', 'TRANSFERENCIA', 'QR', 'TARJETA', 'CHEQUE'];

    /**
     * Estado del saldo del pedido AL momento de registrar este pago
     * (PARCIAL mientras se debe, PAGADO cuando queda cubierto).
     */
    public const ESTADOS = ['PENDIENTE', 'PAGADO', 'PARCIAL'];

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
     * Filtra por estado exacto. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function estado(Builder $query, ?string $estado): void
    {
        $query->when($estado, fn (Builder $query) => $query->where('estado', $estado));
    }

    /**
     * Filtra por método de pago exacto. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function metodo(Builder $query, ?string $metodo): void
    {
        $query->when($metodo, fn (Builder $query) => $query->where('metodo_pago', $metodo));
    }
}
