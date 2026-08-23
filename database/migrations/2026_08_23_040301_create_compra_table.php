<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedor')->restrictOnDelete();
            $table->foreignId('empleado_id')->constrained('empleado')->restrictOnDelete();
            $table->string('numero_factura')->nullable();
            $table->date('fecha');
            $table->decimal('total', 10, 2);
            $table->enum('estado', ['PENDIENTE', 'PAGADA', 'ANULADA'])->default('PENDIENTE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra');
    }
};
