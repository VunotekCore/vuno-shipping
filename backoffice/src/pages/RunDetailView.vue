<script setup lang="ts">
import { ref, onMounted, computed, nextTick, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { routeRunService, routeStopService, driverLocationService, type RouteRun, type RouteStop, type DriverLocation } from '../services/routeService'
import { userService, type User } from '../services/userService'
import { vehicleService, type Vehicle } from '../services/vehicleService'
import { useToast } from '../composables/useToast'
import AppModal from '../components/ui/AppModal.vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { NICARAGUA_MAP_OPTIONS, NICARAGUA_BOUNDS, stabilizeMapSize, fitMapToBounds } from '../utils/map'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const runId = computed(() => Number(route.params.id))
const loading = ref(false)
const runData = ref<RouteRun | null>(null)
const stops = ref<RouteStop[]>([])
const error = ref('')

// Modal states
const showAssignModal = ref(false)
const candidatePackages = ref<any[]>([])
const selectedPackageIds = ref<number[]>([])
const loadingPackages = ref(false)
const saving = ref(false)
const optimizing = ref(false)
const statusLoading = ref(false)

const pendingStops = computed(() => stops.value.filter(s => s.status_name === 'pending'))
const pendingWithCoords = computed(() =>
  pendingStops.value.filter(s => Number(s.latitude) && Number(s.longitude)),
)
const canOptimize = computed(() => pendingWithCoords.value.length >= 2)

// Asignacion de conductor/vehiculo por dia
const showAssignDriverModal = ref(false)
const assignDriverId = ref<number | null>(null)
const assignVehicleId = ref<number | null>(null)
const driverOptions = ref<User[]>([])
const vehicleOptions = ref<Vehicle[]>([])
const loadingAssignOptions = ref(false)
const savingAssignment = ref(false)

// Stop action modal (skip/fail con observacion)
const showStopActionModal = ref(false)
const stopActionTarget = ref<RouteStop | null>(null)
const stopActionStatus = ref('skipped')
const stopActionNotes = ref('')
const stopActionSaving = ref(false)

// Leaflet
let map: L.Map | null = null
const mapContainer = ref<HTMLElement | null>(null)
let liveMarker: L.CircleMarker | null = null
let vehicleTrajectory: L.Polyline | null = null
let routeCorridor: [number, number][] = []
let mapBounds: L.LatLngBoundsLiteral = []
let cleanupMapSize: (() => void) | null = null
const canRecenter = ref(false)

const isRunActive = computed(() => runData.value?.status_name === 'active')
let driverTimer: ReturnType<typeof setInterval> | null = null
let tickCount = 0
const offRoute = ref(false)

function haversine(a: [number, number], b: [number, number]): number {
  const R = 6371000
  const dLat = (b[0] - a[0]) * Math.PI / 180
  const dLng = (b[1] - a[1]) * Math.PI / 180
  const s = Math.sin(dLat / 2) ** 2
    + Math.cos(a[0] * Math.PI / 180) * Math.cos(b[0] * Math.PI / 180) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(s))
}

function distanceToCorridor(lat: number, lng: number): number {
  if (routeCorridor.length === 0) return 0
  const p: [number, number] = [lat, lng]
  let min = Infinity
  for (let i = 0; i < routeCorridor.length - 1; i++) {
    const a = routeCorridor[i]
    const b = routeCorridor[i + 1]
    const dx = b[0] - a[0]
    const dy = b[1] - a[1]
    const len2 = dx * dx + dy * dy
    let t = len2 === 0 ? 0 : ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / len2
    t = Math.max(0, Math.min(1, t))
    const proj: [number, number] = [a[0] + t * dx, a[1] + t * dy]
    const d = haversine(p, proj)
    if (d < min) min = d
  }
  return min
}

