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
        // Lista de materiales (BOM) para productos que se calculan por materiales, ej: muebles
        Schema::create('formula_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained('formula')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('material');
            $table->decimal('cantidad_por_unidad', 10, 3);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formula_material');
    }
};
