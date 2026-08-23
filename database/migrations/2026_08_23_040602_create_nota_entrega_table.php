<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_entrega', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedido')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleado')->restrictOnDelete();
            $table->string('numero_nota')->unique();
            $table->date('fecha_entrega');
            $table->string('recibido_por')->nullable();
            $table->string('cargo_receptor')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_entrega');
    }
};
