<?php

namespace App\Models;

use Database\Factories\CompraDetalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'compra_id',
    'material_id',
    'unidad_compra',
    'contenido_presentacion',
    'cantidad',
    'precio_unitario',
    'subtotal',
])]

class CompraDetalle extends Model
{
    /** @use HasFactory<CompraDetalleFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'compra_detalle';

    /**
     * En qué se compró la línea: por PRESENTACION (rollo, plancha, galón) o
     * por MEDIDA (m², ml, litro, unidad). `cantidad` y `precio_unitario`
     * están en esa unidad.
     */
    public const UNIDADES_COMPRA = ['PRESENTACION', 'MEDIDA'];

    /**
     * La cantidad en la unidad de medida del material (lo que suma al stock).
     */
    public function cantidadEnMedida(): float
    {
        return (float) $this->cantidad * $this->factorAMedida();
    }

    /**
     * Cuántas unidades de medida trae una unidad comprada: el contenido de
     * la presentación, o 1 si se compró por unidad de medida.
     */
    /**
     * Qué es la cantidad, para la pantalla y el PDF: "m²", o "rollo · = 320
     * m²" si se compró por presentación.
     */
    public function notaDeCantidad(): string
    {
        $unidad = Material::etiquetaUnidad($this->material?->unidad_medida);

        if ($this->unidad_compra !== 'PRESENTACION') {
            return $unidad;
        }

        $presentacion = mb_strtolower(strtok((string) $this->material?->presentacion, ' ') ?: 'presentación');
        $enMedida = rtrim(rtrim(number_format($this->cantidadEnMedida(), 2, ',', '.'), '0'), ',');

        return "{$presentacion} · = {$enMedida} {$unidad}";
    }

    public function factorAMedida(): float
    {
        return $this->unidad_compra === 'PRESENTACION' ? max((float) $this->contenido_presentacion, 0.0001) : 1.0;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'contenido_presentacion' => 'decimal:4',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
