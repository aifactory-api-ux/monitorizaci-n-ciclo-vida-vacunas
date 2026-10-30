<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Domain\Alert\Entity\Alert;
use App\Domain\Alert\Event\AlertRaised;
use App\Domain\Alert\Port\AlertNotifier;
use App\Domain\Alert\Repository\AlertRepository;
use App\Domain\Audit\Service\StructuredAuditLogger;
use App\Domain\Search\Port\BatchSearchCache;
use App\Domain\Shared\Event\DomainEventRecorder;
use DateTimeImmutable;

/**
 * Caso de uso de generación de una alerta operativa.
 *
 * Persiste la alerta detectada, deja constancia en el log estructurado y
 * notifica la alerta de prioridad máxima por los canales configurados, sin
 * que ninguna persona tenga que intervenir (véase TC005, TC023 y TC019).
 */
final class RaiseAlert
{
    public function __construct(
        private readonly AlertRepository $alerts,
        private readonly AlertNotifier $notifier,
        private readonly StructuredAuditLogger $auditLogger,
        private readonly BatchSearchCache $searchCache,
        private readonly DomainEventRecorder $events,
    ) {}

    /**
     * Genera la alerta: la persiste, la audita y la notifica si es crítica.
     */
    public function execute(Alert $alert, ?DateTimeImmutable $raisedAt = null): Alert
    {
        $timestamp = $raisedAt ?? new DateTimeImmutable;

        $this->alerts->save($alert);
        $this->searchCache->invalidate();

        $this->auditLogger->record(
            Alert::EVENT_TYPE_RAISED,
            [
                'alert_id' => $alert->id(),
                'alert_type' => $alert->alertType()->value(),
                'severity' => $alert->severity()->value(),
                'message' => $alert->message(),
                'detected_at' => $alert->createdAt()->format(DATE_ATOM),
            ],
            $alert->batchId(),
            null,
            $timestamp,
        );

        if ($alert->isCritical()) {
            $this->notifier->notify($alert);
        }

        // La alerta se difunde en el canal de monitorización para que los
        // paneles de alertas e indicadores se renueven de inmediato (véase D11).
        $this->events->record(AlertRaised::fromAlert($alert));

        return $alert;
    }
}
