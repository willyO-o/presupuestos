<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Muebles y exhibidores (cabeceras, islas, estantes) se cotizan en tres
     * dimensiones y por niveles: "0,70 × 1,75 × 0,30, 4 niveles".
     *
     * - `profundidad` (m) y `niveles` en la línea de cotización y en la del
     *   pedido (que copia la cotización al convertir). Opcionales: una lona
     *   o un sticker siguen siendo ancho × alto.
     * - `descripcion` pasa a TEXT en cotización, pedido y nota de entrega:
     *   las descripciones reales de un exhibidor rondan los 650 caracteres
     *   en varios párrafos, y viajan de una tabla a la siguiente.
     */
    public function up(): void
    {
        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            $table->text('descripcion')->change();
            $table->decimal('profundidad', 10, 2)->nullable()->after('alto');
            $table->unsignedSmallInteger('niveles')->nullable()->after('profundidad');
        });

        Schema::table('pedido_detalle', function (Blueprint $table) {
            $table->text('descripcion')->change();
            $table->decimal('profundidad', 10, 2)->nullable()->after('alto');
            $table->unsignedSmallInteger('niveles')->nullable()->after('profundidad');
        });

        Schema::table('nota_entrega_detalle', function (Blueprint $table) {
            $table->text('descripcion')->change();
        });
    }

    /**
     * Volver a VARCHAR(255) corta en MySQL cualquier descripción más larga:
     * solo revertir en un entorno donde eso no importe.
     */
    public function down(): void
    {
        Schema::table('nota_entrega_detalle', function (Blueprint $table) {
            $table->string('descripcion')->change();
        });

        Schema::table('pedido_detalle', function (Blueprint $table) {
            $table->dropColumn(['profundidad', 'niveles']);
            $table->string('descripcion')->change();
        });

        Schema::table('cotizacion_detalle', function (Blueprint $table) {
            $table->dropColumn(['profundidad', 'niveles']);
            $table->string('descripcion')->change();
        });
    }
};
