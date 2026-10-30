<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de esquema: resultados microbiológicos importados de LIMS Labware.
 *
 * `approved_by` identifica a quien aprobó el resultado microbiológico y es
 * la condición que exige el gating de la fase logística (véase `lims-gating`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LIMS_RESULT', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('batch_id', 36);
            $table->string('test_type', 100);
            $table->string('result', 50);
            $table->string('approved_by', 150)->nullable();
            $table->timestamp('result_date');

            $table->foreign('batch_id')
                ->references('id')
                ->on('VACCINE_BATCH')
                ->restrictOnDelete();

            $table->index('batch_id', 'idx_lims_result_batch');
            $table->index(['batch_id', 'test_type'], 'idx_lims_result_batch_test');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LIMS_RESULT');
    }
};
