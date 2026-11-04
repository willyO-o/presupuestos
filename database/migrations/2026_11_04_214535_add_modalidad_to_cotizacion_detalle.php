<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cómo se fijó el precio de una línea del cotizador de muebles (paso 1 del
 * asistente): COMPOSICION (hoja de costos + recargo), PRECIO_MANUAL (precio
 * por unidad escrito a mano) o SUPERFICIE (precio por m² × ancho × alto).
 * Sirve para reabrir el asistente en el mismo modo. Null en las líneas del
 * cotizador general y en las anteriores a este cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            $table->enum('modalidad', ['COMPOSICION', 'PRECIO_MANUAL', 'SUPERFICIE'])->nullable()->after('precio_manual');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            $table->dropColumn('modalidad');
        });
    }
};
