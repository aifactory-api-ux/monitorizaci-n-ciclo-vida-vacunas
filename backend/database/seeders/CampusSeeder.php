<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\VaccineBatch\Entity\Campus;
use App\Infrastructure\Persistence\Eloquent\Model\CampusModel;
use Illuminate\Database\Seeder;

/**
 * Carga los campus de MSD: el de cultivo, el de formulación Sphereon y el
 * logístico. Los identificadores se generan en cada despliegue con UUID v4.
 */
class CampusSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([Campus::CULTURE, Campus::SPHEREON, Campus::LOGISTICS] as $name) {
            CampusModel::query()->firstOrCreate(['name' => $name]);
        }
    }
}
