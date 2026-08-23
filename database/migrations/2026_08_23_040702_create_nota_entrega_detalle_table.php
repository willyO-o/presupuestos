<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_entrega_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_entrega_id')->constrained('nota_entrega')->cascadeOnDelete();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalle')->restrictOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad_entregada', 10, 2);
            $table->string('ubicacion')->nullable();
            $table->string('foto_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_entrega_detalle');
    }
};
