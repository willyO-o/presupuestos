<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Insumos de una línea de cotización: las columnas A–E de la hoja de
     * costos (Tipo, Descripción, Cantidad, Costo Unit., Subtotal). Filas
     * dinámicas — cualquier cantidad de insumos por línea, no una lista fija
     * de 5 o 6 renglones como en el Excel.
     *
     * Su suma es el `costo_base` (por unidad) de la línea. Se pueden traer
     * automáticamente desde la receta/BOM del producto (`producto_material`,
     * vía PrecioSugeridoService) o escribirse a mano: por eso `material_id`
     * es opcional — la mano de obra y los servicios de terceros no son
     * materiales del inventario.
     */
    public function up(): void
    {
        Schema::create('cotizacion_detalle_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_detalle_id')->constrained('cotizacion_detalle')->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('material')->nullOnDelete();
            $table->enum('tipo', ['MATERIAL', 'MANO_OBRA', 'IMPRESION', 'SERVICIO', 'OTRO'])->default('MATERIAL');
            $table->string('descripcion');
            $table->string('unidad')->nullable();
            // 4 decimales: una receta puede consumir 0,0725 m² de una plancha.
            $table->decimal('cantidad', 12, 4);
            $table->decimal('costo_unitario', 12, 4);
            $table->decimal('subtotal', 12, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_detalle_item');
    }
};
