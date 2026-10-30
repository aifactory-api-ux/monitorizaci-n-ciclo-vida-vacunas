<?php

declare(strict_types=1);

namespace App\Api\Providers;

use App\Application\Authentication\IssueAccessToken;
use App\Application\Authorization\ActorRoleResolver;
use App\Application\Authorization\BatchVisibilityPolicy;
use App\Application\Authorization\OperationPolicy;
use App\Application\Batches\ListBatches;
use App\Application\Dashboard\GetDashboardKpis;
use App\Application\Operations\MeasureNonFunctionalThresholds;
use App\Application\Operations\VerifyPrivatePerimeter;
use App\Application\Reports\ReportExporterRegistry;
use App\Application\Search\BatchSearchService;
use App\Application\Security\RunSecurityStages;
use App\Domain\Alert\Port\AlertNotifier;
use App\Domain\Alert\Repository\AlertRepository;
use App\Domain\Alert\Service\AlertFactory;
use App\Domain\Alert\Service\SphereonAnomalyDetector;
use App\Domain\Alert\Service\ThermalDeviationDetector;
use App\Domain\Audit\Port\OperationDurationLogger;
use App\Domain\Audit\Repository\AuditTrailRepository;
use App\Domain\Audit\Repository\LogEntryRepository;
use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Authentication\Port\AccessTokenIssuer;
use App\Domain\Backup\Port\BackupArtifactStore;
use App\Domain\Backup\Port\CacheSnapshotStore;
use App\Domain\Backup\Port\CriticalDataStore;
use App\Domain\Dashboard\Service\CycleTimeCalculator;
use App\Domain\Lims\Port\LimsLabwareClient;
use App\Domain\Lims\Repository\LimsResultRepository;
use App\Domain\Lims\Service\GatingEvaluator;
use App\Domain\Lims\Service\LimsPayloadValidator;
use App\Domain\Operations\Port\DeploymentInspector;
use App\Domain\Operations\Port\MonitoringTarget;
use App\Domain\Operations\Port\ProtectedRouteProbe;
use App\Domain\Operations\Service\PrivatePerimeterPolicy;
use App\Domain\Realtime\Port\RealtimeSubscriberRegistry;
use App\Domain\Report\Port\TraceabilityReportExporter;
use App\Domain\Report\Service\TraceabilityReportBuilder;
use App\Domain\Search\Port\BatchSearchCache;
use App\Domain\Search\Port\BatchSearchRepository;
use App\Domain\Secrets\Port\SecretsManager;
use App\Domain\Security\Port\SecurityStageScanner;
use App\Domain\Security\Service\SecurityGate;
use App\Domain\Shared\Event\DomainEventRecorder;
use App\Domain\Shared\Exception\ConfigurationException;
use App\Domain\User\Repository\AppUserRepository;
use App\Domain\VaccineBatch\Repository\CampusRepository;
use App\Domain\VaccineBatch\Repository\PhaseTransitionRepository;
use App\Domain\VaccineBatch\Repository\VaccineBatchRepository;
use App\Domain\VaccineBatch\Repository\VaccineReferenceRepository;
use App\Domain\VaccineBatch\Service\CampusSequenceBuilder;
use App\Domain\VaccineBatch\Service\GatingPolicy;
use App\Domain\VaccineBatch\Service\PhaseTransitionPolicy;
use App\Infrastructure\Authentication\PassportAccessTokenIssuer;
use App\Infrastructure\Backup\EloquentCriticalDataStore;
use App\Infrastructure\Backup\FilesystemBackupArtifactStore;
use App\Infrastructure\Backup\RedisCacheSnapshotStore;
use App\Infrastructure\Broadcasting\ArrayBroadcaster;
use App\Infrastructure\Lims\HttpLabwareLimsClient;
use App\Infrastructure\Logging\JsonAuditLogger;
use App\Infrastructure\Logging\JsonOperationDurationLogger;
use App\Infrastructure\Notifier\CompositeAlertNotifier;
use App\Infrastructure\Notifier\EmailAlertNotifier;
use App\Infrastructure\Notifier\SmsAlertNotifier;
use App\Infrastructure\Operations\HttpProtectedRouteProbe;
use App\Infrastructure\Operations\InProcessMonitoringTarget;
use App\Infrastructure\Operations\RouterDeploymentInspector;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentAlertRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentAppUserRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentAuditTrailRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentBatchSearchRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentCampusRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentLimsResultRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentLogEntryRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentPhaseTransitionRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentVaccineBatchRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\EloquentVaccineReferenceRepository;
use App\Infrastructure\Queue\ArrayConnector;
use App\Infrastructure\Realtime\ArraySubscriberRegistry;
use App\Infrastructure\Realtime\RedisEventBroadcaster;
use App\Infrastructure\Reports\CsvTraceabilityExporter;
use App\Infrastructure\Reports\PdfTraceabilityExporter;
use App\Infrastructure\Search\RedisBatchSearchCache;
use App\Infrastructure\Secrets\SecretResolver;
use App\Infrastructure\Secrets\VaultSecretsManager;
use App\Infrastructure\Security\ProcessSecurityStageScanner;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Psr\Log\LoggerInterface;

