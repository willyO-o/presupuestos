<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consumo REAL de materiales por item de pedido: se compara contra el
     * BOM presupuestado en producto_material (costo estimado vs. real),
     * insumo clave para el modulo de Inteligencia de Negocios.
     */
    public function up(): void
    {
        Schema::create('pedido_detalle_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalle')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('material')->restrictOnDelete();
            $table->decimal('cantidad_usada', 10, 2);
            $table->decimal('costo_real', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_detalle_material');
    }
};
