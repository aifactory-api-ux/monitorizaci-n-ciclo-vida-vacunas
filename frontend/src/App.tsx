import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';

import { ProtectedRoute } from '@/auth/ProtectedRoute';
import { AlertHistoryPage } from '@/pages/AlertHistoryPage';
import { BatchDetailPage } from '@/pages/BatchDetailPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { LoginPage } from '@/pages/LoginPage';

/**
 * Enrutado de la aplicación.
 *
 * Las vistas con datos del panel quedan detrás de `ProtectedRoute`, que
 * exige un token de acceso válido.
 */
export default function App() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={<LoginPage />} />

                <Route element={<ProtectedRoute />}>
                    <Route path="/" element={<DashboardPage />} />
                    <Route path="/batches/:batchId" element={<BatchDetailPage />} />
                    <Route path="/alerts" element={<AlertHistoryPage />} />
                </Route>

                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
        </BrowserRouter>
    );
}
