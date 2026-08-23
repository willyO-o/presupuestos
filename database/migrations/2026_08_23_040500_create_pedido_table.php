<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido', function (Blueprint $table) {
            $table->id();
            // unique(): 1 cotizacion aprobada genera a lo sumo 1 pedido.
            $table->foreignId('cotizacion_id')->unique()->constrained('cotizacion')->restrictOnDelete();
            $table->string('numero_pedido')->unique();
            $table->date('fecha_pedido');
            $table->date('fecha_entrega_estimada')->nullable();
            $table->date('fecha_entrega_real')->nullable();
            $table->enum('estado', ['DISENO', 'ELABORACION', 'ACABADO', 'ENTREGADO', 'CANCELADO'])->default('DISENO');
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido');
    }
};
