<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Model\VaccineReferenceModel;
use Illuminate\Database\Seeder;

/**
 * Carga el catálogo de referencias de vacuna con su familia de producto,
 * que es el criterio de filtrado del listado de lotes.
 */
class VaccineReferenceSeeder extends Seeder
{
    /**
     * Referencias de ejemplo: nombre y familia de producto.
     *
     * @var list<array{name: string, family: string}>
     */
    private const REFERENCES = [
        ['name' => 'Vacuna conjugada intramuscular', 'family' => 'Vacunas conjugadas'],
        ['name' => 'Vacuna combinada trivalente', 'family' => 'Vacunas combinadas'],
        ['name' => 'Vacuna oral bivalente', 'family' => 'Vacunas orales'],
    ];

    public function run(): void
    {
        foreach (self::REFERENCES as $reference) {
            VaccineReferenceModel::query()->firstOrCreate(
                ['name' => $reference['name']],
                ['family' => $reference['family']],
            );
        }
    }
}
