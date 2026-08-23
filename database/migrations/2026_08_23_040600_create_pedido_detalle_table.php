<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copia de cotizacion_detalle al momento de crear el pedido: permite
     * que el pedido evolucione (estado_item, medidas reales) sin alterar
     * la cotizacion historica que le dio origen.
     */
    public function up(): void
    {
        Schema::create('pedido_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedido')->cascadeOnDelete();
            $table->foreignId('cotizacion_detalle_id')->constrained('cotizacion_detalle')->restrictOnDelete();
            $table->string('descripcion');
            $table->decimal('ancho', 10, 2)->nullable();
            $table->decimal('alto', 10, 2)->nullable();
            $table->decimal('cantidad', 10, 2);
            $table->enum('estado_item', ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO'])->default('DISENO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_detalle');
    }
};
