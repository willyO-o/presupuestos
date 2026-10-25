<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién firma cada documento en nombre de la empresa (orden de trabajo, nota
 * de entrega, cotización). Una fila por firma (`clave`, ver
 * FirmaDocumento::FIRMAS); si una firma no tiene fila, firma el propietario
 * de `config('sitio.empresa')`. Se edita en Administración → Firmas de
 * documentos, para cuando se asigne un responsable de producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firma_documento', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 50)->unique();
            $table->string('nombre', 150);
            $table->string('cargo', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firma_documento');
    }
};
