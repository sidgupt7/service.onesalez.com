/** Clear only this application's offline files. Account data and cookies are retained. */
export async function clearAppCache(): Promise<void> {
  const appScope = new URL(import.meta.env.BASE_URL, window.location.origin).href;
  if ('serviceWorker' in navigator) {
    const registrations = await navigator.serviceWorker.getRegistrations();
    await Promise.all(registrations.filter((registration) => registration.scope === appScope)
      .map((registration) => registration.unregister()));
  }
  if ('caches' in window) {
    const names = await window.caches.keys();
    await Promise.all(names.filter((name) => name.startsWith('onesalez-')
      || (name.startsWith('workbox-') && name.endsWith(appScope)))
      .map((name) => window.caches.delete(name)));
  }
}

export async function resetApp(): Promise<void> {
  await clearAppCache();
  // A fresh navigation also drops in-memory query data and bypasses cached login HTML.
  const target = new URL('login', new URL(import.meta.env.BASE_URL, window.location.origin));
  target.searchParams.set('app-reset', Date.now().toString());
  window.location.replace(target.href);
}
