import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

import { ApiError, apiRequest } from '../../lib/api';
import * as authApi from './auth-api';
import type { AuthActor, LoginCredentials, TrustedDeviceProfile } from './auth-api';

type AuthStatus = 'initializing' | 'authenticated' | 'guest';

interface AuthContextValue {
  actor: AuthActor | null;
  accessToken: string | null;
  status: AuthStatus;
  signIn: (credentials: LoginCredentials, quickPin?: string) => Promise<AuthActor>;
  signInWithPin: (profile: TrustedDeviceProfile, pin: string) => Promise<AuthActor>;
  signOut: () => Promise<void>;
  authenticatedRequest: <T>(path: string, options?: RequestInit) => Promise<T>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [actor, setActor] = useState<AuthActor | null>(null);
  const [accessToken, setAccessToken] = useState<string | null>(null);
  const [status, setStatus] = useState<AuthStatus>('initializing');
  const refreshInFlight = useRef<Promise<authApi.AuthSession> | null>(null);

  const applySession = useCallback((session: authApi.AuthSession) => {
    setActor(session.actor);
    setAccessToken(session.access_token);
    setStatus('authenticated');
  }, []);

  useEffect(() => {
    let active = true;
    authApi.refreshSession()
      .then((session) => {
        if (active) applySession(session);
      })
      .catch(() => {
        if (active) setStatus('guest');
      });
    return () => {
      active = false;
    };
  }, [applySession]);

  const signIn = useCallback(async (credentials: LoginCredentials, quickPin?: string) => {
    const session = await authApi.login(credentials);
    if (quickPin) {
      const deviceName = /Windows/i.test(navigator.userAgent) ? 'Windows device' : 'Trusted browser';
      await authApi.enrollTrustedDevice(session.access_token, quickPin, deviceName);
    }
    applySession(session);
    return session.actor;
  }, [applySession]);

  const signInWithPin = useCallback(async (profile: TrustedDeviceProfile, pin: string) => {
    const session = await authApi.pinLogin(profile, pin);
    applySession(session);
    return session.actor;
  }, [applySession]);

  const signOut = useCallback(async () => {
    try {
      await authApi.logout();
    } finally {
      setActor(null);
      setAccessToken(null);
      setStatus('guest');
    }
  }, []);

  const authenticatedRequest = useCallback(async <T,>(path: string, options: RequestInit = {}): Promise<T> => {
    const send = (token: string) => apiRequest<T>(path, {
      ...options,
      headers: { ...options.headers, Authorization: `Bearer ${token}` },
    });
    if (!accessToken) throw new ApiError('Authentication is required.', 401, 'UNAUTHENTICATED');
    try {
      return await send(accessToken);
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) throw error;
      refreshInFlight.current ??= authApi.refreshSession().finally(() => {
        refreshInFlight.current = null;
      });
      try {
        const session = await refreshInFlight.current;
        applySession(session);
        return await send(session.access_token);
      } catch (refreshError) {
        setActor(null);
        setAccessToken(null);
        setStatus('guest');
        throw refreshError;
      }
    }
  }, [accessToken, applySession]);

  const value = useMemo(
    () => ({ actor, accessToken, status, signIn, signInWithPin, signOut, authenticatedRequest }),
    [actor, accessToken, status, signIn, signInWithPin, signOut, authenticatedRequest],
  );
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside AuthProvider.');
  return context;
}
