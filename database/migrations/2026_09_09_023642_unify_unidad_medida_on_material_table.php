<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unifica el vocabulario de unidades de medida (2026-09-09).
     *
     * Había dos listas distintas para el mismo concepto: `material` usaba
     * `METRO` y `producto` usaba `METRO_LINEAL` — el mismo metro lineal con
     * dos nombres. Eso obligaba a traducir a mano entre catálogos y rompía
     * cualquier `GROUP BY unidad_medida` que cruzara ambas tablas.
     *
     * A partir de acá el vocabulario canónico es `Material::UNIDADES_MEDIDA`
     * (M2, METRO_LINEAL, UNIDAD, LITRO) y `Producto::UNIDADES_MEDIDA` es un
     * subconjunto suyo (un producto no se vende por litro).
     *
     * El ENUM se ensancha para que acepte los DOS nombres, recién ahí se
     * migran los datos y al final se recorta. Hacerlo al revés falla: MariaDB
     * rechaza un UPDATE a un valor que el ENUM todavía no admite
     * ("Data truncated for column 'unidad_medida'").
     */
    public function up(): void
    {
        Schema::table('material', function (Blueprint $table) {
            $table->enum('unidad_medida', ['M2', 'METRO', 'METRO_LINEAL', 'UNIDAD', 'LITRO'])->change();
        });

        DB::table('material')->where('unidad_medida', 'METRO')->update(['unidad_medida' => 'METRO_LINEAL']);

        Schema::table('material', function (Blueprint $table) {
            $table->enum('unidad_medida', ['M2', 'METRO_LINEAL', 'UNIDAD', 'LITRO'])->change();
        });

        // `cotizacion_detalle_item.unidad` es texto libre a propósito (tiene
        // que poder decir HORA para la mano de obra), pero las filas que sí
        // vienen de un material deben hablar el vocabulario canónico.
        DB::table('cotizacion_detalle_item')
            ->whereNotNull('material_id')
            ->where('unidad', 'METRO')
            ->update(['unidad' => 'METRO_LINEAL']);
    }

    public function down(): void
    {
        // Se ensancha el ENUM para que acepte los dos nombres, se migran los
        // datos y recién ahí se recorta: si se recortara primero, MariaDB
        // vaciaría los METRO_LINEAL antes de poder convertirlos.
        Schema::table('material', function (Blueprint $table) {
            $table->enum('unidad_medida', ['M2', 'METRO', 'METRO_LINEAL', 'UNIDAD', 'LITRO'])->change();
        });

        DB::table('material')->where('unidad_medida', 'METRO_LINEAL')->update(['unidad_medida' => 'METRO']);

        Schema::table('material', function (Blueprint $table) {
            $table->enum('unidad_medida', ['M2', 'METRO', 'UNIDAD', 'LITRO'])->change();
        });
    }
};
