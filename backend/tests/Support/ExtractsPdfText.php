<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Extracción del texto de un documento PDF.
 *
 * Los exportadores producen documentos con fuentes incrustadas de forma
 * opcional, por lo que el texto se extrae de los flujos de contenido
 * descomprimidos del fichero.
 */
trait ExtractsPdfText
{
    /**
     * Texto del documento PDF, una línea por cada texto dibujado.
     */
    protected function pdfText(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches);

        $text = '';

        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);

            if ($decoded === false) {
                $decoded = $stream;
            }

            if (! str_contains($decoded, 'BT')) {
                continue;
            }

            preg_match_all('/\(((?:\\\\.|[^\\\\()])*)\)\s*\]?\s*T[Jj]/', $decoded, $literals);

            foreach ($literals[1] as $literal) {
                $text .= $this->unescapePdfLiteral($literal)."\n";
            }
        }

        return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    /**
     * Resuelve las secuencias de escape de una cadena literal del PDF.
     */
    private function unescapePdfLiteral(string $literal): string
    {
        $resolved = preg_replace_callback(
            '/\\\\([0-7]{1,3})/',
            fn (array $matches): string => chr((int) octdec($matches[1])),
            $literal,
        );

        return str_replace(
            ['\\(', '\\)', '\\\\'],
            ['(', ')', '\\'],
            $resolved ?? $literal,
        );
    }
}
