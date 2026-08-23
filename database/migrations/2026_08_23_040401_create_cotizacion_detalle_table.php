<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizacion')->cascadeOnDelete();
            // Nullable: un item personalizado (no catalogado) no tiene producto.
            $table->foreignId('producto_id')->nullable()->constrained('producto')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('ancho', 10, 2)->nullable();
            $table->decimal('alto', 10, 2)->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_detalle');
    }
};
