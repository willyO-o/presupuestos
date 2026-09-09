<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la etapa CONTROL_CALIDAD entre ACABADO y ENTREGA, que el flujo
     * documentado de la empresa exige y el esquema original no contemplaba
     * ("Proceso 2 – Producción: 4. Control de calidad", y §9 del documento
     * "Proceso completo cliente a entrega": verificar medidas, acabados y
     * funcionalidad ANTES de entregar).
     *
     * Se usa `->change()` (no SQL crudo) para que funcione igual en MariaDB
     * —donde es un ENUM real— y en SQLite, el motor de los tests, donde
     * Laravel traduce el enum a un CHECK y necesita reconstruir la tabla.
     * Ningún dato existente cambia: solo se amplía el conjunto de valores.
     */
    public function up(): void
    {
        Schema::table('pedido', function (Blueprint $table) {
            $table->enum('estado', ['DISENO', 'ELABORACION', 'ACABADO', 'CONTROL_CALIDAD', 'ENTREGADO', 'CANCELADO'])
                ->default('DISENO')->change();
        });

        Schema::table('pedido_detalle', function (Blueprint $table) {
            $table->enum('estado_item', ['DISENO', 'ELABORACION', 'ACABADO', 'CONTROL_CALIDAD', 'ENTREGADO'])
                ->default('DISENO')->change();
        });

        Schema::table('pedido_seguimiento', function (Blueprint $table) {
            $table->enum('etapa', ['DISENO', 'ELABORACION', 'ACABADO', 'CONTROL_CALIDAD', 'ENTREGA'])->change();
        });
    }

    /**
     * Revertir exige que no queden filas en la etapa nueva: se las devuelve a
     * ACABADO (la anterior del flujo) antes de recortar el conjunto de
     * valores, para no perderlas silenciosamente.
     */
    public function down(): void
    {
        DB::table('pedido')->where('estado', 'CONTROL_CALIDAD')->update(['estado' => 'ACABADO']);
        DB::table('pedido_detalle')->where('estado_item', 'CONTROL_CALIDAD')->update(['estado_item' => 'ACABADO']);
        DB::table('pedido_seguimiento')->where('etapa', 'CONTROL_CALIDAD')->update(['etapa' => 'ACABADO']);

        Schema::table('pedido', function (Blueprint $table) {
            $table->enum('estado', ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO', 'CANCELADO'])
                ->default('DISENO')->change();
        });

        Schema::table('pedido_detalle', function (Blueprint $table) {
            $table->enum('estado_item', ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO'])
                ->default('DISENO')->change();
        });

        Schema::table('pedido_seguimiento', function (Blueprint $table) {
            $table->enum('etapa', ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGA'])->change();
        });
    }
};
