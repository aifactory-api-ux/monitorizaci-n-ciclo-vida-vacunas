<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;

/**
 * Arranque de la aplicación para las pruebas.
 *
 * La clave de cifrado se genera en memoria cuando el entorno no la aporta, de
 * modo que la suite no dependa de un `.env` versionado ni de un secreto
 * fijo en el repositorio.
 */
trait CreatesApplication
{
    /**
     * Crea y arranca la aplicación Laravel.
     */
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if (blank(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }

        $this->ensurePassportKeys();

        return $app;
    }

    /**
     * Genera las claves de firma de Passport cuando no están disponibles.
     *
     * La suite emite y valida tokens JWT reales, por lo que necesita las
     * claves de firma. Se generan en el almacenamiento local de la aplicación
     * y no se versionan (véase D9).
     */
    private function ensurePassportKeys(): void
    {
        if (file_exists(Passport::keyPath('oauth-private.key'))) {
            return;
        }

        Artisan::call('passport:keys', ['--force' => true]);
    }
}