async function loadVehicleHistory() {
  const userId = runData.value?.assigned_to
  if (!userId || !map) return
  try {
    const res = await driverLocationService.history(userId)
    const history = (res.data.data as { history?: Array<{ latitude: number; longitude: number; recorded_at: string }> }).history ?? []
    if (vehicleTrajectory) {
      vehicleTrajectory.remove()
      vehicleTrajectory = null
    }
    const latlngs = history
      .map(h => [Number(h.latitude), Number(h.longitude)] as [number, number])
      .filter(ll => !Number.isNaN(ll[0]) && !Number.isNaN(ll[1]))
    if (latlngs.length >= 2) {
      vehicleTrajectory = L.polyline(latlngs, { color: '#f97316', weight: 3, opacity: 0.85 }).addTo(map)
    }
  } catch {
    /* sin historial aun */
  }
}

function startDriverPolling() {
  stopDriverPolling()
  const userId = runData.value?.assigned_to
  if (!userId) return
  const tick = async () => {
    tickCount++
    if (tickCount % 3 === 1) loadVehicleHistory()
    try {
      const res = await driverLocationService.get(userId)
      const loc = (res.data.data as { location?: DriverLocation }).location
      if (!loc || !loc.latitude || !loc.longitude) return
      const lat = Number(loc.latitude)
      const lng = Number(loc.longitude)
      upsertLiveMarker(lat, lng)
      offRoute.value = routeCorridor.length > 0 && distanceToCorridor(lat, lng) > 250
    } catch {
      /* silencioso: sin señal del conductor */
    }
  }
  tick()
  driverTimer = setInterval(tick, 10000)
}

function stopDriverPolling() {
  if (driverTimer) {
    clearInterval(driverTimer)
    driverTimer = null
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
    liveMarker.bindPopup('<b>Conductor</b><br/>Ubicación en vivo')
  } else {
    liveMarker.setLatLng([lat, lng])
  }
}

const statusBadge = (status: string | null | undefined): string => {
  const m: Record<string, string> = {
    draft: 'admin-badge-warning',
    active: 'admin-badge-success',
    completed: 'admin-badge-info',
    cancelled: 'admin-badge-danger',
  }
  return m[status ?? ''] ?? 'admin-badge-default'
}

const stopStatusBadge = (status: string | null | undefined): string => {
  const m: Record<string, string> = {
    pending: 'admin-badge-warning',
    visited: 'admin-badge-success',
    skipped: 'admin-badge-info',
    failed: 'admin-badge-danger',
  }
  return m[status ?? ''] ?? 'admin-badge-default'
}

const allowedTransitions = computed(() => {
  const transitions: Record<string, string[]> = {
    draft: ['active', 'cancelled'],
    active: ['completed', 'cancelled'],
    completed: [],
    cancelled: ['draft'],
  }
  return transitions[runData.value?.status_name ?? ''] ?? []
})

function formatSchedule(): string {
  const start = runData.value?.start_time
  const end = runData.value?.end_time
  if (!start && !end) return '—'
  if (start && end) return `${start}–${end}`
  return start || end || '—'
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-NI', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' })
}

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
  error.value = ''
  try {
    const res = await routeRunService.get(runId.value)
    runData.value = res.data.data.run
    stops.value = runData.value.stops ?? []
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar el despacho'
  } finally {
    loading.value = false
    await nextTick()
    initMap()
    startDriverPolling()
  }
}

