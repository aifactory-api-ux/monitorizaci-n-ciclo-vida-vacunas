<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\VaccineBatch\Entity\Campus;
use App\Infrastructure\Persistence\Eloquent\Model\AppUserModel;
use App\Infrastructure\Persistence\Eloquent\Model\CampusModel;
use App\Infrastructure\Persistence\Eloquent\Model\VaccineReferenceModel;
use Database\Seeders\CampusSeeder;
use Database\Seeders\VaccineReferenceSeeder;
use Illuminate\Support\Str;

/**
 * Utilidades de datos de referencia para las pruebas de feature.
 */
trait InteractsWithDatabase
{
    /**
     * Da de alta los tres campus de MSD.
     */
    protected function seedCampuses(): void
    {
        $this->seed(CampusSeeder::class);
    }

    /**
     * Da de alta el catálogo de referencias de vacuna.
     */
    protected function seedVaccineReferences(): void
    {
        $this->seed(VaccineReferenceSeeder::class);
    }

    /**
     * Identificador UUID del campus indicado por su nombre.
     */
    protected function campusId(string $name): string
    {
        $campus = CampusModel::query()->where('name', $name)->first();

        $this->assertNotNull($campus, sprintf('El campus "%s" no está dado de alta.', $name));

        return (string) $campus->getKey();
    }

    /**
     * Identificador UUID de la referencia de vacuna indicada por su nombre.
     */
    protected function vaccineReferenceId(string $name): string
    {
        $reference = VaccineReferenceModel::query()->where('name', $name)->first();

        $this->assertNotNull($reference, sprintf('La referencia "%s" no está dada de alta.', $name));

        return (string) $reference->getKey();
    }

    /**
     * Crea un usuario con el rol indicado.
     */
    protected function createUser(string $role): AppUserModel
    {
        return AppUserModel::query()->create([
            'name' => $role,
            'email' => Str::uuid()->toString().'@msd.example',
            'password' => 'clave-de-prueba',
            'role' => $role,
        ]);
    }

    /**
     * Campus de cultivo de referencia.
     */
    protected function cultureCampusId(): string
    {
        return $this->campusId(Campus::CULTURE);
    }
}
