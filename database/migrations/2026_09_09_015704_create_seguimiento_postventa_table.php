<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Último paso del flujo de la empresa que el sistema no cubría:
     * "Proceso 3 – Entrega y postventa: 5. Seguimiento 7 días" (y §11 del
     * documento "Proceso completo cliente a entrega": contactar al cliente
     * después de la entrega para verificar satisfacción y detectar
     * oportunidades futuras).
     *
     * Se crea automáticamente cuando un pedido queda ENTREGADO
     * (App\Services\Pedido\ProgramarPostventaService), con `fecha_programada`
     * = entrega + N días (`config/postventa.php`). Nace PENDIENTE y pasa a
     * REALIZADO cuando alguien registra el contacto; NO_CONTACTADO deja
     * constancia de que se intentó y no se pudo cerrar.
     *
     * 1:1 con el pedido (`unique`): un pedido entregado se sigue una vez.
     */
    public function up(): void
    {
        Schema::create('seguimiento_postventa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedido')->cascadeOnDelete();
            // Quién hizo el contacto: null mientras está pendiente.
            $table->foreignId('empleado_id')->nullable()->constrained('empleado')->nullOnDelete();
            $table->date('fecha_programada');
            $table->date('fecha_contacto')->nullable();
            $table->enum('estado', ['PENDIENTE', 'REALIZADO', 'NO_CONTACTADO'])->default('PENDIENTE');
            $table->enum('medio', ['LLAMADA', 'WHATSAPP', 'CORREO', 'VISITA'])->nullable();
            // 1 a 5, como la encuesta que se hace por teléfono.
            $table->unsignedTinyInteger('satisfaccion')->nullable();
            // SI = el cliente reportó un problema que hay que atender.
            $table->enum('requiere_accion', ['SI', 'NO'])->default('NO');
            // Oportunidad detectada para el equipo comercial.
            $table->text('oportunidad')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha_programada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_postventa');
    }
};
