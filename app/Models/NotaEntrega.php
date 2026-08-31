<?php

namespace App\Models;

use Database\Factories\NotaEntregaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'pedido_id',
    'empleado_id',
    'numero_nota',
    'fecha_entrega',
    'recibido_por',
    'cargo_receptor',
    'observaciones',
    'archivo_pdf',
])]

class NotaEntrega extends Model
{
    /** @use HasFactory<NotaEntregaFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'nota_entrega';

    /**
     * @var list<string>
     */
    protected $appends = ['archivo_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'date',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(NotaEntregaDetalle::class);
    }

    protected function archivoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->archivo_pdf ? Storage::disk('public')->url($this->archivo_pdf) : null,
        );
    }

    /**
     * Filtra por coincidencia parcial en número de nota, número de pedido o
     * quien recibió. Sin término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('numero_nota', 'like', "%{$term}%")
                    ->orWhere('recibido_por', 'like', "%{$term}%")
                    ->orWhereHas('pedido', fn (Builder $q) => $q->where('numero_pedido', 'like', "%{$term}%"));
            });
        });
    }
}
