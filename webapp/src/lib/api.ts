const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

export interface ApiEnvelope<T> {
  success: boolean;
  data: T;
  error: null | { code: string; message: string };
  timestamp: string;
}

export interface HealthStatus {
  status: string;
}

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code = 'REQUEST_FAILED',
  ) {
    super(message);
  }
}

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
  });
  const payload = (await response.json()) as ApiEnvelope<T>;
  if (!response.ok || !payload.success) {
    throw new ApiError(payload.error?.message || 'The request could not be completed.', response.status, payload.error?.code);
  }
  return payload.data;
}

export async function getHealth(signal?: AbortSignal): Promise<HealthStatus> {
  return apiRequest<HealthStatus>('/health', { signal });
}
