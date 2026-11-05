<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compras por presentación (rollo, plancha, galón).
 *
 * Antes una línea de compra se tomaba siempre en la unidad de medida del
 * material (m², ml, litro). Quien cargaba "2 rollos a Bs 2.900" dejaba la
 * lona a Bs 2.900 el m² y cada cotización salía ~160 veces más cara.
 *
 * - `material.contenido_presentacion`: cuánto trae una presentación en la
 *   unidad de medida (rollo 3,20 × 50 m = 160 m²; galón = 3,78 L). Se llena
 *   con la proporción precio de presentación ÷ precio unitario, que la
 *   aprobación de compras mantuvo constante incluso en los precios inflados.
 * - `compra_detalle.unidad_compra`: si la línea se compró por PRESENTACION o
 *   por MEDIDA. Las compras existentes quedan en MEDIDA (como se calcularon).
 * - `compra_detalle.contenido_presentacion`: copia del contenido al cargar la
 *   compra, para que aprobarla después use el mismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material', function (Blueprint $table) {
            $table->decimal('contenido_presentacion', 12, 4)->default(1)->after('presentacion');
        });

        DB::table('material')
            ->where('precio_unitario', '>', 0)
            ->update(['contenido_presentacion' => DB::raw('ROUND(precio_presentacion / precio_unitario, 4)')]);

        DB::table('material')->where('contenido_presentacion', '<=', 0)->update(['contenido_presentacion' => 1]);

        Schema::table('compra_detalle', function (Blueprint $table) {
            $table->enum('unidad_compra', ['PRESENTACION', 'MEDIDA'])->default('MEDIDA')->after('material_id');
            $table->decimal('contenido_presentacion', 12, 4)->default(1)->after('unidad_compra');
        });
    }

    public function down(): void
    {
        Schema::table('compra_detalle', function (Blueprint $table) {
            $table->dropColumn(['unidad_compra', 'contenido_presentacion']);
        });

        Schema::table('material', function (Blueprint $table) {
            $table->dropColumn('contenido_presentacion');
        });
    }
};
