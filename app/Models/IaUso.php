<?php

namespace App\Models;

use Database\Factories\IaUsoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'funcionalidad',
    'proveedor',
    'modelo',
    'tokens_entrada',
    'tokens_salida',
    'tokens_cache',
    'costo_usd',
    'latencia_ms',
    'estado',
    'error',
])]

/**
 * Registro de cada llamada a un modelo de IA (ver App\Ai\ServicioIa).
 *
 * Es la fuente del tope de gasto mensual: lo gastado en el mes es la suma
 * de `costo_usd` de este mes, calculada con los precios de config/ia.php y
 * los tokens que reporta el proveedor.
 */
class IaUso extends Model
{
    /** @use HasFactory<IaUsoFactory> */
    use HasFactory;

    public const ESTADOS = ['OK', 'ERROR', 'SIN_PRESUPUESTO', 'NO_CONFIGURADA'];

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'ia_uso';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'costo_usd' => 'decimal:6',
            'tokens_entrada' => 'integer',
            'tokens_salida' => 'integer',
            'tokens_cache' => 'integer',
            'latencia_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Llamadas del mes calendario en curso, en la hora de la empresa (un
     * llamado del 31 a las 22:00 en La Paz no cuenta para el mes siguiente).
     */
    #[Scope]
    protected function delMesActual(Builder $query): void
    {
        $zona = config('ia.zona_horaria');
        $inicio = Carbon::now($zona)->startOfMonth()->utc();
        $fin = Carbon::now($zona)->endOfMonth()->utc();

        $query->whereBetween($query->qualifyColumn('created_at'), [$inicio, $fin]);
    }

    /**
     * Gasto en USD del mes en curso.
     */
    public static function gastoDelMes(): float
    {
        return round((float) static::query()->delMesActual()->sum('costo_usd'), 6);
    }
}
