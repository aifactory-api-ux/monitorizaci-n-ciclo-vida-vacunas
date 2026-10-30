<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Model\AppUserModel;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Carga los usuarios de los cinco roles operativos.
 *
 * La contraseña se recibe del entorno (`APP_USER_SEED_PASSWORD`) porque la
 * inyecta el gestor de secretos del despliegue: no hay ninguna credencial
 * versionada en el repositorio (véase D16).
 */
class AppUserSeeder extends Seeder
{
    /**
     * Usuarios de los cinco roles, con su nombre y su correo.
     *
     * @var list<array{name: string, email: string, role: string}>
     */
    private const USERS = [
        [
            'name' => 'Operador de Producción',
            'email' => 'operador.produccion@msd.example',
            'role' => 'Operador de Producción',
        ],
        [
            'name' => 'Técnico de Control de Calidad',
            'email' => 'tecnico.calidad@msd.example',
            'role' => 'Técnico de Control de Calidad',
        ],
        [
            'name' => 'Coordinador de Logística',
            'email' => 'coordinador.logistica@msd.example',
            'role' => 'Coordinador de Logística',
        ],
        [
            'name' => 'Supervisor de Planta',
            'email' => 'supervisor.planta@msd.example',
            'role' => 'Supervisor de Planta',
        ],
        [
            'name' => 'Auditor / Administrador IT',
            'email' => 'auditor.it@msd.example',
            'role' => 'Auditor / Administrador IT',
        ],
    ];

    public function run(): void
    {
        $password = (string) env('APP_USER_SEED_PASSWORD', '');

        if ($password === '') {
            throw new RuntimeException(
                'Falta la variable APP_USER_SEED_PASSWORD: debe inyectarse desde el gestor de secretos del despliegue.',
            );
        }

        foreach (self::USERS as $user) {
            AppUserModel::query()->firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => $password,
                    'role' => $user['role'],
                ],
            );
        }
    }
}
