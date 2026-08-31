<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El portal del cliente puede crear una cotización PENDIENTE sin
     * vendedor asignado (`empleado_id` null) — ventas la toma después. Se
     * pasa a `nullOnDelete` para acompañar el cambio.
     */
    public function up(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
        });

        Schema::table('cotizacion', function (Blueprint $table) {
            $table->foreignId('empleado_id')->nullable()->change();
            $table->foreign('empleado_id')->references('id')->on('empleado')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
        });

        Schema::table('cotizacion', function (Blueprint $table) {
            $table->foreignId('empleado_id')->nullable(false)->change();
            $table->foreign('empleado_id')->references('id')->on('empleado')->restrictOnDelete();
        });
    }
};
