import { afterEach, describe, expect, it, vi } from 'vitest';
import { clearAppCache } from './reset-app';

afterEach(() => { vi.unstubAllGlobals(); localStorage.clear(); });

describe('clearAppCache', () => {
  it('clears this app worker and caches while retaining preferences and unrelated caches', async () => {
    const scope = new URL('/', window.location.origin).href;
    const unregisterApp = vi.fn().mockResolvedValue(true);
    const unregisterOther = vi.fn().mockResolvedValue(true);
    vi.stubGlobal('navigator', {serviceWorker: {getRegistrations: vi.fn().mockResolvedValue([
      {scope, unregister: unregisterApp}, {scope: scope + 'other/', unregister: unregisterOther},
    ])}});
    const removeCache = vi.fn().mockResolvedValue(true);
    vi.stubGlobal('caches', {keys: vi.fn().mockResolvedValue([
      'onesalez-pages-v2', `workbox-precache-v2-${scope}`, `workbox-precache-v2-${scope}other/`, 'other-app',
    ]), delete: removeCache});
    localStorage.setItem('onesalez_trusted_devices_v1', 'saved-device');
    await clearAppCache();
    expect(unregisterApp).toHaveBeenCalledOnce();
    expect(unregisterOther).not.toHaveBeenCalled();
    expect(removeCache.mock.calls.map(([name]) => name)).toEqual(['onesalez-pages-v2', `workbox-precache-v2-${scope}`]);
    expect(localStorage.getItem('onesalez_trusted_devices_v1')).toBe('saved-device');
  });

  it('supports browsers without offline storage APIs', async () => {
    vi.stubGlobal('navigator', {});
    await expect(clearAppCache()).resolves.toBeUndefined();
  });

  it('surfaces storage failures instead of reporting a successful reset', async () => {
    vi.stubGlobal('navigator', {serviceWorker: {getRegistrations: vi.fn().mockRejectedValue(new Error('Storage unavailable'))}});
    await expect(clearAppCache()).rejects.toThrow('Storage unavailable');
  });
});
