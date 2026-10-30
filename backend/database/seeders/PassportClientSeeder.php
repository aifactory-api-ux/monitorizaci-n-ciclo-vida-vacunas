<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * Carga el cliente OAuth de contraseña del panel.
 *
 * El panel obtiene su token JWT en `POST /api/v1/auth/token` mediante el flujo
 * de contraseña de OAuth 2.0, que exige un cliente registrado con ese tipo de
 * concesión (véase D9).
 */
class PassportClientSeeder extends Seeder
{
    /**
     * Nombre del cliente de contraseña del panel.
     */
    private const CLIENT_NAME = 'Dashboard de Monitorización del Ciclo de Vida de Vacunas';

    /**
     * Proveedor de usuarios al que está asociado el cliente.
     */
    private const PROVIDER = 'users';

    public function run(): void
    {
        if ($this->passwordClientExists()) {
            return;
        }

        app(ClientRepository::class)->createPasswordGrantClient(self::CLIENT_NAME, self::PROVIDER);
    }

    /**
     * Indica si ya existe un cliente de contraseña activo.
     */
    private function passwordClientExists(): bool
    {
        return Passport::client()
            ->newQuery()
            ->where('revoked', false)
            ->get()
            ->contains(fn (Client $client): bool => $client->hasGrantType('password'));
    }
}
