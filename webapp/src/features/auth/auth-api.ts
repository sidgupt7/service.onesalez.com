import { apiRequest } from '../../lib/api';

export type AuthRealm = 'client' | 'employee';
export type ActorType = 'CLIENT_CONTACT' | 'EMPLOYEE';

export interface AuthActor {
  id: number;
  type: ActorType;
  email: string;
  displayName: string | null;
  clientId: number | null;
  roles: string[];
  permissions: string[];
}

export interface AuthSession {
  access_token: string;
  token_type: 'Bearer';
  expires_in: number;
  actor: AuthActor;
}

export interface LoginCredentials {
  email: string;
  password: string;
  realm: AuthRealm;
}

export interface TrustedDeviceProfile {
  deviceId: string;
  deviceToken: string;
  deviceName: string;
  expiresAt: string;
  actor: AuthActor;
}

interface TrustedDeviceEnrollment {
  device_id: string;
  device_token: string;
  device_name: string;
  expires_at: string;
  actor: AuthActor;
}

const TRUSTED_DEVICES_KEY = 'onesalez_trusted_devices_v1';

export function login(credentials: LoginCredentials): Promise<AuthSession> {
  return apiRequest<AuthSession>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ ...credentials, email: credentials.email.trim().toLowerCase() }),
  });
}

export function requestPasswordReset(email: string, realm: AuthRealm): Promise<{ message: string }> {
  return apiRequest<{ message: string }>('/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({ email: email.trim().toLowerCase(), realm }),
  });
}

export function resetPassword(token: string, password: string, realm: AuthRealm): Promise<{ message: string }> {
  return apiRequest<{ message: string }>('/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify({ token, password, realm }),
  });
}

export function pinLogin(profile: TrustedDeviceProfile, pin: string): Promise<AuthSession> {
  return apiRequest<AuthSession>('/auth/pin-login', {
    method: 'POST',
    body: JSON.stringify({ device_id: profile.deviceId, device_token: profile.deviceToken, pin }),
  });
}

export async function enrollTrustedDevice(accessToken: string, pin: string, deviceName: string): Promise<TrustedDeviceProfile> {
  const enrollment = await apiRequest<TrustedDeviceEnrollment>('/auth/trusted-device', {
    method: 'POST',
    headers: { Authorization: `Bearer ${accessToken}` },
    body: JSON.stringify({ pin, device_name: deviceName }),
  });
  const profile = {
    deviceId: enrollment.device_id,
    deviceToken: enrollment.device_token,
    deviceName: enrollment.device_name,
    expiresAt: enrollment.expires_at,
    actor: enrollment.actor,
  };
  saveTrustedProfile(profile);
  return profile;
}

export function trustedProfiles(): TrustedDeviceProfile[] {
  try {
    const stored = JSON.parse(localStorage.getItem(TRUSTED_DEVICES_KEY) || '[]') as TrustedDeviceProfile[];
    const now = Date.now();
    const active = stored.filter((profile) => Date.parse(profile.expiresAt) > now);
    if (active.length !== stored.length) localStorage.setItem(TRUSTED_DEVICES_KEY, JSON.stringify(active));
    return active;
  } catch {
    return [];
  }
}

export function saveTrustedProfile(profile: TrustedDeviceProfile): void {
  const profiles = trustedProfiles().filter((item) => item.deviceId !== profile.deviceId && item.actor.email !== profile.actor.email);
  localStorage.setItem(TRUSTED_DEVICES_KEY, JSON.stringify([profile, ...profiles]));
}

export function forgetTrustedProfile(deviceId: string): void {
  localStorage.setItem(TRUSTED_DEVICES_KEY, JSON.stringify(trustedProfiles().filter((profile) => profile.deviceId !== deviceId)));
}

export function forgetTrustedProfilesForActor(email: string): void {
  localStorage.setItem(TRUSTED_DEVICES_KEY, JSON.stringify(
    trustedProfiles().filter((profile) => profile.actor.email.toLowerCase() !== email.toLowerCase()),
  ));
}

export function refreshSession(): Promise<AuthSession> {
  return apiRequest<AuthSession>('/auth/refresh', { method: 'POST' });
}

export function logout(): Promise<{ message: string }> {
  return apiRequest<{ message: string }>('/auth/logout', { method: 'POST' });
}

export function homeForActor(actor: AuthActor): string {
  return actor.type === 'CLIENT_CONTACT' ? '/portal' : actor.roles.includes('SYSTEM_ADMIN') ? '/admin' : '/console';
}
