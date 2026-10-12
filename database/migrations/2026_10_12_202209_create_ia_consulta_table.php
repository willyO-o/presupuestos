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
        // Historial visible del asistente de BI: la pregunta, la respuesta y
        // los resultados de las herramientas que usó (para redibujar los
        // gráficos y el "cómo lo calculé" al volver a abrir el panel).
        Schema::create('ia_consulta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('conversacion_id', 36)->nullable()->index();
            $table->text('pregunta');
            $table->longText('respuesta')->nullable();
            $table->json('resultados')->nullable();
            $table->string('proveedor', 30)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->decimal('costo_usd', 10, 6)->default(0);
            $table->enum('estado', ['OK', 'ERROR']);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ia_consulta');
    }
};
