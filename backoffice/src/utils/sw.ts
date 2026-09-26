// Registro centralizado del Service Worker.
// En dev, Vite sirve public/ en la raiz (/sw-geolocation.js). En produccion, el SW
// vive bajo /backoffice/ con scope restringido a esa ruta.

const SW_PATH = import.meta.env.DEV ? '/sw-geolocation.js' : '/backoffice/sw-geolocation.js'
const SW_OPTIONS: RegistrationOptions = import.meta.env.DEV
  ? {}
  : { scope: '/backoffice/' }
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