// sw-geolocation.js
// Service Worker de geolocalizacion para conductores.
// La geolocalizacion en si la maneja la pagina (watchPosition) porque es una API DOM.
// Este SW aporta: cola offline de posiciones + background sync + retry cuando vuelve la red.
'use strict'

const CONFIG = {
  APP_PREFIX: '/backoffice/',
  SYNC_TAG: 'geo-sync',
  MAX_QUEUE: 200,
  CACHE_NAME: 'geo-shell-v2',
}

function isDevHost() {
  const host = self.location.hostname
  return host === 'localhost' || host === '127.0.0.1' || host === '::1'
}

// En dev no cachear el app shell (vite sirve modulos .ts/.vue con HMR).
function shouldCacheGet(url) {
  if (isDevHost()) return false
  if (url.pathname.endsWith('sw-geolocation.js')) return false
  if (/\/@vite\/|\/@fs\/|\/@id\/|\.ts$|\.vue$/.test(url.pathname)) return false
  return true
}

// --- IndexedDB helpers (kv + queue) ---
const DB_NAME = 'geo-sw-db'
const DB_VERSION = 1
let dbPromise = null

function openDb() {
  if (dbPromise) return dbPromise
  dbPromise = new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION)
    req.onupgradeneeded = () => {
      const db = req.result
      if (!db.objectStoreNames.contains('kv')) db.createObjectStore('kv')
      if (!db.objectStoreNames.contains('queue')) {
        db.createObjectStore('queue', { keyPath: 'id', autoIncrement: true })
      }
    }
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
  return dbPromise
}

async function kvGet(key) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('kv', 'readonly')
    const req = tx.objectStore('kv').get(key)
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}

async function kvSet(key, value) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('kv', 'readwrite')
    tx.objectStore('kv').put(value, key)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

async function queueAdd(entry) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('queue', 'readwrite')
    tx.objectStore('queue').add(entry)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

async function queueAll() {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('queue', 'readonly')
    const req = tx.objectStore('queue').getAll()
    req.onsuccess = () => resolve(req.result || [])
    req.onerror = () => reject(req.error)
  })
}

async function queueClear(ids) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction('queue', 'readwrite')
    ids.forEach((id) => tx.objectStore('queue').delete(id))
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

// --- Notify open clients ---
async function notify(message) {
  try {
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    clients.forEach((client) => client.postMessage(message))
  } catch (e) {
    // sin clientes abiertos: ignorar
  }
}

// --- Flush de cola offline ---
async function flushQueue() {
  const config = await kvGet('config')
  if (!config || !config.active) return
  const entries = await queueAll()
  if (entries.length === 0) return

  let sent = 0
  for (const entry of entries) {
    try {
      const res = await fetch(entry.url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(config.csrfToken ? { 'X-CSRF-Token': config.csrfToken } : {}),
        },
        body: entry.body,
        credentials: 'same-origin',
      })
      if (!res.ok) break
      sent++
    } catch (e) {
      break
    }
  }

  if (sent > 0) {
    await queueClear(entries.slice(0, sent).map((e) => e.id))
    const remaining = await queueAll()
    notify({ type: 'GEO_FLUSHED', count: sent, remaining: remaining.length })
  }
}

// --- Ciclo de vida ---
self.addEventListener('install', (e) => {
  self.skipWaiting()
})

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    await self.clients.claim()
    const cacheKeys = await caches.keys()
    await Promise.all(
      cacheKeys.filter((k) => k !== CONFIG.CACHE_NAME).map((k) => caches.delete(k))
    )
    const config = await kvGet('config')
    if (config && config.active) await flushQueue()
  })())
})

// --- Mensajes de la pagina ---
self.addEventListener('message', (e) => {
  const data = e.data || {}
  if (data.type === 'START_GEO') {
    kvSet('config', { active: true, csrfToken: data.csrfToken || '', startedAt: Date.now() })
      .then(() => notify({ type: 'GEO_STATUS', active: true }))
      .catch(() => {})
  } else if (data.type === 'STOP_GEO') {
    kvSet('config', { active: false })
      .then(() => notify({ type: 'GEO_STATUS', active: false }))
      .catch(() => {})
  } else if (data.type === 'UPDATE_CSRF') {
    kvGet('config')
      .then((config) => kvSet('config', { ...(config || {}), csrfToken: data.csrfToken || '' }))
      .catch(() => {})
  } else if (data.type === 'FLUSH_GEO') {
    flushQueue()
  }
})

