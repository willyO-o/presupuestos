<?php

namespace App\Models;

use Database\Factories\CotizacionPublicaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'codigo',
    'nombre',
    'empresa',
    'email',
    'telefono',
    'mensaje',
    'detalle',
    'subtotal',
    'iva',
    'total',
    'estimado_min',
    'estimado_max',
    'holgura',
    'vigencia_dias',
    'fecha_vencimiento',
    'estado',
    'descargas',
    'descargado_en',
    'cotizacion_id',
    'ip',
    'user_agent',
])]

class CotizacionPublica extends Model
{
    /** @use HasFactory<CotizacionPublicaFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'cotizacion_publica';

    /**
     * NUEVA al llegar; CONTACTADA cuando ventas la atendió; CONVERTIDA cuando
     * derivó en una `cotizacion` formal; DESCARTADA para spam o consultas que
     * no prosperaron.
     *
     * No hay estado VENCIDA: el vencimiento es una fecha, no un estado, y
     * calcularlo (`estaVigente()`) evita depender de que un job corra a
     * tiempo para que el sitio diga la verdad.
     */
    public const ESTADOS = ['NUEVA', 'CONTACTADA', 'CONVERTIDA', 'DESCARTADA'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'subtotal' => 'decimal:2',
            'iva' => 'decimal:2',
            'total' => 'decimal:2',
            'estimado_min' => 'decimal:2',
            'estimado_max' => 'decimal:2',
            'holgura' => 'decimal:4',
            'vigencia_dias' => 'integer',
            'fecha_vencimiento' => 'date',
            'descargas' => 'integer',
            'descargado_en' => 'datetime',
        ];
    }

    /**
     * Presupuesto formal emitido a partir de esta solicitud. Null mientras
     * ventas no la haya convertido.
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * true si la estimación todavía está dentro de su vigencia. El día del
     * vencimiento cuenta como válido: se prometió "válida hasta el X".
     */
    public function estaVigente(): bool
    {
        return ! $this->fecha_vencimiento->isBefore(today());
    }

    /**
     * Días que le quedan de vigencia (0 si ya venció).
     */
    public function diasRestantes(): int
    {
        return max(0, (int) today()->diffInDays($this->fecha_vencimiento, absolute: false));
    }

    /**
     * true si todavía se puede emitir el documento imprimible.
     *
     * El tope es por FILA y no solo por IP: un rate limiter se agota y se
     * renueva, así que con un código válido y paciencia se puede pedir el
     * documento indefinidamente. Esto lo corta de raíz.
     */
    public function puedeDescargar(): bool
    {
        return $this->descargas < (int) config('cotizador.descargas_maximas', 3);
    }

    /**
     * Anota una emisión más del documento. `descargado_en` guarda la PRIMERA
     * (es lo que se le muestra al visitante y lo que hace desaparecer el
     * botón); `descargas` cuenta todas, que es lo que aplica el tope.
     */
    public function registrarDescarga(): void
    {
        $this->forceFill([
            'descargas' => $this->descargas + 1,
            'descargado_en' => $this->descargado_en ?? now(),
        ])->save();
    }

    /**
     * Filtra por coincidencia parcial en código, nombre, empresa, teléfono o
     * correo. Sin término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('codigo', 'like', "%{$term}%")
                    ->orWhere('nombre', 'like', "%{$term}%")
                    ->orWhere('empresa', 'like', "%{$term}%")
                    ->orWhere('telefono', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
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
