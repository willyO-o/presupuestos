<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tipo',
    'razon_social',
    'nit',
    'contacto_nombre',
    'telefono',
    'email',
    'direccion',
    'ciudad',
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
