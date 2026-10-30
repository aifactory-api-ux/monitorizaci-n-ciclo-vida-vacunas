<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de esquema: log estructurado e inmutable de operaciones críticas.
 *
 * Cada entrada se escribe por adición: no existe columna `updated_at` ni
 * operación de aplicación que modifique o borre una entrada (véase D10).
 * `batch_id` y `user_id` admiten valor nulo para registrar intentos que no
 * llegan a asociarse a un lote o a un usuario, como una importación fallida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LOG_ENTRY', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('batch_id', 36)->nullable();
            $table->string('user_id', 36)->nullable();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->timestamp('created_at')->nullable();

            $table->foreign('batch_id')
                ->references('id')
                ->on('VACCINE_BATCH')
                ->restrictOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('APP_USER')
                ->restrictOnDelete();

            $table->index('batch_id', 'idx_log_entry_batch');
            $table->index('user_id', 'idx_log_entry_user');
            $table->index('event_type', 'idx_log_entry_event');
            $table->index('created_at', 'idx_log_entry_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LOG_ENTRY');
    }
};
