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
        // Anomalías detectadas por reglas estadísticas. `huella` evita repetir
        // la misma alerta cada día (tipo + entidad + periodo); `estado` deja
        // que gerencia la marque como revisada o descartada.
        Schema::create('bi_anomalia', function (Blueprint $table) {
            $table->id();
            $table->string('huella', 150)->unique();
            $table->string('tipo', 40);
            $table->enum('severidad', ['ALTA', 'MEDIA']);
            $table->string('titulo');
            $table->text('detalle');
            $table->text('que_revisar')->nullable();
            $table->json('hechos');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursal')->nullOnDelete();
            $table->string('url')->nullable();
            $table->enum('redaccion', ['IA', 'PLANTILLA'])->default('PLANTILLA');
            $table->enum('estado', ['NUEVA', 'REVISADA', 'DESCARTADA'])->default('NUEVA');
            $table->date('detectada_en');
            $table->foreignId('revisada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisada_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'detectada_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bi_anomalia');
    }
};
