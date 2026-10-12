<?php

namespace App\Ai\Seguridad;

use App\Models\User;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ejecuta un bloque de código consultando SOLO por la conexión de lectura.
 *
 * Es por donde pasan las herramientas del asistente y los cálculos que se le
 * entregan a la IA. Tres barreras, de afuera hacia adentro:
 *
 * 1. Mientras dura el bloque, la conexión por defecto es
 *    `config('ia.conexion_lectura')`: todo Eloquent sin conexión explícita
 *    (los servicios del BI de siempre) consulta por ahí.
 * 2. Un guardia en esa conexión rechaza cualquier sentencia que no sea de
 *    lectura (SELECT / WITH / SHOW / DESCRIBE / EXPLAIN) antes de enviarla.
 * 3. En MariaDB la conexión abre la sesión con
 *    `SET SESSION TRANSACTION READ ONLY` y puede usar un usuario con GRANT
 *    SELECT solamente (config/database.php, `php artisan ia:usuario-lectura`).
 *
 * El alcance por sucursal del usuario se resuelve ANTES de cambiar de
 * conexión: consulta roles y permisos, que el usuario de solo lectura no
 * puede leer a propósito.
 */
final class ConsultaSoloLectura
{
    private static bool $activa = false;

    /**
     * Conexiones con el guardia ya instalado, por INSTANCIA y no por nombre:
     * un `DB::purge()`, un worker de cola que reconecta o una app nueva en
     * cada test crean otro objeto Connection con el mismo nombre, y ese
     * objeto nace sin el guardia.
     *
     * @var \WeakMap<Connection, true>|null
     */
    private static ?\WeakMap $guardias = null;

    /**
     * @template T
     *
     * @param  Closure(): T  $bloque
     * @return T
     */
    public static function ejecutar(?User $usuario, Closure $bloque): mixed
    {
        if ($usuario !== null) {
            // Memoiza el alcance de los módulos que usan los reportes: así
            // ningún `visiblePara()` necesita leer roles dentro del bloque.
            $usuario->sucursalesVisibles();
            $usuario->sucursalesVisibles('pedidos');
        }

        $conexion = config('ia.conexion_lectura');
        $anterior = DB::getDefaultConnection();

        self::instalarGuardia($conexion);

        $estabaActiva = self::$activa;
        self::$activa = true;
        DB::setDefaultConnection($conexion);

        try {
            return $bloque();
        } finally {
            DB::setDefaultConnection($anterior);
            self::$activa = $estabaActiva;
        }
    }

    public static function activa(): bool
    {
        return self::$activa;
    }

    /**
     * true si la sentencia solo lee. Se mira la primera palabra sin
     * comentarios: no es un parser SQL, es una lista blanca estricta (una
     * sentencia de lectura que empiece de otra forma se rechaza, y eso está
     * bien).
     */
    public static function esLectura(string $sql): bool
    {
        $limpio = ltrim(preg_replace('#/\*.*?\*/|--[^\n]*#s', '', $sql) ?? '');

        if ($limpio === '' || str_contains(rtrim($limpio, "; \n\r\t"), ';')) {
            return false;
        }

        return (bool) preg_match('/^(select|with|show|describe|desc|explain)\b/i', $limpio);
    }

    private static function instalarGuardia(string $nombre): void
    {
        self::$guardias ??= new \WeakMap;

        /** @var Connection $conexion */
        $conexion = DB::connection($nombre);

        if (isset(self::$guardias[$conexion])) {
            return;
        }

        $conexion->beforeExecuting(function (string $sql): void {
            if (self::$activa && ! self::esLectura($sql)) {
                throw new RuntimeException('Consulta bloqueada: la IA solo puede leer datos.');
            }
        });

        self::$guardias[$conexion] = true;
    }
}
