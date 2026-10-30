<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de esquema: catálogo de campus de MSD.
 *
 * El nombre del campus es único porque el dominio resuelve el campus de cada
 * fase por su nombre ('Campus 1', 'Campus 2', 'Campus 3').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('CAMPUS', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CAMPUS');
    }
};