// --- Background sync + eventos de red ---
self.addEventListener('sync', (e) => {
  if (e.tag === CONFIG.SYNC_TAG) e.waitUntil(flushQueue())
})

self.addEventListener('online', () => {
  flushQueue()
})

self.addEventListener('periodicsync', (e) => {
  if (e.tag === CONFIG.SYNC_TAG) e.waitUntil(flushQueue())
})

// --- Push notifications ---
function pushUrl(url) {
  if (!url) return '/backoffice/conductor'
  if (url.startsWith('http')) return url
  return url.startsWith('/') ? url : '/' + url
}

self.addEventListener('push', (e) => {
  let payload = {}
  try {
    payload = e.data ? e.data.json() : {}
  } catch (err) {
    payload = { title: 'Notificación', body: '' }
  }
  const title = payload.title || 'Shipping Agency'
  const options = {
    body: payload.body || '',
    icon: self.location.origin + '/favicon.svg',
    badge: self.location.origin + '/favicon.svg',
    data: { url: pushUrl(payload.url) },
    tag: payload.tag || 'shipping-push',
  }
  e.waitUntil(self.registration.showNotification(title, options))
})

self.addEventListener('notificationclick', (e) => {
  e.notification.close()
  const targetUrl = e.notification.data && e.notification.data.url
    ? e.notification.data.url
    : '/backoffice/conductor'

  const fullUrl = new URL(targetUrl, self.location.origin).href

  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        try {
          const clientUrl = new URL(client.url)
          if (clientUrl.origin === self.location.origin) {
            client.navigate(fullUrl)
            return client.focus()
          }
        } catch (err) { /* ignorar */ }
      }
      return self.clients.openWindow(fullUrl)
    })
  )
})

self.addEventListener('pushsubscriptionchange', () => {
  // La pagina re-suscribe (necesita la VAPID key + CSRF desde la sesion).
  notify({ type: 'PUSH_SUB_CHANGED' })
})

// --- Fetch ---
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url)

  // Posts de ubicacion: relay con cola offline
  if (e.request.method === 'POST' && url.pathname.endsWith('/driver/location.php')) {
    e.respondWith(handleLocationPost(e.request))
    return
  }

  // Cache offline del app shell (GET bajo /backoffice/)
  if (
    e.request.method === 'GET' &&
    url.origin === self.location.origin &&
    url.pathname.startsWith(CONFIG.APP_PREFIX) &&
    shouldCacheGet(url)
  ) {
    // Navegacion: network-first (siempre HTML fresco, cache como fallback)
    if (e.request.mode === 'navigate') {
      e.respondWith(networkFirst(e.request))
    } else {
      e.respondWith(staleWhileRevalidate(e.request))
    }
  }
})

async function handleLocationPost(request) {
  const cloned = request.clone()
  try {
    const res = await fetch(request)
    if (!res.ok && res.status === 401) {
      // sesion expirada: la pagina maneja el refresh via axios interceptor
      notify({ type: 'GEO_UNAUTH' })
    }
    return res
  } catch (err) {
    // Sin red: encolar
    try {
      const config = await kvGet('config')
      if (config && config.active) {
        const text = await cloned.text()
        const queue = await queueAll()
        if (queue.length < CONFIG.MAX_QUEUE) {
          await queueAdd({ url: request.url, body: text, queuedAt: Date.now() })
        }
        if ('sync' in self.registration) {
          self.registration.sync.register(CONFIG.SYNC_TAG).catch(() => {})
        }
        notify({ type: 'GEO_QUEUED', count: Math.min(queue.length + 1, CONFIG.MAX_QUEUE) })
      }
    } catch (e) {
      // sin almacen disponible: descartar
    }
    return new Response(JSON.stringify({ success: false, error: 'offline' }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' },
    })
  }
}

async function networkFirst(request) {
  const cache = await caches.open(CONFIG.CACHE_NAME)
  try {
    const res = await fetch(request)
    if (res.ok) cache.put(request, res.clone())
    return res
  } catch (e) {
    const cached = await cache.match(request)
    if (cached) return cached
    throw new Error('offline')
  }
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(CONFIG.CACHE_NAME)
  const cached = await cache.match(request)

  const network = fetch(request)
    .then((res) => {
      if (res.ok) cache.put(request, res.clone())
      return res
    })
    .catch(() => {
      if (cached) return cached
      throw new Error('offline')
    })

  return cached || network
}
