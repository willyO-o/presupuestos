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
        // Una fila por llamada a un modelo de IA: con qué proveedor y modelo
        // respondió, cuántos tokens y cuánto costó. Es la base del tope de
        // gasto mensual (config/ia.php) y de la auditoría de uso.
        Schema::create('ia_uso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('funcionalidad', 40);
            $table->string('proveedor', 30)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);
            $table->unsignedInteger('tokens_cache')->default(0);
            $table->decimal('costo_usd', 10, 6)->default(0);
            $table->unsignedInteger('latencia_ms')->default(0);
            $table->enum('estado', ['OK', 'ERROR', 'SIN_PRESUPUESTO', 'NO_CONFIGURADA']);
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'estado']);
            $table->index('funcionalidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ia_uso');
    }
};
