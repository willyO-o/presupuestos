<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_producto_id')->constrained('categoria_producto')->restrictOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('unidad_medida', ['M2', 'UNIDAD', 'METRO_LINEAL']);
            $table->decimal('precio_base', 10, 2)->nullable();
            $table->enum('requiere_medidas', ['SI', 'NO'])->default('SI');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto');
    }
};
