<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'estado'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * `INACTIVO` impide iniciar sesión (ver App\Http\Requests\Auth\LoginRequest).
     */
    public const ESTADOS = ['ACTIVO', 'INACTIVO'];

    /**
     * Se agrega al array/JSON (compartido vía Inertia en `auth.user`) para
     * que el frontend nunca tenga que armar la ruta de `storage/` a mano.
     *
     * @var list<string>
     */
    protected $appends = ['foto_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Ficha de empleado vinculada a esta cuenta (no todo usuario tiene una:
     * ver `empleado.user_id` nullable en database-design.md).
     */
    public function empleado(): HasOne
    {
        return $this->hasOne(Empleado::class);
    }

    /**
     * Ficha de cliente vinculada a esta cuenta (solo si el usuario tiene
     * acceso al portal, `cliente.user_id`).
     */
    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class);
    }

    /**
     * true si la cuenta puede iniciar sesión.
     */
    public function estaActivo(): bool
    {
        return $this->estado !== 'INACTIVO';
    }

    /**
     * URL pública de la foto de perfil (disco `public`, ver
     * `php artisan storage:link`). Null si el usuario no subió foto.
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->foto ? Storage::disk('public')->url($this->foto) : null,
        );
    }
}
