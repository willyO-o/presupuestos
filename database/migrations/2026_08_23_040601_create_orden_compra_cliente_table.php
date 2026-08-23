<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documento formal (ej. "Orden de Compra 11021545") que el cliente
     * envia como respaldo del pedido. unique(pedido_id): a lo sumo una OC
     * por pedido (relacion one_to_one_optional declarada en schema.json,
     * pero sin la marca "unique" en la columna — se corrige aqui).
     */
    public function up(): void
    {
        Schema::create('orden_compra_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedido')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('cliente')->restrictOnDelete();
            $table->string('numero_oc');
            $table->date('fecha');
            $table->decimal('monto_total', 10, 2);
            $table->string('condicion_pago')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->enum('estado', ['PENDIENTE', 'VALIDADA', 'ANULADA'])->default('PENDIENTE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_compra_cliente');
    }
};
