<?php

namespace App\Models;

use Database\Factories\CotizacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'codigo_verificacion',
    'cliente_id',
    'empleado_id',
    'sucursal_id',
    'fecha',
    'fecha_vencimiento',
    'estado',
    'subtotal',
    'descuento',
    'impuesto',
    'total',
    'observaciones',
])]

class Cotizacion extends Model
{
    /** @use HasFactory<CotizacionFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'cotizacion';

    /**
     * Estados posibles del rombo "Propuesta Sí/No" del flujo del proyecto
     * (ver database-design.md §8/§12): PENDIENTE al crearla, APROBADA/RECHAZADA
     * al responder el cliente, CONVERTIDA cuando genera un pedido, VENCIDA si
     * pasó `fecha_vencimiento` sin respuesta.
     */
    public const ESTADOS = ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'CONVERTIDA', 'VENCIDA'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_vencimiento' => 'date',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Líneas del presupuesto (cada producto/ítem con sus medidas, cantidad y
     * precio). Pertenecen por completo a la cotización (cascadeOnDelete).
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class);
    }

    /**
     * Pedido generado al convertir la cotización (1:1). Null hasta que un
     * VENDEDOR/ADMIN la convierte (ver PedidoController::store), momento en
     * que el estado pasa a CONVERTIDA.
     */
    public function pedido(): HasOne
    {
        return $this->hasOne(Pedido::class);
    }

    /**
     * true si ya se puede convertir en pedido: aprobada y sin pedido previo.
     */
    public function esConvertible(): bool
    {
        return $this->estado === 'APROBADA' && $this->pedido()->doesntExist();
    }

    /**
     * true si todavía se puede editar/borrar: solo mientras está PENDIENTE.
     * Una vez respondida (APROBADA/RECHAZADA) o convertida en pedido queda
     * como documento histórico.
     */
    public function esEditable(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    /**
     * Filtra por coincidencia parcial en código de verificación, razón
     * social del cliente u observaciones. Sin término, no aplica ningún
     * filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('codigo_verificacion', 'like', "%{$term}%")
                    ->orWhere('observaciones', 'like', "%{$term}%")
                    ->orWhereHas('cliente', fn (Builder $query) => $query->where('razon_social', 'like', "%{$term}%"));
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
     * Filtra por cliente exacto. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function clienteId(Builder $query, ?string $clienteId): void
    {
        $query->when($clienteId, fn (Builder $query) => $query->where('cliente_id', $clienteId));
    }

    /**
     * Filtra por sucursal exacta. Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function sucursalId(Builder $query, ?string $sucursalId): void
    {
        $query->when($sucursalId, fn (Builder $query) => $query->where('sucursal_id', $sucursalId));
    }
}
