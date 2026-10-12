<?php

namespace App\Models;

use App\Models\Concerns\AcotaPorSucursal;
use Database\Factories\BiAnomaliaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'huella',
    'tipo',
    'severidad',
    'titulo',
    'detalle',
    'que_revisar',
    'hechos',
    'sucursal_id',
    'url',
    'redaccion',
    'estado',
    'detectada_en',
    'revisada_por',
    'revisada_en',
])]

/**
 * Anomalía detectada por las reglas estadísticas de
 * App\Services\Reporte\DeteccionAnomaliasService.
 *
 * La detección es estadística y reproducible; la IA (cuando está
 * disponible) solo reescribe `detalle` y `que_revisar` en lenguaje claro.
 * `huella` identifica "la misma anomalía" para no repetirla cada día.
 *
 * `sucursal_id` null = anomalía de toda la empresa (costos de material,
 * volumen total): la ve solo quien ve todas las sucursales.
 */
class BiAnomalia extends Model
{
    /** @use HasFactory<BiAnomaliaFactory> */
    use AcotaPorSucursal, HasFactory;

    public const TIPOS = [
        'caida_cotizaciones' => 'Caída de cotizaciones',
        'alza_cotizaciones' => 'Alza inusual de cotizaciones',
        'margen_bajo' => 'Margen muy bajo en una venta',
        'vendedor_inactivo' => 'Vendedor sin actividad',
        'cliente_en_riesgo' => 'Cliente grande que dejó de comprar',
    ];

    public const ESTADOS = ['NUEVA', 'REVISADA', 'DESCARTADA'];

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'bi_anomalia';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hechos' => 'array',
            'detectada_en' => 'date',
            'revisada_en' => 'datetime',
        ];
    }

    protected static function rutaSucursal(): ?string
    {
        return null;
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }

    /**
     * Anomalías que `$user` puede ver: las de sus sucursales y, si ve todas,
     * también las de toda la empresa (`sucursal_id` null). Mismo criterio que
     * BiResumen::visibleResumenPara.
     */
    #[Scope]
    protected function visibleAnomaliaPara(Builder $query, User $user): void
    {
        if ($user->sucursalesVisibles() === null) {
            return;
        }

        $query->visiblePara($user);
    }
}
