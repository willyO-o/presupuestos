<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina `orden_compra_cliente.cliente_id` (2026-09-09).
     *
     * El cliente de una OC siempre es el de su pedido
     * (`pedido → cotizacion → cliente`), así que la columna era una FK
     * redundante: nada impedía que apuntara a un cliente distinto del que
     * firmó la cotización. Ahora el cliente se lee por la relación
     * `OrdenCompraCliente::cliente()` (hasOneThrough), que no puede mentir.
     *
     * `monto_total` SÍ se conserva: es el importe que dice el documento del
     * cliente, y que puede legítimamente no coincidir con el total del
     * pedido — detectar esa diferencia es justamente para lo que sirve
     * validar una OC.
     */
    public function up(): void
    {
        Schema::table('orden_compra_cliente', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('orden_compra_cliente', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('pedido_id')
                ->constrained('cliente')->restrictOnDelete();
        });

        // Se reconstruye desde el pedido, que es de donde salía.
        DB::statement('UPDATE orden_compra_cliente oc
            JOIN pedido p ON p.id = oc.pedido_id
            JOIN cotizacion c ON c.id = p.cotizacion_id
            SET oc.cliente_id = c.cliente_id');
    }
};
