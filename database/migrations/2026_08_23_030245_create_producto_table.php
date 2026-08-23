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
        // Catalogo de productos concretos que se muestran al cliente / se cotizan
        Schema::create('producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_producto_id')->constrained('tipo_producto');
            $table->foreignId('formula_id')->constrained('formula');
            $table->string('nombre', 150);
            $table->string('descripcion', 255)->nullable();
            $table->string('imagen', 255)->nullable();
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
        Schema::dropIfExists('producto');
    }
};
