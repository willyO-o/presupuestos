<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedido')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->date('fecha_pago');
            $table->enum('metodo_pago', ['EFECTIVO', 'TRANSFERENCIA', 'QR', 'TARJETA', 'CHEQUE']);
            $table->enum('estado', ['PENDIENTE', 'PAGADO', 'PARCIAL'])->default('PENDIENTE');
            $table->string('comprobante_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago');
    }
};
