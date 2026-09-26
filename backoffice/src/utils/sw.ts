// Registro centralizado del Service Worker.
// Vite tiene base: '/backoffice/', por lo que public/ se sirve bajo /backoffice/
// tanto en dev como en produccion. Usamos siempre ese path con scope restringido.

const SW_PATH = '/backoffice/sw-geolocation.js'
const SW_OPTIONS: RegistrationOptions = { scope: '/backoffice/' }
const SW_READY_TIMEOUT_MS = 3000

let readyPromise: Promise<boolean> | null = null

export async function registerAppSw(): Promise<boolean> {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return false
  if (readyPromise) return readyPromise

  readyPromise = (async () => {
    try {
      const reg = await navigator.serviceWorker.getRegistration(SW_PATH)
      if (!reg) {
        await navigator.serviceWorker.register(SW_PATH, SW_OPTIONS)
      }
      await Promise.race([
        navigator.serviceWorker.ready,
        new Promise((resolve) => setTimeout(resolve, SW_READY_TIMEOUT_MS)),
      ])
      return true
    } catch (e) {
      readyPromise = null
      return false
    }
  })()

  return readyPromise
}