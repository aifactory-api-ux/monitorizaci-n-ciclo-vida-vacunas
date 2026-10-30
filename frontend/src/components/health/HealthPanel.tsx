import { useHealthStatus } from '@/hooks/useHealthStatus';

/**
 * Estado del servicio y de sus dependencias, tal y como lo publica el
 * endpoint público `GET /api/v1/health`.
 */
export function HealthPanel() {
    const health = useHealthStatus();

    return (
        <section aria-labelledby="estado-servicio" className="flex flex-col gap-3">
            <h2 id="estado-servicio" className="text-title-20 text-text-high">
                Estado del servicio
            </h2>

            {health.isPending && <p className="text-body-14 text-text-medium">Consultando el estado del servicio…</p>}

            {health.isError && (
                <p role="alert" className="text-body-14 text-danger">
                    No se ha podido consultar el estado del servicio.
                </p>
            )}

            {health.data && (
                <ul className="flex flex-col gap-2">
                    {Object.entries(health.data.checks).map(([dependency, check]) => (
                        <li
                            key={dependency}
                            className="flex items-center justify-between rounded-lg border border-line bg-surface px-4 py-3 shadow-card"
                        >
                            <span className="text-body-14 font-medium text-text-high">{dependency}</span>
                            <span
                                className={
                                    check.status === 'ok'
                                        ? 'text-body-14 font-medium text-success'
                                        : 'text-body-14 font-medium text-danger'
                                }
                            >
                                {check.status}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
