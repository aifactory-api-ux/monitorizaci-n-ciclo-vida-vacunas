<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Domain\Security\Port\SecurityStageScanner;
use App\Domain\Security\ValueObject\SecurityStageResult;

/**
 * Doble de un analizador de seguridad.
 *
 * Sustituye al analizador que ejecuta el comando del despliegue durante las
 * pruebas: devuelve el resultado preparado, de modo que el bloqueo de la
 * promoción se pueda verificar sin ejecutar el análisis real.
 */
final class FakeSecurityStageScanner implements SecurityStageScanner
{
    /**
     * @param  SecurityStageResult  $result  Resultado que devuelve el analizador.
     */
    public function __construct(private readonly SecurityStageResult $result) {}

    public function scan(): SecurityStageResult
    {
        return $this->result;
    }
}
