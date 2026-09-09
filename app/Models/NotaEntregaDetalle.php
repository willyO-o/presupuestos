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

    /**
     * La foto de evidencia como data URI, para incrustarla en el PDF de la
     * nota de entrega (App\Services\Pdf\GeneradorPdf).
     *
     * Chromium genera el PDF sin salir a la red, así que un
     * `<img src="http://...">` apuntando a `foto_publica_url` saldría en
     * blanco. Va como accesor y no en la vista para que el Blade no tenga que
     * saber en qué disco vive el archivo.
     *
     * Devuelve null si la foto no está (registro viejo, archivo borrado a
     * mano): el documento se emite igual, con un guion en esa celda.
     */
    public function fotoIncrustada(): ?string
    {
        if (! $this->foto_url) {
            return null;
        }

        $disco = Storage::disk('public');

        if (! $disco->exists($this->foto_url)) {
            return null;
        }

        return 'data:'.$disco->mimeType($this->foto_url).';base64,'.base64_encode($disco->get($this->foto_url));
    }
}
