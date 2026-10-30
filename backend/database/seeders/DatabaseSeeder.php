<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Carga los datos de referencia del despliegue: campus, referencias de
 * vacuna, clientes OAuth y usuarios de los cinco roles operativos.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CampusSeeder::class,
            VaccineReferenceSeeder::class,
            PassportClientSeeder::class,
            AppUserSeeder::class,
        ]);
    }
}
