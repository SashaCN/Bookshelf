/** Registers the offline service worker; a no-op outside production builds, the demo build and unsupported browsers. */
export function registerSW(): void {
  if (!import.meta.env.PROD || import.meta.env.VITE_DEMO === 'true') return
  if (!('serviceWorker' in navigator)) return

  const register = () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
      // Offline support is an enhancement; the app works without it.
    })
  }
  if (document.readyState === 'complete') register()
  else window.addEventListener('load', register, { once: true })
}
