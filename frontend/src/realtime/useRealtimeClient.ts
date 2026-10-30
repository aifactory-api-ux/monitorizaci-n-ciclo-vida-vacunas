import { useEffect, useState } from 'react';

import { useAuthSession } from '@/auth/authStore';
import { createBatchMonitoringClient, realtimeConfigFromEnv } from '@/realtime/echo';
import type { RealtimeClient } from '@/realtime/echo';

/**
 * Cliente de tiempo real del canal privado de monitorización.
 *
 * El cliente se crea sólo cuando hay sesión iniciada y la configuración de
 * Reverb está disponible en el entorno. Si falta la configuración, el hook
 * devuelve `null` y la aplicación sigue funcionando con el refresco
 * periódico de las consultas, sin lanzar errores (véase D11).
 */
export function useRealtimeClient(): RealtimeClient | null {
    const session = useAuthSession();
    const token = session.accessToken;
    const [client, setClient] = useState<RealtimeClient | null>(null);

    useEffect(() => {
        if (token === null || token === '') {
            setClient(null);

            return undefined;
        }

        let config;

        try {
            config = realtimeConfigFromEnv(import.meta.env as Record<string, string | undefined>);
        } catch {
            setClient(null);

            return undefined;
        }

        const created = createBatchMonitoringClient(config, token);
        setClient(created);

        return () => {
            created.disconnect();
            setClient(null);
        };
    }, [token]);

    return client;
}
