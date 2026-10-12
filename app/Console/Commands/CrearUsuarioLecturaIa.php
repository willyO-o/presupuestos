<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Arma el usuario de MariaDB de SOLO LECTURA para las funciones de IA.
 *
 * Solo recibe SELECT, y solo sobre las tablas de negocio. Quedan afuera las
 * tablas de usuarios, roles, permisos, sesiones, tokens, colas, caché,
 * notificaciones y el propio registro de IA. En las tablas con datos
 * personales (cliente, empleado, proveedor) el permiso es por COLUMNA: sin
 * NIT, CI, teléfonos, correos ni direcciones.
 *
 * Por defecto solo imprime el SQL; con --aplicar lo ejecuta con la conexión
 * principal (que necesita privilegio de GRANT) y muestra las líneas para el
 * .env.
 */
class CrearUsuarioLecturaIa extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ia:usuario-lectura
        {--usuario=ia_lectura : Nombre del usuario de MariaDB}
        {--host=localhost : Host desde el que se conecta la aplicación}
        {--aplicar : Ejecutar el SQL en vez de solo mostrarlo}';

    /**
     * @var string
     */
    protected $description = 'Genera (o aplica) el SQL del usuario de MariaDB de solo lectura que usan las funciones de IA';

    /**
     * Tablas de negocio con SELECT completo.
     */
    private const TABLAS = [
        'area', 'categoria_material', 'categoria_producto', 'familia_producto', 'compra', 'compra_detalle',
        'cotizacion', 'cotizacion_detalle', 'cotizacion_detalle_item', 'formula', 'historial_precio_material',
        'material', 'nota_entrega', 'nota_entrega_detalle', 'orden_compra_cliente', 'pago', 'pedido',
        'pedido_detalle', 'pedido_detalle_material', 'pedido_seguimiento', 'producto', 'producto_material',
        'seguimiento_postventa', 'sucursal', 'tipo_proyecto', 'bi_anomalia',
    ];

    /**
     * Tablas con datos personales: SELECT solo sobre estas columnas.
     */
    private const COLUMNAS = [
        'cliente' => ['id', 'tipo', 'razon_social', 'ciudad', 'estado', 'created_at', 'updated_at'],
        'empleado' => ['id', 'sucursal_id', 'area_id', 'nombres', 'paterno', 'materno', 'cargo', 'fecha_ingreso', 'estado', 'created_at', 'updated_at'],
        'proveedor' => ['id', 'nombre', 'estado', 'created_at', 'updated_at'],
    ];

    public function handle(): int
    {
        $usuario = (string) $this->option('usuario');
        $host = (string) $this->option('host');
        $base = (string) config('database.connections.'.config('database.default').'.database');
        $clave = Str::password(24, symbols: false);

        if (! preg_match('/^[A-Za-z0-9_]{1,32}$/', $usuario) || ! preg_match('/^[A-Za-z0-9_.%-]{1,60}$/', $host)) {
            $this->error('Usuario o host con caracteres no permitidos.');

            return self::FAILURE;
        }

        $sentencias = ["CREATE USER IF NOT EXISTS '{$usuario}'@'{$host}' IDENTIFIED BY '{$clave}'"];

        foreach (self::TABLAS as $tabla) {
            if (Schema::hasTable($tabla)) {
                $sentencias[] = "GRANT SELECT ON `{$base}`.`{$tabla}` TO '{$usuario}'@'{$host}'";
            }
        }

        foreach (self::COLUMNAS as $tabla => $columnas) {
            if (Schema::hasTable($tabla)) {
                $existentes = array_values(array_intersect($columnas, Schema::getColumnListing($tabla)));
                $lista = implode(', ', array_map(fn ($c) => "`{$c}`", $existentes));
                $sentencias[] = "GRANT SELECT ({$lista}) ON `{$base}`.`{$tabla}` TO '{$usuario}'@'{$host}'";
            }
        }

        if (! $this->option('aplicar')) {
            $this->line('-- Ejecutar como un usuario con privilegio de GRANT (o usar --aplicar):');
            foreach ($sentencias as $sql) {
                $this->line($sql.';');
            }
            $this->newLine();
            $this->line("-- Luego en .env:\nIA_DB_USERNAME={$usuario}\nIA_DB_PASSWORD={$clave}");

            return self::SUCCESS;
        }

        // Si el usuario ya existía, CREATE USER IF NOT EXISTS no cambia su
        // clave: se fuerza para que la del .env sea la vigente.
        $sentencias[] = "ALTER USER '{$usuario}'@'{$host}' IDENTIFIED BY '{$clave}'";

        foreach ($sentencias as $sql) {
            DB::statement($sql);
        }

        $this->info("Usuario {$usuario}@{$host} listo con SELECT sobre ".count($sentencias) - 2 .' tablas.');
        $this->line("Agregar a .env:\nIA_DB_USERNAME={$usuario}\nIA_DB_PASSWORD={$clave}");

        return self::SUCCESS;
    }
}
