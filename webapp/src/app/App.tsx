import { useTheme } from "../lib/use-theme";
import { lazy, Suspense } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
  Navigate,
  RouterProvider,
  createBrowserRouter,
} from "react-router-dom";

import { LoginPage } from "../features/auth/LoginPage";
const ResetPasswordPage = lazy(() =>
  import("../features/auth/ResetPasswordPage").then((module) => ({
    default: module.ResetPasswordPage,
  })),
);
import { AuthProvider } from "../features/auth/AuthProvider";
import { ProtectedRoute } from "../features/auth/ProtectedRoute";
const AdminLayout = lazy(() =>
  import("../features/admin/AdminLayout").then((module) => ({
    default: module.AdminLayout,
  })),
);
const AdminOverview = lazy(() =>
  import("../features/admin/AdminOverview").then((module) => ({
    default: module.AdminOverview,
  })),
);
const ClientsPage = lazy(() =>
  import("../features/clients/ClientsPage").then((module) => ({
    default: module.ClientsPage,
  })),
);
const ClientPortalPage = lazy(() =>
  import("../features/portal/ClientPortalPage").then((module) => ({
    default: module.ClientPortalPage,
  })),
);
const EmployeesPage = lazy(() =>
  import("../features/employees/EmployeesPage").then((module) => ({
    default: module.EmployeesPage,
  })),
);
const ServiceConsolePage = lazy(() =>
  import("../features/service/ServiceConsolePage").then((module) => ({
    default: module.ServiceConsolePage,
  })),
);
const ReportsPage = lazy(() =>
  import("../features/reports/ReportsPage").then((module) => ({
    default: module.ReportsPage,
  })),
);

const LeadsPage = lazy(() =>
  import("../features/leads/LeadsPage").then((module) => ({
    default: module.LeadsPage,
  })),
);
const TeamsPage = lazy(() =>
  import("../features/employees/TeamsPage").then((module) => ({
    default: module.TeamsPage,
  })),
);
const SettingsPage = lazy(() =>
  import("../features/admin/SettingsPage").then((module) => ({
    default: module.SettingsPage,
  })),
);

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
  { path: "/login", element: <LoginPage /> },
  { path: "/reset-password", element: <ResetPasswordPage /> },
  {
    path: "/portal",
    element: (
      <ProtectedRoute actorType="CLIENT_CONTACT">
        <ClientPortalPage />
      </ProtectedRoute>
    ),
  },
  {
    path: "/console",
    element: (
      <ProtectedRoute actorType="EMPLOYEE">
        <ServiceConsolePage />
      </ProtectedRoute>
    ),
  },
  {
    path: "/admin",
    element: (
      <ProtectedRoute actorType="EMPLOYEE">
        <AdminLayout />
      </ProtectedRoute>
    ),
    children: [
      {
        path: "leads",
        element: (
          <ProtectedRoute
            actorType="EMPLOYEE"
            requiredPermission="leads.manage"
          >
            <LeadsPage />
          </ProtectedRoute>
        ),
      },
      {
        path: "teams",
        element: (
          <ProtectedRoute actorType="EMPLOYEE" requiredRole="SYSTEM_ADMIN">
            <TeamsPage />
          </ProtectedRoute>
        ),
      },
      { path: "settings", element: <SettingsPage /> },
      { index: true, element: <AdminOverview /> },
      { path: "clients", element: <ClientsPage /> },
      {
        path: "employees",
        element: (
          <ProtectedRoute actorType="EMPLOYEE" requiredRole="SYSTEM_ADMIN">
            <EmployeesPage />
          </ProtectedRoute>
        ),
      },
      {
        path: "reports",
        element: (
          <ProtectedRoute
            actorType="EMPLOYEE"
            requiredPermission="analytics.view"
          >
            <ReportsPage />
          </ProtectedRoute>
        ),
      },
    ],
  },
  { path: "*", element: <Navigate to="/login" replace /> },
]);

export function App() {
  useTheme();
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <Suspense
          fallback={
            <p role="status" className="p-8 text-center">
              Loading workspace…
            </p>
          }
        >
          <RouterProvider router={router} />
        </Suspense>
      </AuthProvider>
    </QueryClientProvider>
  );
}
