<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursal')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('area')->restrictOnDelete();
            $table->string('nombre_completo');
            $table->string('ci');
            $table->string('cargo');
            $table->string('telefono')->nullable();
            $table->date('fecha_ingreso');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO');
            $table->timestamps();

            $table->unique('ci');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado');
    }
};
