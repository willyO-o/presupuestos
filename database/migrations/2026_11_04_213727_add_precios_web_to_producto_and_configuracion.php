<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El dueño administra el cotizador web desde el panel, sin tocar el código
 * ni el `.env`.
 *
 * - `producto.modo_precio_web`: de dónde sale el precio que ve el visitante.
 *   MATERIALES = el mismo costeo que ventas (costo base o receta) + margen;
 *   PRECIO_FIJO = `precio_web` por m², metro lineal o unidad; RANGOS = precio
 *   por m² según el área del trabajo (tabla `producto_precio_web`).
 * - `producto.precio_web`: precio de venta sin IVA por unidad de medida.
 * - `producto_precio_web`: rangos de área de un producto. `area_hasta` null
 *   es el último rango, sin tope.
 * - `configuracion`: ajustes editables (clave → valor) que antes solo
 *   vivían en `config/cotizador.php`. Sin fila, manda el config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->enum('modo_precio_web', ['MATERIALES', 'PRECIO_FIJO', 'RANGOS'])
                ->default('MATERIALES')
                ->after('cotizable_web');
            $table->decimal('precio_web', 10, 2)->nullable()->after('modo_precio_web');
        });

        Schema::create('producto_precio_web', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('producto')->cascadeOnDelete();
            $table->decimal('area_hasta', 8, 2)->nullable();
            $table->decimal('precio_m2', 10, 2);
            $table->timestamps();
        });

        Schema::create('configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion');
        Schema::dropIfExists('producto_precio_web');

        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn(['modo_precio_web', 'precio_web']);
        });
    }
};
