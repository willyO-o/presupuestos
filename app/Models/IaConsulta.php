<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'conversacion_id',
    'pregunta',
    'respuesta',
    'resultados',
    'proveedor',
    'modelo',
    'costo_usd',
    'estado',
])]

/**
 * Una pregunta al asistente de BI y su respuesta, con los resultados de
 * las herramientas que se usaron. Los gráficos y tablas del panel salen de
 * `resultados` (datos de la base), nunca del texto del modelo.
 */
class IaConsulta extends Model
{
    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'ia_consulta';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultados' => 'array',
            'costo_usd' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
