<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro historico de precios de cada material: clave para el
     * analisis BI de evolucion de costos y para no alterar
     * cotizaciones/pedidos ya cerrados al cambiar un precio. Es un log de
     * solo escritura, por eso no lleva `updated_at`.
     */
    public function up(): void
    {
        Schema::create('historial_precio_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('material')->cascadeOnDelete();
            $table->decimal('precio_presentacion', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->date('vigente_desde');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['material_id', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_precio_material');
    }
};
