<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { driverLocationService, routeRunService, type RouteRun, type RouteStop } from '../../services/routeService'
import { useAuthStore } from '../../stores/auth'
import { useBackgroundGeo } from '../../composables/useBackgroundGeo'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { NICARAGUA_MAP_OPTIONS, NICARAGUA_BOUNDS, stabilizeMapSize, fitMapToBounds } from '../../utils/map'
import { statusLabel } from '../../utils/format'

const router = useRouter()
const auth = useAuthStore()
const geo = useBackgroundGeo()
const loading = ref(true)
const routeData = ref<RouteRun | null>(null)
const stops = ref<RouteStop[]>([])
const error = ref('')
const mapContainer = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let historyPolyline: L.Polyline | null = null
let liveMarker: L.CircleMarker | null = null
let mapBounds: L.LatLngBoundsLiteral = []
let cleanupMapSize: (() => void) | null = null
const canRecenter = ref(false)
let historyTimer: ReturnType<typeof setInterval> | null = null

function parseCoords(raw: string | null | undefined): [number, number][] {
  if (!raw) return []
  try {
    const coords = typeof raw === 'string' ? JSON.parse(raw) : raw
    if (!Array.isArray(coords)) return []
    return coords.map((c: any): [number, number] => [Number(c[0] ?? c.lat), Number(c[1] ?? c.lng)]).filter((ll) => !Number.isNaN(ll[0]) && !Number.isNaN(ll[1]))
  } catch {
    return []
  }
}

async function load() {
  loading.value = true
  try {
    const res = await driverLocationService.driverRoutes()
    const runs: RouteRun[] = res.data.data.runs ?? []
    const active = runs.find(r => r.status_name === 'active') || runs[0] || null

    if (active) {
      const detail = await routeRunService.get(active.id)
      const run = detail.data.data.run
      routeData.value = run
      stops.value = run.stops ?? []
    } else {
      routeData.value = null
      stops.value = []
    }
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar'
  } finally {
    loading.value = false
  }

  await nextTick()
  initMap()
  await loadOwnHistory()
  historyTimer = setInterval(loadOwnHistory, 30000)
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
  if (cleanupMapSize) {
    cleanupMapSize()
    cleanupMapSize = null
  }
  historyPolyline = null
  liveMarker = null
  mapBounds = []
  canRecenter.value = false

  map = L.map(mapContainer.value, {
    zoomControl: true,
    attributionControl: false,
    ...NICARAGUA_MAP_OPTIONS,
  }).setView([12.1149, -86.2362], 8)

  cleanupMapSize = stabilizeMapSize(map, mapContainer.value)

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
        L.polyline(latlngs, { color: rd.route_color || '#22c55e', weight: 4, opacity: 0.8 }).addTo(map!)
        latlngs.forEach(ll => bounds.push(ll))
      }
    } catch {}
  }

  // Cobertura de zonas de la ruta
  if (rd.zones && rd.zones.length > 0) {
    rd.zones.forEach((z: any) => {
      if (z.lat == null || z.lng == null) return
      const lat = Number(z.lat)
      const lng = Number(z.lng)
      const circle = L.circle([lat, lng], {
        radius: 1500,
        color: rd.route_color || '#22c55e',
        fillColor: rd.route_color || '#22c55e',
        fillOpacity: 0.08,
        weight: 1,
        opacity: 0.35,
      }).addTo(map!)
      circle.bindPopup(`<b>${z.name}</b><br/>Zona cubierta por la ruta`)
      bounds.push([lat, lng])
    })
  }

  // Ruta optima OSRM (azul punteada)
  const optimized = parseCoords(rd.optimized_geometry)
  if (optimized.length >= 2) {
    L.polyline(optimized, { color: '#00A8FF', weight: 3, dashArray: '8 8', opacity: 0.9 }).addTo(map!)
    optimized.forEach(ll => bounds.push(ll))
  }

  // Stops
  stops.value.forEach((s) => {
    if (!s.latitude || !s.longitude) return
    const lat = Number(s.latitude)
    const lng = Number(s.longitude)
    const isPending = s.status_name === 'pending'
    const isDelivered = s.status_name === 'delivered'

    const color = isDelivered ? '#22c55e' : isPending ? '#eab308' : '#64748b'

    const marker = L.circleMarker([lat, lng], {
      radius: isPending ? 10 : isDelivered ? 7 : 7,
      fillColor: color,
      color: '#fff',
      weight: 2,
      fillOpacity: 0.9,
    }).addTo(map!)

    marker.bindPopup(`
      <b>#${s.stop_order}</b> ${s.client_name || ''}<br/>
      ${s.address || ''}<br/>
      ${s.from_zone_center ? '<i style="color:#94a3b8">Punto por centro de zona (sin GPS)</i><br/>' : ''}
      <span style="color:${color}">${statusLabel(s.status_name)}</span>
    `)
    bounds.push([lat, lng])
  })

  // Inicio / Fin de ruta (offset del centro de zona para no tapar stops fallback)
  if (rd.start_latitude && rd.start_longitude) {
    const ll: [number, number] = [Number(rd.start_latitude) + 0.004, Number(rd.start_longitude) - 0.004]
    L.circleMarker(ll, { radius: 6, fillColor: '#22c55e', color: '#fff', weight: 2, fillOpacity: 1 }).addTo(map!)
      .bindPopup('Inicio')
    bounds.push(ll)
  }
  if (rd.end_latitude && rd.end_longitude) {
    const ll: [number, number] = [Number(rd.end_latitude) - 0.004, Number(rd.end_longitude) + 0.004]
    L.circleMarker(ll, { radius: 6, fillColor: '#ef4444', color: '#fff', weight: 2, fillOpacity: 1 }).addTo(map!)
      .bindPopup('Fin')
    bounds.push(ll)
  }

  // Live GPS
  if (geo.lastFix.value) {
    upsertLiveMarker(geo.lastFix.value.latitude, geo.lastFix.value.longitude)
  }

  mapBounds = bounds
  canRecenter.value = bounds.length > 0
  if (map && bounds.length > 0) {
    fitMapToBounds(map, bounds, 50)
  }
}

