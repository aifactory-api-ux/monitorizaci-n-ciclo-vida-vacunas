import { Navigate, Outlet, useLocation } from 'react-router-dom';

import { useAuthSession } from '@/auth/authStore';

/**
 * Ruta que exige una sesión iniciada.
 *
 * Sin token de acceso el usuario se redirige a la pantalla de autenticación,
 * conservando la ruta solicitada para volver a ella tras iniciar sesión.
 */
export function ProtectedRoute() {
    const session = useAuthSession();
    const location = useLocation();

    if (session.accessToken === null || session.accessToken === '') {
        return <Navigate to="/login" replace state={{ from: location.pathname }} />;
    }

    return <Outlet />;
}
