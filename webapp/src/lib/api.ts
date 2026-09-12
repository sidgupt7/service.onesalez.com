// Production is hosted with its API; a developer's .env.local must never leak into a release.
const API_BASE_URL = import.meta.env.PROD
  ? "/api/v1"
  : import.meta.env.VITE_API_BASE_URL || "/api/v1";

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
    public readonly code = "REQUEST_FAILED",
  ) {
    super(message);
  }
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      credentials: "include",
      headers: {
        Accept: "application/json",
        ...(options.body ? { "Content-Type": "application/json" } : {}),
        ...options.headers,
      },
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError")
      throw error;
    throw new ApiError(
      "Cannot reach the service. Reload this page and try again.",
      0,
      "NETWORK_ERROR",
    );
  }
  let payload: ApiEnvelope<T>;
  try {
    payload = (await response.json()) as ApiEnvelope<T>;
  } catch {
    throw new ApiError(
      "The service returned an unexpected response. Reload this page and try again.",
      response.status,
      "INVALID_RESPONSE",
    );
  }
  if (
    !payload ||
    typeof payload !== "object" ||
    typeof payload.success !== "boolean" ||
    !("data" in payload)
  ) {
    throw new ApiError(
      "The service returned an unexpected response. Reload this page and try again.",
      response.status,
      "INVALID_RESPONSE",
    );
  }
  if (!response.ok || !payload.success) {
    throw new ApiError(
      payload.error?.message || "The request could not be completed.",
      response.status,
      payload.error?.code,
    );
  }
  return payload.data;
}

export async function getHealth(signal?: AbortSignal): Promise<HealthStatus> {
  return apiRequest<HealthStatus>("/health", { signal });
}
