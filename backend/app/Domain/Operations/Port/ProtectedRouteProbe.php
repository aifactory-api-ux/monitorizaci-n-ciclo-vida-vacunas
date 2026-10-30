<?php

declare(strict_types=1);

namespace App\Domain\Operations\Port;

/**
 * Sonda de una ruta de datos protegida por el middleware de autenticación.
 *
 * La sonda emite la petición sin cabecera `Authorization` y devuelve el
 * código de estado con el que responde la API, que debe ser 401 (véase D9 y
 * NFR003).
 */
interface ProtectedRouteProbe
{
    /**
     * Código de estado que responde una ruta de datos sin token Bearer.
     */
    public function statusCode(): int;
}
