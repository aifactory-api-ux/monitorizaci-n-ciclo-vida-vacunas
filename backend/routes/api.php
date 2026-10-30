<?php

declare(strict_types=1);

use App\Api\Http\Controllers\V1\AlertController;
use App\Api\Http\Controllers\V1\AuditController;
use App\Api\Http\Controllers\V1\AuthController;
use App\Api\Http\Controllers\V1\BatchController;
use App\Api\Http\Controllers\V1\DashboardController;
use App\Api\Http\Controllers\V1\GatingController;
use App\Api\Http\Controllers\V1\HealthController;
use App\Api\Http\Controllers\V1\LimsController;
use App\Api\Http\Controllers\V1\PhaseTransitionController;
use App\Api\Http\Controllers\V1\ReportController;
use App\Api\Http\Controllers\V1\SearchController;
use App\Api\Http\Controllers\V1\TraceabilityController;
use App\Application\Authorization\OperationPolicy;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| El contrato expone los endpoints bajo el prefijo `/api/v1`. El sondeo de
| disponibilidad y la emisión del token son públicos; el resto de rutas de
| datos exige un token Bearer válido (`auth:api`) y las de escritura aplican
| además la autorización por rol y la auditoría de la petición (véase D9).
|
*/

Route::prefix('v1')->group(function (): void {
    // El sondeo de disponibilidad es público: excluye `auth:api` del grupo
    // `api`, que protege el resto de rutas de datos.
    Route::get('/health', HealthController::class)
        ->withoutMiddleware('auth:api')
        ->name('api.v1.health');

    // La emisión del token es pública: es el punto de entrada al panel. No
    // se audita porque no modifica datos del dominio.
    Route::post('/auth/token', [AuthController::class, 'store'])
        ->withoutMiddleware('auth:api')
        ->name('api.v1.auth.token');

    // Ciclo de vida del lote de vacuna.
    Route::post('/batches', [BatchController::class, 'store'])
        ->middleware(['audit.request', 'role:'.OperationPolicy::BATCH_CREATE])
        ->name('api.v1.batches.store');
    Route::get('/batches', [BatchController::class, 'index'])
        ->name('api.v1.batches.index');
    Route::get('/batches/{batchId}', [BatchController::class, 'show'])
        ->name('api.v1.batches.show');
    Route::get('/batches/{batchId}/traceability', [TraceabilityController::class, 'show'])
        ->name('api.v1.batches.traceability');
    Route::post('/batches/{batchId}/transitions', [PhaseTransitionController::class, 'store'])
        ->middleware(['audit.request', 'role:'.OperationPolicy::BATCH_ADVANCE_PHASE])
        ->name('api.v1.batches.transitions.store');
    Route::post('/batches/{batchId}/reception', [BatchController::class, 'reception'])
        ->middleware(['audit.request', 'role:'.OperationPolicy::BATCH_REGISTER_PHYSICAL_RECEPTION])
        ->name('api.v1.batches.reception.store');
    Route::post('/batches/{batchId}/logistics-close', [BatchController::class, 'logisticsClose'])
        ->middleware(['audit.request', 'role:'.OperationPolicy::BATCH_CLOSE_LOGISTICS_CYCLE])
        ->name('api.v1.batches.logistics-close.store');

    // Integración con LIMS Labware y gating microbiológico.
    Route::post('/lims/import', [LimsController::class, 'import'])
        ->middleware(['audit.request', 'role:'.OperationPolicy::LIMS_IMPORT])
        ->name('api.v1.lims.import');
    Route::post('/batches/{batchId}/lims-results', [LimsController::class, 'store'])
        ->middleware(['audit.request', 'role:'.OperationPolicy::LIMS_REGISTER_RESULT])
        ->name('api.v1.batches.lims-results.store');
    Route::get('/batches/{batchId}/gating', [GatingController::class, 'show'])
        ->name('api.v1.batches.gating.show');
    Route::post('/batches/{batchId}/gating/release', [GatingController::class, 'release'])
        ->middleware(['audit.request', 'role:'.OperationPolicy::GATING_RELEASE])
        ->name('api.v1.batches.gating.release');

    // Alertas operativas de prioridad máxima: consulta del listado y del
    // histórico. La generación de las alertas es automática.
    Route::get('/alerts', [AlertController::class, 'index'])
        ->name('api.v1.alerts.index');
    Route::get('/alerts/history', [AlertController::class, 'history'])
        ->name('api.v1.alerts.history');
    Route::get('/batches/{batchId}/alerts', [AlertController::class, 'forBatch'])
        ->name('api.v1.batches.alerts.index');

    // Reporte de trazabilidad del lote. Las variantes con sufijo de formato se
    // registran antes de la ruta genérica y acotan el identificador del lote
    // a un UUID, de modo que el sufijo no forme parte del parámetro.
    Route::get('/reports/traceability/{batchId}.csv', [ReportController::class, 'csv'])
        ->where('batchId', '[0-9a-fA-F-]{36}')
        ->name('api.v1.reports.traceability.csv');
    Route::get('/reports/traceability/{batchId}.pdf', [ReportController::class, 'pdf'])
        ->where('batchId', '[0-9a-fA-F-]{36}')
        ->name('api.v1.reports.traceability.pdf');
    Route::get('/reports/traceability/{batchId}', [ReportController::class, 'show'])
        ->where('batchId', '[0-9a-fA-F-]{36}')
        ->name('api.v1.reports.traceability.show');

    // Búsqueda avanzada de lotes: filtros por familia de producto, fase,
    // estado de alerta e intervalo de fechas, con paginación y total de
    // coincidencias (véase D14).
    Route::get('/search/batches', [SearchController::class, 'index'])
        ->name('api.v1.search.batches.index');

    // Indicadores del panel: tiempos de ciclo por fase y desglose de
    // estados de los lotes (véase D21).
    Route::get('/dashboard/kpis', [DashboardController::class, 'index'])
        ->name('api.v1.dashboard.kpis.index');

    // Historial inmutable de auditoría, reservado al rol de Auditor /
    // Administrador IT. La comprobación del rol se aplica en la capa de
    // aplicación (véase D9).
    Route::get('/audit/logs', [AuditController::class, 'index'])
        ->name('api.v1.audit.logs.index');
    Route::get('/audit/logs/{uuid}', [AuditController::class, 'show'])
        ->where('uuid', '[0-9a-fA-F-]{36}')
        ->name('api.v1.audit.logs.show');
});