function initMap() {
  if (!mapContainer.value) return

  if (map) {
    map.remove()
    map = null
  }
  if (cleanupMapSize) {
    cleanupMapSize()
    cleanupMapSize = null
  }
  liveMarker = null
  vehicleTrajectory = null
  routeCorridor = []
  mapBounds = []
  canRecenter.value = false
  offRoute.value = false

  map = L.map(mapContainer.value, {
    zoomControl: true,
    attributionControl: false,
    ...NICARAGUA_MAP_OPTIONS,
  }).setView([12.1149, -86.2362], 8)

  cleanupMapSize = stabilizeMapSize(map, mapContainer.value)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    bounds: NICARAGUA_BOUNDS,
  }).addTo(map!)

  const rd = runData.value
  if (!rd) return

  const bounds: L.LatLngBoundsLiteral = []

  // Trajectory (green line) + corridor de referencia para deteccion off-route
  if (rd.trajectory) {
    try {
      const coords = typeof rd.trajectory === 'string' ? JSON.parse(rd.trajectory) : rd.trajectory
      if (Array.isArray(coords) && coords.length >= 2) {
        const latlngs: [number, number][] = coords.map((c: any) => [c[1] ?? c.lat, c[0] ?? c.lng])
        L.polyline(latlngs, { color: rd.route_color || '#22c55e', weight: 4, opacity: 0.8 }).addTo(map!)
        routeCorridor = latlngs
        latlngs.forEach((ll) => bounds.push(ll))
      }
    } catch {}
  }

  // Zonas de la ruta maestra (cobertura)
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

  // OSRM optimal route (blue dashed line)
  const optimized = parseCoords(rd.optimized_geometry)
  if (optimized.length >= 2) {
    L.polyline(optimized, { color: '#00A8FF', weight: 3, dashArray: '8 8', opacity: 0.9 }).addTo(map!)
    if (routeCorridor.length === 0) routeCorridor = optimized
    optimized.forEach((ll) => bounds.push(ll))
  }

  // Stops markers
  stops.value.forEach((stop) => {
    if (stop.latitude && stop.longitude) {
      const lat = Number(stop.latitude)
      const lng = Number(stop.longitude)
      const markerColor = stop.status_name === 'visited' ? '#22c55e' : stop.status_name === 'pending' ? '#eab308' : '#64748b'
      const marker = L.circleMarker([lat, lng], {
        radius: 8,
        fillColor: markerColor,
        color: '#fff',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.9,
      }).addTo(map!)
      const carryTag = stop.carried_over ? '<br/><i style="color:#f97316">↻ Arrastrado del despacho anterior</i>' : ''
      const notesTag = stop.notes ? `<br/><i style="color:#94a3b8">${stop.notes}</i>` : ''
      const popupHtml = `<b>#${stop.stop_order}</b> ${stop.client_name}<br/>${stop.address || ''}${stop.from_zone_center ? '<br/><i style="color:#94a3b8">Punto por centro de zona (sin GPS)</i>' : ''}${carryTag}${notesTag}`
      marker.bindPopup(popupHtml)
      bounds.push([lat, lng])
    }
  })

  // Start/end markers (offset del centro de zona para no tapar stops fallback)
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

  mapBounds = bounds
  canRecenter.value = bounds.length > 0
  if (map && bounds.length > 0) {
    fitMapToBounds(map, bounds, 40)
  }
}

function recenterMap() {
  if (map && mapBounds.length > 0) {
    fitMapToBounds(map, mapBounds, 40)
  }
}

async function changeStatus(newStatus: string) {
  statusLoading.value = true
  try {
    const res = await routeRunService.updateStatus(runId.value, newStatus)
    runData.value = res.data.data.run
    toast.success(`Estado cambiado a "${newStatus}"`)
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al cambiar estado')
  } finally {
    statusLoading.value = false
  }
}

async function loadCandidatePackages() {
  loadingPackages.value = true
  selectedPackageIds.value = []
  try {
    const res = await routeRunService.getCandidatePackages(runId.value)
    candidatePackages.value = res.data.data.packages ?? []
    showAssignModal.value = true
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al cargar paquetes')
  } finally {
    loadingPackages.value = false
  }
}

async function openAssignDriverModal() {
  loadingAssignOptions.value = true
  try {
    const [driversRes, vehiclesRes] = await Promise.all([
      userService.listByRole('conductor'),
      vehicleService.listAll(),
    ])
    driverOptions.value = driversRes.data.data.users ?? []
    vehicleOptions.value = vehiclesRes.data.data.vehicles ?? []
    assignDriverId.value = runData.value?.assigned_to ?? null
    assignVehicleId.value = runData.value?.vehicle_id ?? null
    showAssignDriverModal.value = true
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al cargar conductores y vehículos')
  } finally {
    loadingAssignOptions.value = false
  }
}

