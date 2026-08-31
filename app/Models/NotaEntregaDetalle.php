<?php

namespace App\Models;

use Database\Factories\NotaEntregaDetalleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'nota_entrega_id',
    'pedido_detalle_id',
    'descripcion',
    'cantidad_entregada',
    'ubicacion',
    'foto_url',
])]

class NotaEntregaDetalle extends Model
{
    /** @use HasFactory<NotaEntregaDetalleFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'nota_entrega_detalle';

    /**
     * @var list<string>
     */
    protected $appends = ['foto_publica_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_entregada' => 'decimal:2',
        ];
    }

    public function notaEntrega(): BelongsTo
    {
        return $this->belongsTo(NotaEntrega::class);
    }

    public function pedidoDetalle(): BelongsTo
    {
        return $this->belongsTo(PedidoDetalle::class);
    }

    /**
     * `foto_url` guarda la ruta relativa en el disco `public`; esto expone
     * la URL pública ya resuelta para el frontend.
     */
    protected function fotoPublicaUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->foto_url ? Storage::disk('public')->url($this->foto_url) : null,
        );
    }
}
