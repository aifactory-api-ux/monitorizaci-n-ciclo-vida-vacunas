<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de esquema: usuarios de la aplicación.
 *
 * `role` es el rol operativo que aplica la matriz de permisos (Operador de
 * Producción, Técnico de Control de Calidad, Coordinador de Logística,
 * Supervisor de Planta y Auditor / Administrador IT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('APP_USER', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('name', 150);
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('role', 50);
            $table->timestamps();

            $table->index('role', 'idx_app_user_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('APP_USER');
    }
};
