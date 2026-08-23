<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitacora de avance por area/etapa: Diseno -> Elaboracion -> Acabado
     * -> Entrega, tal como lo define el flujo del proyecto.
     */
    public function up(): void
    {
        Schema::create('pedido_seguimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalle')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('area')->restrictOnDelete();
            $table->foreignId('empleado_id')->constrained('empleado')->restrictOnDelete();
            $table->enum('etapa', ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGA']);
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_fin')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_seguimiento');
    }
};
