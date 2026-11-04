<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes del sistema editables desde el panel (clave → valor).
 *
 * Una clave sin fila devuelve el valor por defecto que pasa quien la lee
 * (normalmente el de `config/*.php`): así el `.env` sigue funcionando hasta
 * que el dueño guarda un valor propio.
 */
class Configuracion extends Model
{
    protected $table = 'configuracion';

    protected $fillable = [
        'clave',
        'valor',
    ];

    public static function valor(string $clave, mixed $defecto = null): mixed
    {
        $valor = static::query()->where('clave', $clave)->value('valor');

        return $valor ?? $defecto;
    }

    public static function guardar(string $clave, mixed $valor): void
    {
        static::query()->updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor === null ? null : (string) $valor],
        );
    }
}
