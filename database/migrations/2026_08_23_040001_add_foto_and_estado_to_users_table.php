<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extiende la tabla nativa `users` (no se toca su migracion original,
     * ver .ai/rules/migrations.md) con los campos que pide el esquema:
     * foto de perfil y estado de acceso (controla si el usuario puede
     * iniciar sesion).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('password');
            $table->enum('estado', ['ACTIVO', 'INACTIVO'])->default('ACTIVO')->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['foto', 'estado']);
        });
    }
};