async function saveAssignment() {
  savingAssignment.value = true
  try {
    const res = await routeRunService.update({
      id: runId.value,
      assigned_to: assignDriverId.value,
      vehicle_id: assignVehicleId.value,
    })
    runData.value = res.data.data.run
    showAssignDriverModal.value = false
    toast.success('Conductor y vehículo actualizados')
    stopDriverPolling()
    startDriverPolling()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al asignar conductor y vehículo')
  } finally {
    savingAssignment.value = false
  }
}

function togglePackage(id: number) {
  const idx = selectedPackageIds.value.indexOf(id)
  if (idx >= 0) {
    selectedPackageIds.value.splice(idx, 1)
  } else {
    selectedPackageIds.value.push(id)
  }
}

async function generateStops() {
  if (selectedPackageIds.value.length === 0) return
  saving.value = true
  try {
    const res = await routeRunService.generateStops(runId.value, selectedPackageIds.value)
    stops.value = res.data.data.stops ?? []
    if (runData.value) runData.value.optimized_geometry = null
    showAssignModal.value = false
    toast.success(res.data.message || 'Paradas generadas')
    await nextTick()
    initMap()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al generar paradas')
  } finally {
    saving.value = false
  }
}

async function optimizeStops() {
  optimizing.value = true
  try {
    const res = await routeRunService.optimize(runId.value)
    stops.value = res.data.data.stops ?? stops.value
    const optimized = (res.data.data as any).optimized_route as [number, number][] | null
    if (runData.value) {
      runData.value.optimized_geometry = optimized && optimized.length > 0 ? JSON.stringify(optimized) : null
    }
    toast.success(res.data.message || 'Despacho optimizado')
    await nextTick()
    initMap()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al optimizar')
  } finally {
    optimizing.value = false
  }
}

function openStopAction(stop: RouteStop, status: string) {
  stopActionTarget.value = stop
  stopActionStatus.value = status
  stopActionNotes.value = stop.notes ?? ''
  showStopActionModal.value = true
}

async function confirmStopAction() {
  if (!stopActionTarget.value) return
  stopActionSaving.value = true
  try {
    await routeStopService.updateStatus(stopActionTarget.value.id, stopActionStatus.value, stopActionNotes.value)
    showStopActionModal.value = false
    toast.success(`Parada marcada como "${stopActionStatus.value}"`)
    await refresh()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al actualizar la parada')
  } finally {
    stopActionSaving.value = false
  }
}

async function markVisited(stopId: number) {
  try {
    await routeStopService.updateStatus(stopId, 'visited')
    toast.success('Parada marcada como visitada')
    await refresh()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al actualizar')
  }
}

async function refresh() {
  const res = await routeRunService.get(runId.value)
  runData.value = res.data.data.run
  stops.value = runData.value.stops ?? []
  await nextTick()
  initMap()
}

onMounted(() => load())

