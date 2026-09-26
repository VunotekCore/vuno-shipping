import { ref } from 'vue'
import { pushService } from '../services/pushService'
import { registerAppSw } from '../utils/sw'

// usePushNotifications — suscripcion Web Push del usuario actual.
// supported: si el browser soporta push + SW + Notification.
// permission: estado del permiso de notificaciones.
// subscribed: si hay una suscripcion activa sincronizada con el backend.

export type PushPermission = 'default' | 'granted' | 'denied'

const supported =
  typeof navigator !== 'undefined' &&
  'serviceWorker' in navigator &&
  'PushManager' in window &&
  'Notification' in window

const permission = ref<PushPermission>(
  supported ? ((Notification.permission as PushPermission) || 'default') : 'default'
)
const subscribed = ref(false)
const enabling = ref(false)
const disabling = ref(false)
const error = ref('')

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = window.atob(base64)
  const outputArray = new Uint8Array(rawData.length)
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i)
  }
  return outputArray
}

async function getSubscription(): Promise<PushSubscription | null> {
  const reg = await registerAppSw()
  if (!reg) return null
  const sw = await navigator.serviceWorker.getRegistration()
  if (!sw || !sw.pushManager) return null
  try {
    return await sw.pushManager.getSubscription()
  } catch (e) {
    return null
  }
}

async function syncState(): Promise<boolean> {
  const sub = await getSubscription()
  subscribed.value = !!sub && sub.endpoint.length > 0
  return subscribed.value
}

async function subscribeAndSync(): Promise<boolean> {
  const reg = await registerAppSw()
  if (!reg) return false
  const sw = await navigator.serviceWorker.getRegistration()
  if (!sw || !sw.pushManager) return false

  let subscription = await getSubscription()
  if (!subscription) {
    try {
      const cfg = await pushService.config()
      subscription = await sw.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(cfg.vapid_public_key),
      })
    } catch (e) {
      error.value = 'No se pudo suscribir el dispositivo'
      return false
    }
  }

  const raw = subscription.toJSON()
  const p256dh = raw.keys?.p256dh ?? ''
  const auth = raw.keys?.auth ?? ''
  if (!p256dh || !auth) {
    error.value = 'Suscripcion invalida del navegador'
    return false
  }

  try {
    await pushService.subscribe({ endpoint: subscription.endpoint, p256dh, auth })
    subscribed.value = true
    error.value = ''
    return true
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al guardar la suscripcion'
    return false
  }
}

export function usePushNotifications() {
  async function enable(): Promise<boolean> {
    if (!supported) {
      error.value = 'Tu navegador no soporta notificaciones push'
      return false
    }

    enabling.value = true
    error.value = ''
    try {
      if (Notification.permission === 'denied') {
        error.value = 'Notificaciones bloqueadas. Habilitalas desde los ajustes del navegador.'
        return false
      }

      if (Notification.permission !== 'granted') {
        const result = await Notification.requestPermission()
        permission.value = result as PushPermission
        if (result !== 'granted') {
          error.value = 'Permiso de notificaciones no otorgado'
          return false
        }
      } else {
        permission.value = 'granted'
      }

      return await subscribeAndSync()
    } finally {
      enabling.value = false
    }
  }

  async function disable(): Promise<boolean> {
    disabling.value = true
    error.value = ''
    try {
      const sub = await getSubscription()
      if (sub) {
        await sub.unsubscribe()
      }
      subscribed.value = false
      const subSnapshot = await getSubscription()
      if (!subSnapshot) {
        subscribed.value = false
      }
      return true
    } catch (e) {
      error.value = 'No se pudo desactivar la suscripcion'
      return false
    } finally {
      disabling.value = false
    }
  }

  function init() {
    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return
    navigator.serviceWorker.addEventListener('message', (event) => {
      const data = event.data || {}
      if (data.type === 'PUSH_SUB_CHANGED') {
        syncState()
      }
    })
    if ('pushManager' in window && 'Notification' in window && Notification.permission === 'granted') {
      syncState().then(() => {})
    }
  }

  return {
    supported,
    permission,
    subscribed,
    enabling,
    disabling,
    error,
    enable,
    disable,
    init,
  }
}