<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BOM (bill of materials): lista de materiales que consume un producto
     * por cada unidad/m2. Con esto el sistema calcula
     * costo_material = SUM(cantidad_por_unidad * precio_unitario_material)
     * en vez de que el vendedor lo calcule a mano.
     */
    public function up(): void
    {
        Schema::create('producto_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('producto')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('material')->restrictOnDelete();
            $table->decimal('cantidad_por_unidad', 10, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_material');
    }
};
