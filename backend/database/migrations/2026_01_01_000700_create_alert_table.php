<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de esquema: alertas operativas de prioridad máxima.
 *
 * El contrato no contempla estado de alerta: la severidad persistida
 * representa el estado de la alerta (véase D6). `batch_id` y `severity`
 * soportan el filtro por estado de alerta del listado de lotes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ALERT', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('batch_id', 36);
            $table->string('alert_type', 100);
            $table->string('severity', 50);
            $table->text('message');
            $table->timestamp('created_at')->nullable();

            $table->foreign('batch_id')
                ->references('id')
                ->on('VACCINE_BATCH')
                ->restrictOnDelete();

            $table->index('batch_id', 'idx_alert_batch');
            $table->index('severity', 'idx_alert_severity');
            $table->index(['batch_id', 'severity'], 'idx_alert_batch_severity');

            // Índice compuesto del filtro por estado de alerta: la severidad
            // persistida representa el estado de la alerta (véase D6 y D14).
            $table->index(['batch_id', 'severity', 'created_at'], 'idx_alert_batch_severity_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ALERT');
    }
};
