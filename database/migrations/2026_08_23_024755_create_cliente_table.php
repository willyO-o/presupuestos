<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cliente', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_cliente', ['natural', 'juridico']);
            $table->foreignId('persona_id')->nullable()->constrained('persona');
            $table->foreignId('empresa_id')->nullable()->constrained('empresa');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->date('fecha_registro')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
        });

        // Blueprint no tiene un metodo check() nativo: el constraint se agrega
        // con SQL crudo despues de crear la tabla. SQLite no soporta ADD
        // CONSTRAINT via ALTER TABLE (solo al crear la tabla), y es el motor
        // que usan los tests (phpunit.xml, DB_CONNECTION=sqlite :memory:) —
        // ahi se omite y la regla queda solo a nivel de aplicacion.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
                ALTER TABLE cliente ADD CONSTRAINT chk_cliente_tipo CHECK (
                    (tipo_cliente = 'natural' AND persona_id IS NOT NULL AND empresa_id IS NULL)
                    OR
                    (tipo_cliente = 'juridico' AND empresa_id IS NOT NULL AND persona_id IS NULL)
                )
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};
