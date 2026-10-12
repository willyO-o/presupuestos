<?php

namespace App\Models;

use App\Models\Concerns\AcotaPorSucursal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'semana_inicio',
    'semana_fin',
    'sucursal_id',
    'hechos',
    'contenido',
    'origen',
    'proveedor',
    'modelo',
    'generado_por',
])]

/**
 * Resumen semanal del BI (ver App\Services\Reporte\ResumenSemanalService).
 *
 * `hechos` es la fuente de verdad (números calculados en PHP); `contenido`
 * es el texto con titular, destacados, riesgos y recomendaciones. `origen`
 * dice si lo redactó la IA o la plantilla de respaldo.
 *
 * `sucursal_id` null es el consolidado de la empresa: solo lo ve quien ve
 * todas las sucursales (`visiblePara` lo filtra igual que cualquier otro
 * dato acotado; ver scope `visibleResumenPara`).
 */
class BiResumen extends Model
{
    use AcotaPorSucursal;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'bi_resumen';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semana_inicio' => 'date',
            'semana_fin' => 'date',
            'hechos' => 'array',
            'contenido' => 'array',
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

    public function generador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    /**
     * Resúmenes que `$user` puede leer: los de sus sucursales y, si ve todas,
     * también el consolidado (`sucursal_id` null). `visiblePara` solo no
     * alcanza: un `whereIn` deja afuera los null, que es lo correcto para un
     * usuario acotado pero oculta el consolidado a gerencia.
     */
    #[Scope]
    protected function visibleResumenPara(Builder $query, User $user): void
    {
        if ($user->sucursalesVisibles() === null) {
            return;
        }

        $query->visiblePara($user);
    }
}
