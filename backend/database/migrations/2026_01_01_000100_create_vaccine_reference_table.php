<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de esquema: catálogo de referencias de vacuna.
 *
 * `family` es la familia de producto por la que se filtra el listado de
 * lotes (véase `batch-search-filter`), por lo que dispone de índice propio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('VACCINE_REFERENCE', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('name', 150);
            $table->string('family', 100);
            $table->timestamps();

            $table->index('family', 'idx_vaccine_reference_family');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('VACCINE_REFERENCE');
    }
};
