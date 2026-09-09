<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estimaciones que un visitante arma solo, desde `/cotizador`, sin login.
     *
     * Tabla aparte de `cotizacion` y NO una fila más de ella, por tres
     * razones que no se pueden salvar con un `estado` extra:
     *
     * 1. `cotizacion.cliente_id` es NOT NULL y apunta al maestro de clientes.
     *    Un visitante anónimo no es un cliente: crear una ficha por cada
     *    curioso ensuciaría el maestro que alimenta reportes y cobranza.
     * 2. Un endpoint público recibe bots. Sus filas no pueden mezclarse con
     *    los presupuestos reales ni contaminar el BI.
     * 3. El precio es APROXIMADO y calculado sin revisión humana. Un
     *    presupuesto de `cotizacion` es un documento que la empresa sostiene;
     *    esto es una referencia con fecha de caducidad.
     *
     * Cuando ventas la atiende, se emite una `cotizacion` de verdad y se
     * enlaza acá (`cotizacion_id`), que es lo que cierra el circuito.
     */
    public function up(): void
    {
        Schema::create('cotizacion_publica', function (Blueprint $table) {
            $table->id();
            // Lo único que el visitante se lleva para retomar la conversación.
            $table->string('codigo')->unique();

            // Datos de contacto: es también la captura del interesado.
            $table->string('nombre');
            $table->string('empresa')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono');
            $table->text('mensaje')->nullable();

            /*
             * Foto del detalle estimado (producto, medidas, cantidad, precio
             * unitario y subtotal por línea). Va como JSON y no como tabla
             * hija porque es un documento inmutable: nunca se edita línea por
             * línea, no se consulta por producto y muere al vencer o al
             * convertirse en una cotización real.
             */
            $table->json('detalle');

            $table->decimal('subtotal', 10, 2);
            $table->decimal('iva', 10, 2);
            $table->decimal('total', 10, 2);

            // Rango mostrado al visitante: total ± holgura.
            $table->decimal('estimado_min', 10, 2);
            $table->decimal('estimado_max', 10, 2);
            $table->decimal('holgura', 5, 4);

            /*
             * La vigencia se guarda además de la fecha: `config/cotizador.php`
             * puede cambiar mañana y esta estimación tiene que seguir
             * explicando la promesa con la que se emitió.
             */
            $table->unsignedSmallInteger('vigencia_dias');
            $table->date('fecha_vencimiento');

            $table->enum('estado', ['NUEVA', 'CONTACTADA', 'CONVERTIDA', 'DESCARTADA'])->default('NUEVA');

            // Presupuesto formal emitido a partir de esta solicitud, si lo hubo.
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizacion')->nullOnDelete();

            // Rastro mínimo para investigar abuso. IPv6 entra en 45 caracteres.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // La bandeja de ventas: las nuevas primero.
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_publica');
    }
};