onBeforeUnmount(() => {
  stopDriverPolling()
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
  <div class="space-y-6">
    <!-- Back button -->
    <button class="flex items-center gap-2 text-sm text-[#94a3b8] transition-colors hover:text-[#dae2fd]" @click="router.push('/runs')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Volver a despachos
    </button>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-[#69dca4] border-t-transparent" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-[#DC2626]">{{ error }}</p>
    </div>

    <!-- Content -->
    <template v-else-if="runData">
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-center gap-3">
          <span class="inline-block h-5 w-5 rounded-full shrink-0" :style="{ backgroundColor: runData.route_color || '#22c55e' }" />
          <div>
            <h1 class="text-xl font-bold text-[#dae2fd]">{{ runData.route_name }}</h1>
            <p class="mt-0.5 text-sm text-[#64748b]">
              Despacho del {{ formatDate(runData.run_date) }}
              <span v-if="runData.carried_over" class="text-[#f97316]">· ↻ {{ runData.carried_over }} arrastrados</span>
            </p>
          </div>
          <span :class="['admin-badge', statusBadge(runData.status_name)]">{{ runData.status_name }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="t in allowedTransitions"
            :key="t"
            class="admin-btn"
            :class="t === 'active' ? 'admin-btn-success' : t === 'completed' ? 'admin-btn-primary' : t === 'cancelled' ? 'admin-btn-danger' : 'admin-btn-ghost'"
            :disabled="statusLoading"
            @click="changeStatus(t)"
          >
            {{ statusLoading ? '...' : `→ ${t}` }}
          </button>
        </div>
      </div>

      <!-- Info cards -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="admin-card p-3">
          <div class="flex items-center justify-between gap-1">
            <p class="text-xs text-[#64748b]">Conductor</p>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" title="Asignar conductor y vehículo" @click="openAssignDriverModal">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
          </div>
          <p class="mt-1 text-sm font-medium text-[#dae2fd]">{{ runData.driver_name || 'Sin asignar' }}</p>
        </div>
        <div class="admin-card p-3">
          <p class="text-xs text-[#64748b]">Vehículo</p>
          <p class="mt-1 text-sm font-medium text-[#dae2fd]">{{ runData.vehicle_plate || 'Sin asignar' }}</p>
        </div>
        <div class="admin-card p-3">
          <p class="text-xs text-[#64748b]">Horario</p>
          <p class="mt-1 text-sm font-medium text-[#dae2fd]">{{ formatSchedule() }}</p>
        </div>
        <div class="admin-card p-3">
          <p class="text-xs text-[#64748b]">Paradas</p>
          <p class="mt-1 text-sm font-medium text-[#dae2fd]">{{ runData.stop_count ?? 0 }} ({{ runData.pending_stops ?? 0 }} pendientes)</p>
        </div>
      </div>

      <!-- Map -->
      <div>
        <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-[#94a3b8]">
          <span class="inline-flex items-center gap-1.5"><span class="h-1 w-5 rounded" style="background:#22c55e" /> Trayectoria</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-0 w-5 border-t-2 border-dashed" style="border-color:#00A8FF" /> Ruta óptima OSRM</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#22c55e;opacity:.25" /> Zonas</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#eab308" /> Pendiente</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#22c55e" /> Visitada</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#64748b" /> Omitida/Fallada</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-1 w-5 rounded" style="background:#f97316" /> Trayectoria vehículo</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#ef4444" /> Conductor / Fin</span>
          <span
            v-if="offRoute"
            class="inline-flex items-center gap-1.5 rounded-full border border-[#DC2626] bg-[#DC2626]/15 px-2 py-0.5 font-semibold text-[#f87171]"
          >
            <span class="relative flex h-2 w-2">
              <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#DC2626] opacity-75" />
              <span class="relative inline-flex h-2 w-2 rounded-full bg-[#DC2626]" />
            </span>
            FUERA DE RUTA
          </span>
        </div>
        <div ref="mapContainer" class="relative h-[400px] w-full rounded-xl border border-[#1e293b] overflow-hidden">
          <button
            v-if="canRecenter"
            class="absolute right-3 top-3 z-[1010] rounded-lg bg-[#0b1326]/90 px-3 py-1.5 text-xs font-medium text-[#dae2fd] shadow-md transition-colors hover:bg-[#1e293b]"
            @click.stop="recenterMap"
          >
            Re-centrar ruta
          </button>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap gap-3">
        <button class="admin-btn admin-btn-primary" :disabled="runData.status_name !== 'draft'" @click="loadCandidatePackages">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
          Asignar paquetes
        </button>
        <button
          class="admin-btn admin-btn-ghost"
          :disabled="!canOptimize || optimizing"
          :title="canOptimize ? 'Reordenar paradas pendientes con OSRM' : 'Se necesitan al menos 2 paradas pendientes con coordenadas para optimizar'"
          @click="optimizeStops"
        >
          {{ optimizing ? 'Optimizando...' : 'Optimizar ruta (OSRM)' }}
        </button>
        <p v-if="!canOptimize" class="basis-full text-xs text-[#64748b]">
          {{ stops.length > 0 ? 'Se necesitan al menos 2 paradas pendientes con coordenadas para optimizar.' : 'Sin paradas pendientes para optimizar.' }}
        </p>
      </div>

      <!-- Stops table -->
      <div class="admin-card">
        <div class="flex items-center justify-between border-b border-[#1e293b] px-5 py-3">
          <h3 class="text-sm font-semibold text-[#dae2fd]">Paradas ({{ stops.length }})</h3>
        </div>

        <div v-if="stops.length === 0" class="admin-empty-state py-8">
          <p class="admin-empty-state-title">Sin paradas</p>
          <p class="admin-empty-state-desc">Asigná paquetes para generar las paradas.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[#1e293b] text-left text-[#64748b]">
                <th class="px-5 py-3 font-medium">#</th>
                <th class="px-5 py-3 font-medium">Cliente</th>
                <th class="px-5 py-3 font-medium">Dirección</th>
                <th class="px-5 py-3 font-medium">Tracking</th>
                <th class="px-5 py-3 font-medium">Estado</th>
                <th class="px-5 py-3 font-medium">Observación</th>
                <th class="px-5 py-3 font-medium">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in stops" :key="s.id" class="border-b border-[#1e293b] transition-colors hover:bg-white/[0.02]">
                <td class="px-5 py-3 text-[#94a3b8]">
                  {{ s.stop_order }}
                  <span v-if="s.carried_over" class="ml-1 rounded-full bg-[#f97316]/15 px-1.5 py-0.5 text-[10px] font-semibold text-[#f97316]" title="Arrastrado del despacho anterior">↻</span>
                </td>
                <td class="px-5 py-3 font-medium text-[#dae2fd]">{{ s.client_name || '—' }}</td>
                <td class="px-5 py-3 text-[#94a3b8] max-w-[200px] truncate" :title="s.address || ''">{{ s.address || '—' }}</td>
                <td class="px-5 py-3 text-[#94a3b8]">{{ s.tracking_number || '—' }}</td>
                <td class="px-5 py-3">
                  <span :class="['admin-badge', stopStatusBadge(s.status_name)]">{{ s.status_name }}</span>
                </td>
                <td class="px-5 py-3 text-xs text-[#94a3b8] max-w-[220px] truncate" :title="s.notes || ''">{{ s.notes || '—' }}</td>
                <td class="px-5 py-3">
                  <div v-if="s.status_name === 'pending'" class="flex gap-1">
                    <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#22c55e]" :disabled="!isRunActive" :title="isRunActive ? 'Visitar' : 'El despacho debe estar activo'" @click="markVisited(s.id)">✔</button>
                    <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#94a3b8]" :disabled="!isRunActive" :title="isRunActive ? 'Saltar' : 'El despacho debe estar activo'" @click="openStopAction(s, 'skipped')">→</button>
                    <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" :disabled="!isRunActive" :title="isRunActive ? 'Fallar' : 'El despacho debe estar activo'" @click="openStopAction(s, 'failed')">✕</button>
                  </div>
                  <span v-else class="text-xs text-[#64748b]">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Assign packages modal -->
    <AppModal :visible="showAssignModal" title="Asignar paquetes al despacho" size="lg" @close="showAssignModal = false">
      <div v-if="loadingPackages" class="flex items-center justify-center py-10">
        <div class="h-6 w-6 animate-spin rounded-full border-2 border-[#69dca4] border-t-transparent" />
      </div>
      <template v-else-if="candidatePackages.length === 0">
        <div class="admin-empty-state py-8">
          <p class="admin-empty-state-title">Sin paquetes disponibles</p>
          <p class="admin-empty-state-desc">No hay paquetes pendientes en las zonas de esta ruta.</p>
        </div>
      </template>
      <div v-else class="max-h-[400px] overflow-y-auto space-y-2">
        <div
          v-for="pkg in candidatePackages"
          :key="pkg.id"
          :class="[
            'flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors',
            selectedPackageIds.includes(pkg.id)
              ? 'border-[#69dca4] bg-[#69dca4]/10'
              : 'border-[#1e293b] hover:border-[#334155]'
          ]"
          @click="togglePackage(pkg.id)"
        >
          <div :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded border text-[10px] font-bold',
            selectedPackageIds.includes(pkg.id) ? 'border-[#69dca4] bg-[#69dca4] text-[#0b1326]' : 'border-[#334155] text-[#64748b]']">
            {{ selectedPackageIds.includes(pkg.id) ? '✓' : '' }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-[#dae2fd] truncate">{{ pkg.tracking_number }}</p>
            <p class="text-xs text-[#94a3b8] truncate">{{ pkg.recipient_name }} — {{ pkg.recipient_address }}</p>
          </div>
          <span class="shrink-0 text-xs text-[#64748b]">{{ pkg.zone_name }}</span>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-between gap-3">
          <span class="text-sm text-[#64748b]">{{ selectedPackageIds.length }} seleccionados</span>
          <div class="flex gap-3">
            <button class="admin-btn admin-btn-ghost" @click="showAssignModal = false">Cancelar</button>
            <button class="admin-btn admin-btn-primary" :disabled="selectedPackageIds.length === 0 || saving" @click="generateStops">
              {{ saving ? 'Generando...' : `Generar ${selectedPackageIds.length} paradas` }}
            </button>
          </div>
        </div>
      </template>
    </AppModal>

    <!-- Stop action modal (skip/fail con observacion) -->
    <AppModal :visible="showStopActionModal" :title="`Marcar parada como '${stopActionStatus}'`" @close="showStopActionModal = false">
      <div class="space-y-4">
        <div class="rounded-lg border border-[#1e293b] bg-white/[0.02] p-3">
          <p class="text-sm font-medium text-[#dae2fd]">{{ stopActionTarget?.client_name || 'Cliente' }}</p>
          <p class="mt-0.5 text-xs text-[#94a3b8]">{{ stopActionTarget?.tracking_number }} · {{ stopActionTarget?.address }}</p>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Observación (se arrastrará al próximo despacho)</label>
          <textarea
            v-model="stopActionNotes"
            class="admin-input min-h-[90px]"
            placeholder="Ej: Cliente no estaba, coordinar por teléfono..."
            :disabled="stopActionSaving"
            maxlength="1000"
          />
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showStopActionModal = false" :disabled="stopActionSaving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="confirmStopAction" :disabled="stopActionSaving">
            {{ stopActionSaving ? 'Guardando...' : 'Confirmar' }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Asignar conductor y vehiculo (por dia) -->
    <AppModal :visible="showAssignDriverModal" title="Asignar conductor y vehículo" @close="showAssignDriverModal = false">
      <div v-if="loadingAssignOptions" class="flex items-center justify-center py-10">
        <div class="h-6 w-6 animate-spin rounded-full border-2 border-[#69dca4] border-t-transparent" />
      </div>
      <div v-else class="space-y-4">
        <p class="text-xs text-[#64748b]">
          Los valores por defecto de la ruta se copian al crear el despacho; acá podés cambiarlos para este día.
        </p>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Conductor</label>
          <select v-model="assignDriverId" class="admin-input w-full" :disabled="savingAssignment">
            <option :value="null">Sin conductor</option>
            <option v-for="d in driverOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Vehículo</label>
          <select v-model="assignVehicleId" class="admin-input w-full" :disabled="savingAssignment">
            <option :value="null">Sin vehículo</option>
            <option v-for="v in vehicleOptions" :key="v.id" :value="v.id">{{ v.plate }}{{ v.brand ? ` · ${v.brand}` : '' }}</option>
          </select>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showAssignDriverModal = false" :disabled="savingAssignment">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="saveAssignment" :disabled="savingAssignment || loadingAssignOptions">
            {{ savingAssignment ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </template>
    </AppModal>
  </div>
</template>
