<?php

namespace App\Models;

use App\Models\Concerns\AcotaPorSucursal;
use Database\Factories\SeguimientoPostventaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pedido_id',
    'empleado_id',
    'fecha_programada',
    'fecha_contacto',
    'estado',
    'medio',
    'satisfaccion',
    'requiere_accion',
    'oportunidad',
    'observaciones',
])]

class SeguimientoPostventa extends Model
{
    /** @use HasFactory<SeguimientoPostventaFactory> */
    use AcotaPorSucursal, HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'seguimiento_postventa';

    /**
     * PENDIENTE al programarse (entrega + N días), REALIZADO cuando se
     * registra el contacto, NO_CONTACTADO si se intentó sin éxito.
     */
    public const ESTADOS = ['PENDIENTE', 'REALIZADO', 'NO_CONTACTADO'];

    public const MEDIOS = ['LLAMADA', 'WHATSAPP', 'CORREO', 'VISITA'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_programada' => 'date',
            'fecha_contacto' => 'date',
            'satisfaccion' => 'integer',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * Quién hizo el contacto. `null` mientras el seguimiento está pendiente.
     */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    /**
     * true si ya pasó la fecha en que había que llamar al cliente y todavía
     * nadie lo hizo — es lo que la pantalla marca en rojo.
     */
    public function estaVencido(): bool
    {
        return $this->estado === 'PENDIENTE' && $this->fecha_programada->isPast();
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
     * Seguimientos que ya tocaba hacer: pendientes con fecha programada
     * vencida o de hoy. Es la bandeja de trabajo del vendedor.
     */
    #[Scope]
    protected function pendientesDeContacto(Builder $query): void
    {
        $query->where('estado', 'PENDIENTE')->whereDate('fecha_programada', '<=', now());
    }

    /**
     * Filtra por coincidencia parcial en número de pedido o razón social del
     * cliente. Sin término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->whereHas('pedido', function (Builder $query) use ($term) {
                $query->where('numero_pedido', 'like', "%{$term}%")
                    ->orWhereHas('cotizacion.cliente', fn (Builder $q) => $q->where('razon_social', 'like', "%{$term}%"));
            });
        });
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
