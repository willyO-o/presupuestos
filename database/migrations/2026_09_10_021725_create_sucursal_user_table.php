<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sucursales que un usuario administra ademas de (o en vez de) la de su
     * ficha de empleado. Solo se consulta cuando
     * `users.alcance_sucursal = 'ASIGNADAS'`: con PROPIA se usa
     * `empleado.sucursal_id` y con TODAS no se filtra nada.
     *
     * Nombre en singular_singular (`sucursal_user`) por la convencion de
     * tablas en singular del esquema (ver .ai/rules/migrations.md); el orden
     * alfabetico es ademas el que Eloquent espera para un belongsToMany sin
     * `$table` explicito.
     *
     * Ambas FK van en cascada — es una asignacion, no historial: si se borra
     * la cuenta o la sucursal, la fila deja de tener sentido. Ojo que
     * `sucursal` igual esta protegida por los `restrictOnDelete()` de
     * `empleado`/`cotizacion`, asi que esto no abre la puerta a borrar una
     * sucursal con movimiento.
     */
    public function up(): void
    {
        Schema::create('sucursal_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursal')->cascadeOnDelete();
            $table->timestamps();

            // Una sucursal no se asigna dos veces al mismo usuario.
            $table->unique(['user_id', 'sucursal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_user');
    }
};
