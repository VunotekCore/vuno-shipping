<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { driverLocationService, routeService, type DeliveryRoute, type RouteStop } from '../../services/routeService'
import { useAuthStore } from '../../stores/auth'
import { useBackgroundGeo } from '../../composables/useBackgroundGeo'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { NICARAGUA_MAP_OPTIONS, NICARAGUA_BOUNDS } from '../../utils/map'

const router = useRouter()
const auth = useAuthStore()
const geo = useBackgroundGeo()
const loading = ref(true)
const routeData = ref<DeliveryRoute | null>(null)
const stops = ref<RouteStop[]>([])
const error = ref('')
const mapContainer = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let historyPolyline: L.Polyline | null = null
let liveMarker: L.CircleMarker | null = null
let historyTimer: ReturnType<typeof setInterval> | null = null

async function load() {
  loading.value = true
  try {
    const res = await driverLocationService.driverRoutes()
    const routes: DeliveryRoute[] = res.data.data.routes ?? []
    const active = routes.find(r => r.status_name === 'active') || routes[0] || null
    routeData.value = active

    if (active) {
      const stopRes = await routeService.get(active.id)
      stops.value = stopRes.data.data.route.stops ?? []
    }

    await nextTick()
    initMap()
    await loadOwnHistory()
    historyTimer = setInterval(loadOwnHistory, 30000)
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar'
  } finally {
    loading.value = false
  }
}

async function loadOwnHistory() {
  if (!auth.user?.id) return
  try {
    const res = await driverLocationService.history(auth.user.id)
    drawOwnHistory(res.data.data.history ?? [])
  } catch {
    /* sin historial aun */
  }
}

function drawOwnHistory(history: Array<{ latitude: number; longitude: number; recorded_at: string }>) {
  if (!map) return
  if (historyPolyline) {
    historyPolyline.remove()
    historyPolyline = null
  }
  const latlngs = history
    .map(h => [Number(h.latitude), Number(h.longitude)] as [number, number])
    .filter(ll => !Number.isNaN(ll[0]) && !Number.isNaN(ll[1]))
  if (latlngs.length >= 2) {
    historyPolyline = L.polyline(latlngs, { color: '#f97316', weight: 4, opacity: 0.85 }).addTo(map)
  }
}

function upsertLiveMarker(lat: number, lng: number) {
  if (!map) return
  if (!liveMarker) {
    liveMarker = L.circleMarker([lat, lng], {
      radius: 9,
      fillColor: '#ef4444',
      color: '#fff',
      weight: 2,
      fillOpacity: 1,
    }).addTo(map)
    liveMarker.bindPopup('<b>Tu ubicación</b><br/>GPS en vivo')
  } else {
    liveMarker.setLatLng([lat, lng])
  }
}

watch(() => geo.lastFix.value, (fix) => {
  if (fix) upsertLiveMarker(fix.latitude, fix.longitude)
})

function initMap() {
  if (!mapContainer.value) return
  if (map) { map.remove(); map = null }
  historyPolyline = null
  liveMarker = null

  map = L.map(mapContainer.value, {
    zoomControl: true,
    attributionControl: false,
    ...NICARAGUA_MAP_OPTIONS,
  }).setView([12.1149, -86.2362], 8)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, bounds: NICARAGUA_BOUNDS })
    .addTo(map!)

  const rd = routeData.value
  if (!rd) return

  const bounds: L.LatLngBoundsLiteral = []

  // Trajectory
  if (rd.trajectory) {
    try {
      const coords = typeof rd.trajectory === 'string' ? JSON.parse(rd.trajectory) : rd.trajectory
      if (Array.isArray(coords) && coords.length >= 2) {
        const latlngs: [number, number][] = coords.map((c: any) => [c[1] ?? c.lat, c[0] ?? c.lng])
        L.polyline(latlngs, { color: rd.color || '#22c55e', weight: 4, opacity: 0.8 }).addTo(map!)
        latlngs.forEach(ll => bounds.push(ll))
      }
    } catch {}
  }

  // Stops
  stops.value.forEach((s) => {
    if (!s.latitude || !s.longitude) return
    const lat = Number(s.latitude)
    const lng = Number(s.longitude)
    const isPending = s.status_name === 'pending'
    const isVisited = s.status_name === 'visited'

    const color = isVisited ? '#22c55e' : isPending ? '#eab308' : '#64748b'

    const marker = L.circleMarker([lat, lng], {
      radius: isPending ? 10 : isVisited ? 7 : 7,
      fillColor: color,
      color: '#fff',
      weight: 2,
      fillOpacity: 0.9,
    }).addTo(map!)

    marker.bindPopup(`
      <b>#${s.stop_order}</b> ${s.client_name || ''}<br/>
      ${s.address || ''}<br/>
      ${s.from_zone_center ? '<i style="color:#94a3b8">Punto por centro de zona (sin GPS)</i><br/>' : ''}
      <span style="color:${color}">${s.status_name}</span>
    `)
    bounds.push([lat, lng])
  })

  // Live GPS
  if (geo.lastFix.value) {
    upsertLiveMarker(geo.lastFix.value.latitude, geo.lastFix.value.longitude)
  }

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 })
  }
}

function goBack() {
  router.push('/conductor')
}

onMounted(() => load())

onBeforeUnmount(() => {
  if (historyTimer) {
    clearInterval(historyTimer)
    historyTimer = null
  }
  if (map) {
    map.remove()
    map = null
  }
})
</script>

<template>
  <div class="space-y-4">
    <button class="flex items-center gap-2 text-sm text-[#94a3b8] hover:text-[#dae2fd]" @click="goBack">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Volver
    </button>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-[#69dca4] border-t-transparent" />
    </div>

    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-sm text-[#DC2626]">{{ error }}</p>
    </div>

    <div v-else-if="!routeData" class="admin-card p-8 text-center">
      <p class="font-medium text-[#64748b]">Sin ruta activa</p>
    </div>

    <template v-else>
      <div class="flex items-center gap-2">
        <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: routeData.color || '#22c55e' }" />
        <h2 class="font-semibold">{{ routeData.name }}</h2>
        <span class="ml-auto text-xs text-[#64748b]">{{ stops.filter(s => s.status_name === 'pending').length }} pendientes</span>
      </div>

      <div ref="mapContainer" class="h-[60vh] w-full rounded-xl border border-[#1e293b] overflow-hidden" />

      <!-- Mini legend -->
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#64748b]">
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#eab308]" /> Pendiente</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#22c55e]" /> Entregado</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#64748b]" /> Otro</span>
        <span class="flex items-center gap-1.5"><span class="h-1 w-5 rounded" style="background:#f97316" /> Tu recorrido</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#ef4444]" /> Tu ubicación</span>
      </div>
    </template>
  </div>
</template>
