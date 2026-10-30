<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuración de los reportes de trazabilidad
|--------------------------------------------------------------------------
|
| El reporte de trazabilidad se puede exportar en los formatos declarados
| aquí. Cada formato dispone de un exportador de infraestructura que
| serializa el modelo de trazabilidad del dominio.
|
| Los formatos declarados son los que el registro de exportadores expone a
| la API: `GET /api/v1/reports/traceability/{batchId}` devuelve el modelo en
| JSON y las variantes con sufijo `.csv` y `.pdf` devuelven el fichero
| exportado con el tipo de contenido que declara su exportador.
|
*/

return [
    /*
     | Formatos de exportación habilitados para el reporte de trazabilidad.
     */
    'formats' => [
        'csv',
        'pdf',
    ],
];
