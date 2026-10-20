<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
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
    'user_id',
    'tipo',
    'razon_social',
    'nit',
    'contacto_nombre',
    'telefono',
    'email',
    'direccion',
    'ciudad',
    'logo',
    'estado',
])]

class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    /**
     * Tabla en singular (convención de este esquema, ver .ai/rules/migrations.md).
     *
     * @var string
     */
    protected $table = 'cliente';

    /**
     * @var list<string>
     */
    protected $appends = ['logo_url'];

    /**
     * Cuenta de acceso al portal (opcional, `cliente.user_id`).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * URL pública del logo (disco `public`, ya convertido a JPG por
     * App\Services\Imagen\ConvierteImagenAJpgService). Null sin logo: la
     * pantalla muestra las iniciales.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logo ? Storage::disk('public')->url($this->logo) : null,
        );
    }

    /**
     * Cotizaciones de este cliente (usado por el portal para scopear).
     */
    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    /**
     * Filtra por coincidencia parcial en razón social, NIT o contacto. Sin
     * término, no aplica ningún filtro.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, function (Builder $query) use ($term) {
            $query->where(function (Builder $query) use ($term) {
                $query->where('razon_social', 'like', "%{$term}%")
                    ->orWhere('nit', 'like', "%{$term}%")
                    ->orWhere('contacto_nombre', 'like', "%{$term}%");
            });
        });
    }

    /**
     * Filtra por estado exacto (ACTIVO/INACTIVO). Sin valor, no aplica filtro.
     */
    #[Scope]
    protected function estado(Builder $query, ?string $estado): void
    {
        $query->when($estado, fn (Builder $query) => $query->where('estado', $estado));
    }
}
