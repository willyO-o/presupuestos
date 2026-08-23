<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_verificacion')->unique();
            $table->foreignId('cliente_id')->constrained('cliente')->restrictOnDelete();
            $table->foreignId('empleado_id')->constrained('empleado')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursal')->restrictOnDelete();
            $table->date('fecha');
            $table->date('fecha_vencimiento')->nullable();
            $table->enum('estado', ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'CONVERTIDA', 'VENCIDA'])->default('PENDIENTE');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('impuesto', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion');
    }
};