/**
 * Proveedor de servicios de la capa Api.
 *
 * Registra los drivers en memoria (`array`) de cola y de broadcasting, que
 * permiten ejecutar la suite de pruebas sin MySQL ni Redis, enlaza los
 * puertos del dominio con sus adaptadores de infraestructura y fija las
 * convenciones de serialización de la API.
 */
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerArrayQueueDriver();
        $this->registerArrayBroadcastDriver();
        $this->configurePassport();
        $this->assertLimsConfiguration();
        $this->assertMonitoringConfiguration();
        $this->assertSecretsConfiguration();

        JsonResource::withoutWrapping();

        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    /**
     * Enlaza los puertos del dominio con sus adaptadores de persistencia.
     */
    public function register(): void
    {
        $this->app->singleton(PhaseTransitionPolicy::class);
        $this->app->singleton(GatingPolicy::class);
        $this->app->singleton(GatingEvaluator::class);
        $this->app->singleton(LimsPayloadValidator::class);
        $this->app->singleton(StructuredAuditLogger::class);
        $this->app->singleton(CampusSequenceBuilder::class);
        $this->app->singleton(TraceabilityReportBuilder::class);
        $this->app->singleton(CycleTimeCalculator::class);

        $this->app->bind(VaccineBatchRepository::class, EloquentVaccineBatchRepository::class);
        $this->app->bind(BatchSearchRepository::class, EloquentBatchSearchRepository::class);
        $this->app->bind(PhaseTransitionRepository::class, EloquentPhaseTransitionRepository::class);
        $this->app->bind(CampusRepository::class, EloquentCampusRepository::class);
        $this->app->bind(VaccineReferenceRepository::class, EloquentVaccineReferenceRepository::class);
        $this->app->bind(LogEntryRepository::class, EloquentLogEntryRepository::class);
        $this->app->bind(AuditTrailRepository::class, EloquentAuditTrailRepository::class);
        $this->app->bind(LimsResultRepository::class, EloquentLimsResultRepository::class);
        $this->app->bind(LimsLabwareClient::class, HttpLabwareLimsClient::class);
        $this->app->bind(AppUserRepository::class, EloquentAppUserRepository::class);

        // El log de auditoría se emite en JSON estructurado por el canal
        // configurado en `config/logging.php` (véase D10).
        $this->app->singleton(JsonAuditLogger::class, fn (): JsonAuditLogger => new JsonAuditLogger(
            $this->app->make('log')->channel('audit_json'),
        ));

        // Las duraciones de las operaciones consultivas se emiten en el mismo
        // canal estructurado (véase D21).
        $this->app->bind(OperationDurationLogger::class, fn (): OperationDurationLogger => new JsonOperationDurationLogger(
            $this->app->make('log')->channel('audit_json'),
        ));

        // La caché de resultados de búsqueda reside en Redis en el despliegue
        // y se invalida por generación cuando cambia el estado de un lote
        // (véase D14).
        $this->app->bind(BatchSearchCache::class, fn (): BatchSearchCache => new RedisBatchSearchCache(
            $this->app->make('cache')->store((string) $this->app['config']->get('search.cache.store')),
            (string) $this->app['config']->get('search.cache.prefix'),
        ));

        $this->app->bind(BatchSearchService::class, fn (): BatchSearchService => new BatchSearchService(
            $this->app->make(ListBatches::class),
            $this->app->make(BatchSearchCache::class),
            $this->app->make(OperationDurationLogger::class),
            (int) $this->app['config']->get('search.cache.ttl_seconds'),
        ));

        // El registro de exportadores resuelve el formato solicitado en cada
        // petición a partir de los adaptadores declarados en config/reports.php.
        $this->app->singleton(ReportExporterRegistry::class, fn (): ReportExporterRegistry => new ReportExporterRegistry(
            $this->exporters(),
        ));

        $this->app->singleton(AlertFactory::class);

        $this->app->bind(AlertRepository::class, EloquentAlertRepository::class);

        // Los eventos de dominio se difunden por el canal privado de
        // monitorización, que Reverb publica sobre Redis (véase D11).
        $this->app->singleton(DomainEventRecorder::class, RedisEventBroadcaster::class);

        $this->app->bind(ThermalDeviationDetector::class, fn (): ThermalDeviationDetector => new ThermalDeviationDetector(
            $this->app->make(AlertFactory::class),
            (float) $this->app['config']->get('monitoring.thermal.deviation_threshold'),
        ));

        $this->app->bind(SphereonAnomalyDetector::class, fn (): SphereonAnomalyDetector => new SphereonAnomalyDetector(
            $this->app->make(AlertFactory::class),
            (float) $this->app['config']->get('monitoring.sphereon.anomaly_threshold'),
        ));

        // Los canales de notificación se resuelven por separado para que cada
        // canal pueda sustituirse por un doble en memoria en las pruebas.
        $this->app->bind('alert-notifier.email', fn (): AlertNotifier => new EmailAlertNotifier(
            $this->app->make(Mailer::class),
            $this->recipients('email'),
        ));

        $this->app->bind('alert-notifier.sms', fn (): AlertNotifier => new SmsAlertNotifier(
            $this->app->make(LoggerInterface::class),
            $this->recipients('sms'),
        ));

        $this->app->bind(AlertNotifier::class, fn (): AlertNotifier => new CompositeAlertNotifier($this->channels()));

        // La matriz de permisos y el alcance de visibilidad por lote son la
        // única fuente de verdad de los roles (véase D9).
        $this->app->singleton(OperationPolicy::class);
        $this->app->singleton(BatchVisibilityPolicy::class);
        $this->app->bind(ActorRoleResolver::class, fn (): ActorRoleResolver => new ActorRoleResolver(
            $this->app->make(AppUserRepository::class),
        ));

        // La emisión de tokens JWT se delega en el servidor de autorización de
        // Passport mediante el flujo de contraseña (véase D9).
        $this->app->bind(AccessTokenIssuer::class, PassportAccessTokenIssuer::class);
        $this->app->bind(IssueAccessToken::class, fn (): IssueAccessToken => new IssueAccessToken(
            $this->app->make(AccessTokenIssuer::class),
        ));

        // El gestor de secretos se enlaza sólo si no hay una implementación
        // previa, de modo que el despliegue pueda sustituir el adaptador de
        // Vault por el gestor corporativo (véase D16).
        $this->app->bindIf(SecretsManager::class, fn (): SecretsManager => new VaultSecretsManager(
            $this->app->make(HttpFactory::class),
            (string) $this->app['config']->get('secrets.vault.address', ''),
            (string) $this->app['config']->get('secrets.vault.mount', 'msd'),
            $this->vaultToken(),
            (int) $this->app['config']->get('secrets.vault.timeout', 5),
        ));
        $this->app->bind(SecretResolver::class, fn (): SecretResolver => new SecretResolver(
            $this->app->make(SecretsManager::class),
            $this->app['config']->get('secrets.paths', []),
            $this->app['config']->get('secrets.required', []),
        ));

        // El respaldo de los datos críticos extrae las filas de las tablas
        // críticas de MySQL y el estado del almacén de claves de Redis, y
        // deposita el resultado en un artefacto portable con su manifiesto
        // (véase D19).
        $this->app->bind(BackupArtifactStore::class, fn (): BackupArtifactStore => new FilesystemBackupArtifactStore(
            (string) $this->app['config']->get('backup.path'),
        ));

        $this->app->bind(CriticalDataStore::class, fn (): CriticalDataStore => new EloquentCriticalDataStore(
            $this->app->make('db'),
            $this->app['config']->get('backup.connection'),
            $this->backupTables(),
        ));

        $this->app->bind(CacheSnapshotStore::class, fn (): CacheSnapshotStore => new RedisCacheSnapshotStore(
            (string) $this->app['config']->get('backup.cache.connection', 'backup'),
        ));

        // La verificación operativa mide los umbrales no funcionales sobre el
        // propio proceso: el tiempo observado es la latencia del servidor de
        // aplicaciones (véase D21).
        $this->app->singleton(MonitoringTarget::class, InProcessMonitoringTarget::class);

        $this->app->singleton(ProtectedRouteProbe::class, fn (): ProtectedRouteProbe => new HttpProtectedRouteProbe(
            $this->app->make(Kernel::class),
            '/'.ltrim((string) $this->app['config']->get('operations.perimeter.protected_uri'), '/'),
        ));

        $this->app->bind(DeploymentInspector::class, RouterDeploymentInspector::class);
        $this->app->singleton(PrivatePerimeterPolicy::class);
        $this->app->singleton(VerifyPrivatePerimeter::class);

        // Los suscriptores del canal de tiempo real se registran en memoria:
        // permiten medir la capacidad de difusión y la latencia de entrega
        // sin levantar el servidor de WebSockets (véase D11 y NFR009).
        $this->app->singleton(RealtimeSubscriberRegistry::class, ArraySubscriberRegistry::class);

        // La etapa de seguridad bloquea la promoción del código cuando el
        // análisis detecta vulnerabilidades (véase D20 y NFR011).
        $this->app->singleton(SecurityGate::class);
        $this->app->singleton('security-stage-scanners', fn (): array => $this->securityStageScanners());
        $this->app->singleton(RunSecurityStages::class, fn (): RunSecurityStages => new RunSecurityStages(
            $this->app->make('security-stage-scanners'),
            $this->app->make(SecurityGate::class),
        ));

        $this->app->singleton(MeasureNonFunctionalThresholds::class, fn (): MeasureNonFunctionalThresholds => new MeasureNonFunctionalThresholds(
            $this->app->make(MonitoringTarget::class),
            $this->app->make(ProtectedRouteProbe::class),
            $this->app->make(GetDashboardKpis::class),
            $this->app->make(BatchSearchService::class),
            $this->app->make(DomainEventRecorder::class),
            (float) $this->app['config']->get('operations.thresholds.dashboard_seconds'),
            (float) $this->app['config']->get('operations.thresholds.search_p95_seconds'),
            (float) $this->app['config']->get('operations.thresholds.event_latency_seconds'),
            (int) $this->app['config']->get('operations.thresholds.protected_status_code'),
            (int) $this->app['config']->get('operations.thresholds.search_samples'),
        ));
    }

    /**
     * Analizadores de seguridad declarados en la configuración.
     *
     * Una etapa sin comando declarado no puede ejecutarse: el analizador la
     * devuelve como fallida, de modo que la promoción queda bloqueada en
     * lugar de continuar sin análisis (véase D20 y NFR011).
     *
     * @return list<SecurityStageScanner>
     */
    private function securityStageScanners(): array
    {
        $stages = $this->app['config']->get('operations.security.stages', []);
        $scanners = [];

        foreach (is_array($stages) ? $stages : [] as $stage) {
            if (! is_array($stage)) {
                continue;
            }

            $name = (string) ($stage['stage'] ?? '');

            if ($name === '') {
                continue;
            }

            $scanners[] = new ProcessSecurityStageScanner(
                $name,
                (string) ($stage['command'] ?? ''),
                (int) ($stage['timeout_seconds'] ?? 300),
            );
        }

        return $scanners;
    }

    /**
     * Tablas críticas que entran en el respaldo, en orden de dependencia.
     *
     * @return list<string>
     */
    private function backupTables(): array
    {
        $tables = $this->app['config']->get('backup.tables', []);

        if (! is_array($tables)) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', $tables),
            static fn (string $table): bool => $table !== '',
        ));
    }

    /**
     * Exportadores de reporte disponibles, indexados por formato.
     *
     * Los formatos habilitados se declaran en `config/reports.php`.
     *
     * @return array<string, TraceabilityReportExporter>
     */
    private function exporters(): array
    {
        $available = [
            'csv' => fn (): TraceabilityReportExporter => new CsvTraceabilityExporter,
            'pdf' => fn (): TraceabilityReportExporter => new PdfTraceabilityExporter,
        ];

        $formats = $this->app['config']->get('reports.formats', []);
        $formats = is_array($formats) ? $formats : [];

        $exporters = [];

        foreach ($available as $format => $factory) {
            if (in_array($format, $formats, true)) {
                $exporters[$format] = $factory();
            }
        }

        return $exporters;
    }

    /**
     * Canales de notificación habilitados en la configuración.
     *
     * @return list<AlertNotifier>
     */
    private function channels(): array
    {
        $channels = [];

        if (config('monitoring.notification.email.enabled')) {
            $channels[] = $this->app->make('alert-notifier.email');
        }

        if (config('monitoring.notification.sms.enabled')) {
            $channels[] = $this->app->make('alert-notifier.sms');
        }

        return $channels;
    }

    /**
     * Destinatarios configurados para un canal de notificación.
     *
     * @return list<string>
     */
    private function recipients(string $channel): array
    {
        $recipients = $this->app['config']->get("monitoring.notification.{$channel}.recipients");

        if (! is_array($recipients)) {
            return [];
        }

        return array_values(array_map('strval', $recipients));
    }

    /**
     * Exige una configuración de monitorización utilizable.
     *
     * Un umbral no utilizable o un canal de notificación habilitado sin
     * destinatarios no debe arrancar con un valor de reserva: el fallo se
     * produce al iniciar, con un mensaje explícito.
     *
     * @throws ConfigurationException Si la configuración de monitorización no es válida.
     */
    private function assertMonitoringConfiguration(): void
    {
        $thresholds = [
            'thermal' => 'monitoring.thermal.deviation_threshold',
            'sphereon' => 'monitoring.sphereon.anomaly_threshold',
        ];

        foreach ($thresholds as $group => $key) {
            $threshold = $this->app['config']->get($key);

            if (! is_numeric($threshold) || (float) $threshold <= 0.0) {
                throw ConfigurationException::invalidMonitoringThreshold($group, $key);
            }
        }

        foreach (['email', 'sms'] as $channel) {
            if (! config("monitoring.notification.{$channel}.enabled")) {
                continue;
            }

            if ($this->recipients($channel) === []) {
                throw ConfigurationException::missingNotificationRecipients($channel);
            }
        }
    }

    /**
     * Exige la configuración de la integración con LIMS Labware cuando está
     * habilitada.
     *
     * La integración habilitada sin URL base ni token no debe arrancar con una
     * credencial de reserva: el fallo se produce al iniciar, con un mensaje
     * explícito.
     *
     * @throws ConfigurationException Si falta la configuración de la integración.
     */
    private function assertLimsConfiguration(): void
    {
        if (! config('lims.enabled')) {
            return;
        }

        foreach (['base_url', 'api_token'] as $key) {
            $value = config("lims.{$key}");

            if (! is_string($value) || trim($value) === '') {
                throw ConfigurationException::missingLimsConfiguration($key);
            }
        }
    }

    /**
     * Configura la emisión de tokens JWT de Passport.
     *
     * Se habilita el flujo de contraseña, que usa el cliente de contraseña
     * declarado en la tabla `oauth_clients`, y se fija la vigencia de los
     * tokens de acceso (véase D9).
     */
    private function configurePassport(): void
    {
        Passport::enablePasswordGrant();

        Passport::tokensExpireIn(now()->addMinutes((int) $this->app['config']->get('auth.api_token_ttl_minutes', 60)));
    }

    /**
     * Exige que el gestor de secretos tenga disponible toda credencial
     * obligatoria cuando la integración está habilitada.
     *
     * Una credencial ausente no debe arrancar con un valor de reserva: el
     * fallo se produce al iniciar, con un mensaje explícito que no incluye el
     * valor (véase D16).
     *
     * @throws ConfigurationException Si falta alguna credencial obligatoria.
     */
    private function assertSecretsConfiguration(): void
    {
        if (! config('secrets.enabled')) {
            return;
        }

        $this->app->make(SecretResolver::class)->assertRequiredSecretsAreAvailable();
    }

    /**
     * Token de servicio del gestor de secretos.
     *
     * Se toma del fichero indicado por el despliegue y, en su defecto, de la
     * variable de entorno. Nunca se registra ni se incluye en un mensaje de
     * error (véase D16).
     */
    private function vaultToken(): ?string
    {
        $file = (string) $this->app['config']->get('secrets.vault.token_file', '');

        if ($file !== '' && is_readable($file)) {
            $contents = trim((string) file_get_contents($file));

            return $contents === '' ? null : $contents;
        }

        $token = $this->app['config']->get('secrets.vault.token');
        $token = is_string($token) ? trim($token) : '';

        return $token === '' ? null : $token;
    }

    /**
     * Registra el driver de cola en memoria.
     */
    private function registerArrayQueueDriver(): void
    {
        $this->app->make('queue')->extend(
            'array',
            fn (): ArrayConnector => new ArrayConnector
        );
    }

    /**
     * Registra el driver de broadcasting en memoria.
     *
     * El emisor reparte las difusiones entre los suscriptores conectados al
     * canal, de modo que la entrega sea medible en el propio proceso (véase
     * D11 y NFR009).
     */
    private function registerArrayBroadcastDriver(): void
    {
        $this->app->make(BroadcastManager::class)->extend(
            'array',
            fn (): ArrayBroadcaster => new ArrayBroadcaster(
                $this->app->make(RealtimeSubscriberRegistry::class),
            )
        );
    }
}
