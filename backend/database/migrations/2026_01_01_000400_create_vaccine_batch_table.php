<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de esquema: lote de vacuna biológica.
 *
 * `phase` y `status` materializan la máquina de estados del lote y
 * `current_campus_id` su ubicación vigente entre campus. Las columnas
 * consultadas por el listado y por la búsqueda avanzada (véase
 * `batch-search-filter`) están indexadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('VACCINE_BATCH', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('reference_id', 36);
            $table->string('phase', 50);
            $table->string('status', 50);
            $table->string('current_campus_id', 36);
            $table->timestamps();

            $table->foreign('reference_id')
                ->references('id')
                ->on('VACCINE_REFERENCE')
                ->restrictOnDelete();

            $table->foreign('current_campus_id')
                ->references('id')
                ->on('CAMPUS')
                ->restrictOnDelete();

            $table->index('reference_id', 'idx_vaccine_batch_reference');
            $table->index('phase', 'idx_vaccine_batch_phase');
            $table->index('status', 'idx_vaccine_batch_status');
            $table->index('current_campus_id', 'idx_vaccine_batch_campus');
            $table->index('created_at', 'idx_vaccine_batch_created_at');
            $table->index(['phase', 'status'], 'idx_vaccine_batch_phase_status');

            // Índices compuestos de la búsqueda avanzada: la fase con la
            // fecha de alta sostienen el orden estable del listado y la
            // referencia con la fase acotan el filtro por familia de producto
            // (véase D14).
            $table->index(['phase', 'created_at', 'id'], 'idx_vaccine_batch_phase_created');
            $table->index(['reference_id', 'phase', 'created_at'], 'idx_vaccine_batch_reference_phase_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('VACCINE_BATCH');
    }
};