function recenterMap() {
  if (map && mapBounds.length > 0) {
    fitMapToBounds(map, mapBounds, 50)
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
  if (cleanupMapSize) {
    cleanupMapSize()
    cleanupMapSize = null
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
        <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: routeData.route_color || '#22c55e' }" />
        <h2 class="font-semibold">{{ routeData.route_name }}</h2>
        <span class="ml-auto text-xs text-[#64748b]">{{ stops.filter(s => s.status_name === 'pending').length }} pendientes</span>
      </div>

      <div ref="mapContainer" class="relative h-[60vh] w-full rounded-xl border border-[#1e293b] overflow-hidden">
        <button
          v-if="canRecenter"
          class="absolute right-3 top-3 z-[1010] rounded-lg bg-[#0b1326]/90 px-3 py-1.5 text-xs font-medium text-[#dae2fd] shadow-md transition-colors hover:bg-[#1e293b]"
          @click.stop="recenterMap"
        >
          Re-centrar ruta
        </button>
      </div>

      <!-- Mini legend -->
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#64748b]">
        <span class="flex items-center gap-1.5"><span class="h-1 w-5 rounded" style="background:#22c55e" /> Trayectoria ruta</span>
        <span class="flex items-center gap-1.5"><span class="h-0.5 w-5 border-t-2 border-dashed" style="border-color:#00A8FF" /> Ruta óptima</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#eab308]" /> Pendiente</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#22c55e]" /> Entregado</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#64748b]" /> Otro</span>
        <span class="flex items-center gap-1.5"><span class="h-1 w-5 rounded" style="background:#f97316" /> Tu recorrido</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#ef4444]" /> Fin / Tu ubicación</span>
      </div>
    </template>
  </div>
</template>
