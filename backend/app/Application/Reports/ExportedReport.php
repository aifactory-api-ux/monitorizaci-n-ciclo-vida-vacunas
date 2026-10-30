<?php

declare(strict_types=1);

namespace App\Application\Reports;

/**
 * Exportación de un reporte ya serializado.
 */
final class ExportedReport
{
    public function __construct(
        private readonly string $content,
        private readonly string $filename,
        private readonly string $contentType,
    ) {}

    /**
     * Contenido serializado de la exportación.
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Nombre del fichero de la exportación.
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * Tipo de contenido MIME de la exportación.
     */
    public function contentType(): string
    {
        return $this->contentType;
    }
}
