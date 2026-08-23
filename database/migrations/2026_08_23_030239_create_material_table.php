<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('material', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->decimal('precio_unitario', 10, 2);
            $table->foreignId('unidad_medida_id')->constrained('unidad_medida');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedor');
            $table->decimal('stock_minimo', 10, 2)->default(0);
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material');
    }
};
