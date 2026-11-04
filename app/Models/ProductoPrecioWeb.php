<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un rango de área del precio web de un producto: hasta `area_hasta` m² se
 * cobra `precio_m2` por m². `area_hasta` null = sin tope (último rango).
 */
class ProductoPrecioWeb extends Model
{
    protected $table = 'producto_precio_web';

    protected $fillable = [
        'producto_id',
        'area_hasta',
        'precio_m2',
    ];

    protected function casts(): array
    {
        return [
            'area_hasta' => 'decimal:2',
            'precio_m2' => 'decimal:2',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
