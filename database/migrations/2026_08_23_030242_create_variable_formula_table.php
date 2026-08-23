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
        // Variables que necesita cada formula (genera el formulario dinamico)
        Schema::create('variable_formula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained('formula')->cascadeOnDelete();
            $table->string('codigo', 50); // debe coincidir con el nombre usado en la expresion
            $table->string('etiqueta', 100);
            $table->foreignId('unidad_medida_id')->nullable()->constrained('unidad_medida');
            $table->enum('tipo_dato', ['NUMERO', 'TEXTO'])->default('NUMERO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variable_formula');
    }
};
