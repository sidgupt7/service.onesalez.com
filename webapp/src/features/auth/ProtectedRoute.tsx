import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';

import { useAuth } from './AuthProvider';
import type { ActorType } from './auth-api';

interface ProtectedRouteProps {
  children: ReactNode;
  actorType?: ActorType;
  requiredRole?: string;
  requiredPermission?: string;
}

export function ProtectedRoute({ children, actorType, requiredRole, requiredPermission }: ProtectedRouteProps) {
  const { actor, status } = useAuth();
  const location = useLocation();

  if (status === 'initializing') {
    return <div className="grid min-h-screen place-items-center bg-[var(--canvas)] text-sm text-[var(--muted)]">Restoring secure session…</div>;
  }
  if (!actor) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }
  if ((actorType && actor.type !== actorType) || (requiredRole && !actor.roles.includes(requiredRole)) || (requiredPermission && !actor.permissions.includes(requiredPermission) && !actor.roles.includes('SYSTEM_ADMIN'))) {
    return <Navigate to={actor.type === 'CLIENT_CONTACT' ? '/portal' : '/console'} replace />;
  }
  return children;
}
