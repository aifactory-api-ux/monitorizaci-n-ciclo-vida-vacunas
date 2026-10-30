<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de esquema: registro inmutable de transiciones de fase.
 *
 * Cada fila documenta el passage de un lote de una fase a la siguiente con
 * su fecha y hora. Las filas no admiten modificación ni borrado: el
 * repositorio de aplicación sólo expone la operación de alta (véase D4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PHASE_TRANSITION', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('batch_id', 36);
            $table->string('from_phase', 50);
            $table->string('to_phase', 50);
            $table->timestamp('transition_date');

            $table->foreign('batch_id')
                ->references('id')
                ->on('VACCINE_BATCH')
                ->restrictOnDelete();

            $table->index('batch_id', 'idx_phase_transition_batch');
            $table->index('transition_date', 'idx_phase_transition_date');
            $table->index(['batch_id', 'transition_date'], 'idx_phase_transition_batch_date');

            // Índice compuesto del cálculo de los tiempos de ciclo: la fase
            // de origen con la fecha delimitan la permanencia del lote en
            // cada fase (véase D21).
            $table->index(['batch_id', 'from_phase', 'transition_date'], 'idx_phase_transition_batch_phase_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PHASE_TRANSITION');
    }
};
