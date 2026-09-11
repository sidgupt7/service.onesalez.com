import { afterEach, describe, expect, it, vi } from 'vitest';

afterEach(() => { vi.unstubAllEnvs(); vi.unstubAllGlobals(); vi.resetModules(); });

describe('production API requests', () => {
  it('uses the live same-origin API even when a local environment URL is present', async () => {
    vi.stubEnv('PROD', true);
    vi.stubEnv('VITE_API_BASE_URL', 'http://127.0.0.1:8000/api/v1');
    const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify({success: true, data: {message: 'ok'}})));
    vi.stubGlobal('fetch', fetch);
    const { apiRequest } = await import('./api');
    await apiRequest('/auth/reset-password', {method: 'POST', body: '{}'});
    expect(fetch).toHaveBeenCalledWith('/api/v1/auth/reset-password', expect.objectContaining({method: 'POST'}));
  });

  it('explains a connection failure without blaming the password', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    const { apiRequest } = await import('./api');
    await expect(apiRequest('/auth/reset-password')).rejects.toMatchObject({code: 'NETWORK_ERROR', message: expect.stringContaining('Cannot reach')});
  });

  it('reports non-JSON server responses without leaking the response body', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>Hosting error</html>', {status: 502})));
    const { apiRequest } = await import('./api');
    await expect(apiRequest('/auth/reset-password')).rejects.toMatchObject({code: 'INVALID_RESPONSE', status: 502});
  });
});
