import { ref } from 'vue'
import { driverLocationService } from '../services/routeService'
import { registerAppSw } from '../utils/sw'

// useBackgroundGeo — singleton para tracking de geolocalizacion del conductor.
// La pagina mantiene watchPosition (la geolocalizacion es una API DOM, no disponible
// en el Service Worker). El SW (sw-geolocation.js) se encarga de cola offline +
// background sync + retry cuando la pagina pierde red o se cierra.

const POST_INTERVAL_MS = 30000
const CSRF_POLL_MS = 60000

interface GeoFix {
  latitude: number
  longitude: number
  accuracy: number | null
  speed: number | null
  heading: number | null
}

// --- Estado singleton ---
const supported = typeof navigator !== 'undefined' && 'serviceWorker' in navigator && 'geolocation' in navigator
const tracking = ref(false)
const queueCount = ref(0)
const swActive = ref(false)
const lastFix = ref<GeoFix | null>(null)
const lastSyncAt = ref<string | null>(null)
const swReady = ref(false)

let watchId: number | null = null
let postTimer: number | null = null
let csrfTimer: number | null = null
let wakeLock: any = null
let lastCsrf = ''
let batteryLevel: number | null = null

function readCsrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : ''
}

function sendMessage(message: unknown) {
  if (!('serviceWorker' in navigator)) return
  navigator.serviceWorker.ready
    .then((reg) => reg.active?.postMessage(message))
    .catch(() => {})
}

async function requestWakeLock() {
  try {
    if ('wakeLock' in navigator) {
      wakeLock = await (navigator as any).wakeLock.request('screen')
      wakeLock.addEventListener?.('release', () => {
        wakeLock = null
      })
    }
  } catch (e) {
    wakeLock = null
  }
}

function releaseWakeLock() {
  try {
    wakeLock?.release?.()
  } catch (e) {
    // ignorar
  }
  wakeLock = null
}

function initBattery() {
  if ('getBattery' in navigator) {
    ;(navigator as any)
      .getBattery()
      .then((battery: any) => {
        batteryLevel = battery.level * 100
        battery.addEventListener?.('levelchange', () => {
          batteryLevel = battery.level * 100
        })
      })
      .catch(() => {})
  }
}

async function sendCurrentPosition() {
  if (!lastFix.value) return
  try {
    await driverLocationService.post({
      latitude: lastFix.value.latitude,
      longitude: lastFix.value.longitude,
      accuracy: lastFix.value.accuracy ?? undefined,
      speed: lastFix.value.speed ?? undefined,
      heading: lastFix.value.heading ?? undefined,
      battery_level: batteryLevel ?? undefined,
      is_active: 1,
    })
    lastSyncAt.value = new Date().toISOString()
  } catch (e) {
    // 503 = offline: el SW encolo y avisa con GEO_QUEUED
    const status = (e as any)?.response?.status
    if (status !== 503) {
      // otros errores (401, red): el SW reencola si es red, el refresh lo maneja axios
    }
  }
}

function onGeolocationUpdate(pos: GeolocationPosition) {
  lastFix.value = {
    latitude: pos.coords.latitude,
    longitude: pos.coords.longitude,
    accuracy: pos.coords.accuracy ?? null,
    speed: pos.coords.speed ?? null,
    heading: pos.coords.heading ?? null,
  }
}

function pollCsrf() {
  const token = readCsrfToken()
  if (token && token !== lastCsrf) {
    lastCsrf = token
    sendMessage({ type: 'UPDATE_CSRF', csrfToken: token })
  }
}

function onMessage(event: MessageEvent) {
  const data = event.data || {}
  if (data.type === 'GEO_STATUS') {
    swActive.value = !!data.active
  } else if (data.type === 'GEO_QUEUED') {
    queueCount.value = data.count ?? queueCount.value + 1
  } else if (data.type === 'GEO_FLUSHED') {
    queueCount.value = data.remaining ?? 0
    if (queueCount.value === 0) lastSyncAt.value = new Date().toISOString()
  } else if (data.type === 'GEO_UNAUTH') {
    swActive.value = false
  }
}

async function registerSw(): Promise<boolean> {
  const ok = await registerAppSw()
  if (!ok) return false
  if (swReady.value) return true
  navigator.serviceWorker.addEventListener('message', onMessage)
  swReady.value = true
  return true
}

export function useBackgroundGeo() {
  async function start() {
    if (!supported || tracking.value) return
    const ok = await registerSw()
    if (!ok) return

    tracking.value = true
    queueCount.value = 0

    initBattery()
    await requestWakeLock()

    const token = readCsrfToken()
    lastCsrf = token
    sendMessage({ type: 'START_GEO', csrfToken: token })

    // Enviar el primer fix inmediatamente
    if (lastFix.value) sendCurrentPosition()

    watchId = navigator.geolocation.watchPosition(
      (pos) => {
        onGeolocationUpdate(pos)
        sendCurrentPosition()
      },
      () => {},
      { enableHighAccuracy: true, timeout: 30000, maximumAge: 30000 }
    )

    postTimer = window.setInterval(() => {
      sendCurrentPosition()
    }, POST_INTERVAL_MS)

    csrfTimer = window.setInterval(pollCsrf, CSRF_POLL_MS)
  }

  function stop() {
    if (!tracking.value) return
    tracking.value = false
    swActive.value = false
    queueCount.value = 0

    if (watchId !== null) {
      navigator.geolocation.clearWatch(watchId)
      watchId = null
    }
    if (postTimer !== null) {
      clearInterval(postTimer)
      postTimer = null
    }
    if (csrfTimer !== null) {
      clearInterval(csrfTimer)
      csrfTimer = null
    }
    releaseWakeLock()
    sendMessage({ type: 'STOP_GEO' })

    // Notificar inactivo al backend
    driverLocationService
      .post({ latitude: 0, longitude: 0, is_active: 0 })
      .catch(() => {})
  }

  async function flush() {
    sendMessage({ type: 'FLUSH_GEO' })
    if (lastFix.value) await sendCurrentPosition()
  }

  return {
    supported,
    tracking,
    swActive,
    queueCount,
    lastFix,
    lastSyncAt,
    start,
    stop,
    flush,
  }
}
