import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Navigate, RouterProvider, createBrowserRouter } from 'react-router-dom';

import { LoginPage } from '../features/auth/LoginPage';
import { ResetPasswordPage } from '../features/auth/ResetPasswordPage';
import { AuthProvider } from '../features/auth/AuthProvider';
import { ProtectedRoute } from '../features/auth/ProtectedRoute';
import { AdminLayout } from '../features/admin/AdminLayout';
import { AdminOverview } from '../features/admin/AdminOverview';
import { ClientsPage } from '../features/clients/ClientsPage';
import { ClientPortalPage } from '../features/portal/ClientPortalPage';
import { EmployeesPage } from '../features/employees/EmployeesPage';
import { ServiceConsolePage } from '../features/service/ServiceConsolePage';
import { ReportsPage } from '../features/reports/ReportsPage';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
      refetchOnWindowFocus: false,
    },
  },
});

const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/reset-password', element: <ResetPasswordPage /> },
  { path: '/portal', element: <ProtectedRoute actorType="CLIENT_CONTACT"><ClientPortalPage /></ProtectedRoute> },
  { path: '/console', element: <ProtectedRoute actorType="EMPLOYEE"><ServiceConsolePage /></ProtectedRoute> },
  {
    path: '/admin',
    element: <ProtectedRoute actorType="EMPLOYEE"><AdminLayout /></ProtectedRoute>,
    children: [{ index: true, element: <AdminOverview /> }, { path: 'clients', element: <ClientsPage /> }, { path: 'employees', element: <ProtectedRoute actorType="EMPLOYEE" requiredRole="SYSTEM_ADMIN"><EmployeesPage /></ProtectedRoute> }, { path: 'reports', element: <ProtectedRoute actorType="EMPLOYEE" requiredPermission="analytics.view"><ReportsPage /></ProtectedRoute> }],
  },
  { path: '*', element: <Navigate to="/login" replace /> },
]);

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>
    </QueryClientProvider>
  );
}
