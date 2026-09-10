<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extiende la tabla nativa `users` (no se toca su migracion original,
     * ver .ai/rules/migrations.md) con el ALCANCE de sucursales del usuario:
     * hasta que sea el momento, un usuario solo podia ver la sucursal de su
     * ficha de empleado, y el unico escape era el permiso suelto
     * `pedidos.ver_todas_sucursales`, que ademas solo lo leia el modulo de
     * pedidos.
     *
     * - PROPIA    -> la sucursal de `empleado.sucursal_id` (comportamiento
     *                historico, por eso es el default: la migracion no
     *                cambia lo que ve nadie).
     * - ASIGNADAS -> las marcadas en el pivote `sucursal_user`.
     * - TODAS     -> sin filtro. Es un MODO y no "marcar todas en el
     *                pivote" a proposito: una sucursal nueva entra sola, sin
     *                que nadie tenga que acordarse de editar al contador.
     *
     * Los roles de `config('acl.sucursales.roles_globales')` (super-admin,
     * administrador) ignoran esta columna: ven todo por rol.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('alcance_sucursal', ['PROPIA', 'ASIGNADAS', 'TODAS'])
                ->default('PROPIA')
                ->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('alcance_sucursal');
        });
    }
};
