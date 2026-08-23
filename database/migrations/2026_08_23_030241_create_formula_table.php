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
        // Formula asociada a un tipo de producto (una expresion matematica, sin llaves)
        // ejemplo: 'ancho * alto * precio_m2'
        Schema::create('formula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_producto_id')->constrained('tipo_producto');
            $table->string('nombre', 100);
            $table->string('expresion', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formula');
    }
};
