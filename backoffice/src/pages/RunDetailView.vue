<script setup lang="ts">
import { ref, onMounted, computed, nextTick, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { routeRunService, routeStopService, driverLocationService, type RouteRun, type RouteStop, type DriverLocation } from '../services/routeService'
import { userService, type User } from '../services/userService'
import { vehicleService, type Vehicle } from '../services/vehicleService'
import { useToast } from '../composables/useToast'
import { useAuthStore } from '../stores/auth'
import VehicleKmModal from '../components/vehicle/VehicleKmModal.vue'
import AppModal from '../components/ui/AppModal.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { NICARAGUA_MAP_OPTIONS, NICARAGUA_BOUNDS, stabilizeMapSize, fitMapToBounds } from '../utils/map'
import { statusLabel } from '../utils/format'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const runId = computed(() => Number(route.params.id))
const loading = ref(false)
const runData = ref<RouteRun | null>(null)
const stops = ref<RouteStop[]>([])
const error = ref('')

// Km de jornada (inicio/fin) — solo supervisor/admin con permiso
const canRegisterKm = computed(() =>
  auth.hasPermission('vehicle', 'edit_km') || auth.hasPermission('vehicle', 'km_supervisor')
)
const showKmModal = ref(false)
const kmModalType = ref('inicio_ruta')
const kmModalTitle = ref('Registrar kilometraje')
const kmVehicle = ref<Vehicle | null>(null)
const pendingStatus = ref<string | null>(null)

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
      vehicleTrajectory = L.polyline(latlngs, { color: '#eb5a28', weight: 3, opacity: 0.85 }).addTo(map)
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
      fillColor: '#dc2626',
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
    delivered: 'admin-badge-success',
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
        L.polyline(latlngs, { color: rd.route_color || '#16a34a', weight: 4, opacity: 0.8 }).addTo(map!)
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
        color: rd.route_color || '#16a34a',
        fillColor: rd.route_color || '#16a34a',
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
    L.polyline(optimized, { color: '#1ea7dc', weight: 3, dashArray: '8 8', opacity: 0.9 }).addTo(map!)
    if (routeCorridor.length === 0) routeCorridor = optimized
    optimized.forEach((ll) => bounds.push(ll))
  }

  // Stops markers
  stops.value.forEach((stop) => {
    if (stop.latitude && stop.longitude) {
      const lat = Number(stop.latitude)
      const lng = Number(stop.longitude)
      const markerColor = stop.status_name === 'delivered' ? '#16a34a' : stop.status_name === 'pending' ? '#d97706' : '#94a3b8'
      const marker = L.circleMarker([lat, lng], {
        radius: 8,
        fillColor: markerColor,
        color: '#fff',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.9,
      }).addTo(map!)
      const carryTag = stop.carried_over ? '<br/><i style="color:#eb5a28">↻ Arrastrado del despacho anterior</i>' : ''
      const notesTag = stop.notes ? `<br/><i style="color:#94a3b8">${stop.notes}</i>` : ''
      const popupHtml = `<b>#${stop.stop_order}</b> ${stop.client_name}<br/>${stop.address || ''}${stop.from_zone_center ? '<br/><i style="color:#94a3b8">Punto por centro de zona (sin GPS)</i>' : ''}${carryTag}${notesTag}`
      marker.bindPopup(popupHtml)
      bounds.push([lat, lng])
    }
  })

  // Start/end markers (offset del centro de zona para no tapar stops fallback)
  if (rd.start_latitude && rd.start_longitude) {
    const ll: [number, number] = [Number(rd.start_latitude) + 0.004, Number(rd.start_longitude) - 0.004]
    L.circleMarker(ll, { radius: 6, fillColor: '#16a34a', color: '#fff', weight: 2, fillOpacity: 1 }).addTo(map!)
      .bindPopup('Inicio')
    bounds.push(ll)
  }
  if (rd.end_latitude && rd.end_longitude) {
    const ll: [number, number] = [Number(rd.end_latitude) - 0.004, Number(rd.end_longitude) + 0.004]
    L.circleMarker(ll, { radius: 6, fillColor: '#dc2626', color: '#fff', weight: 2, fillOpacity: 1 }).addTo(map!)
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
  const current = runData.value?.status_name
  const isStart = current === 'draft' && newStatus === 'active'
  const isComplete = current === 'active' && newStatus === 'completed'
  const vehicleId = runData.value?.vehicle_id

  if ((isStart || isComplete) && canRegisterKm.value && vehicleId) {
    const vehicle = await loadVehicleForRun(vehicleId)
    if (vehicle) {
      kmModalType.value = isStart ? 'inicio_ruta' : 'fin_ruta'
      kmModalTitle.value = isStart ? 'Kilometraje de inicio de jornada' : 'Kilometraje de fin de jornada'
      kmVehicle.value = vehicle
      pendingStatus.value = newStatus
      showKmModal.value = true
      return
    }
    toast.error('No se pudo cargar el vehículo para registrar kilometraje')
    return
  }

  await applyStatus(newStatus)
}

async function loadVehicleForRun(vehicleId: number): Promise<Vehicle | null> {
  try {
    const res = await vehicleService.listAll()
    const list = res.data.data.vehicles ?? []
    return list.find((v) => v.id === vehicleId) ?? null
  } catch {
    return null
  }
}

async function onKmSaved() {
  showKmModal.value = false
  toast.success('Kilometraje registrado')
  const next = pendingStatus.value
  pendingStatus.value = null
  if (next) await applyStatus(next)
}

function cancelKmModal() {
  showKmModal.value = false
  pendingStatus.value = null
}

async function applyStatus(newStatus: string) {
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

function openPicking() {
  window.open(routeRunService.getPickingUrl(runId.value), '_blank')
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

function openStopAction(stop: RouteStop, status: string) {  stopActionTarget.value = stop
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
    toast.success(`Parada marcada como "${statusLabel(stopActionStatus.value)}"`)
    await refresh()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al actualizar la parada')
  } finally {
    stopActionSaving.value = false
  }
}

async function markDelivered(stopId: number) {
  try {
    await routeStopService.deliver({ id: stopId })
    toast.success('Entrega registrada')
    await refresh()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al registrar entrega')
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
    <button class="flex items-center gap-2 text-sm text-muted transition-colors hover:text-content" @click="router.push('/runs')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Volver a despachos
    </button>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-danger">{{ error }}</p>
    </div>

    <!-- Content -->
    <template v-else-if="runData">
      <!-- Header -->
      <PageHeader :title="runData.route_name">
        <template #title>
          <span class="flex items-center gap-3">
            <span class="inline-block h-5 w-5 rounded-full shrink-0" :style="{ backgroundColor: runData.route_color || '#16a34a' }" />
            {{ runData.route_name }}
            <span :class="['admin-badge', statusBadge(runData.status_name)]">{{ statusLabel(runData.status_name) }}</span>
          </span>
        </template>
        <template #subtitle>
          Despacho del {{ formatDate(runData.run_date) }}
          <span v-if="runData.carried_over" class="text-accent">· ↻ {{ runData.carried_over }} arrastrados</span>
        </template>
        <template #actions>
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
        </template>
      </PageHeader>

      <!-- Info cards -->
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="admin-card p-3">
          <div class="flex items-center justify-between gap-1">
            <p class="text-xs text-subtle">Conductor</p>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" title="Asignar conductor y vehículo" @click="openAssignDriverModal">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
          </div>
          <p class="mt-1 text-sm font-medium text-content">{{ runData.driver_name || 'Sin asignar' }}</p>
        </div>
        <div class="admin-card p-3">
          <p class="text-xs text-subtle">Vehículo</p>
          <p class="mt-1 text-sm font-medium text-content">{{ runData.vehicle_plate || 'Sin asignar' }}</p>
        </div>
        <div class="admin-card p-3">
          <p class="text-xs text-subtle">Horario</p>
          <p class="mt-1 text-sm font-medium text-content">{{ formatSchedule() }}</p>
        </div>
        <div class="admin-card p-3">
          <p class="text-xs text-subtle">Paradas</p>
          <p class="mt-1 text-sm font-medium text-content">{{ runData.stop_count ?? 0 }} ({{ runData.pending_stops ?? 0 }} pendientes)</p>
        </div>
      </div>

      <!-- Map -->
      <div>
        <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted">
          <span class="inline-flex items-center gap-1.5"><span class="h-1 w-5 rounded" style="background:#16a34a" /> Trayectoria</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-0 w-5 border-t-2 border-dashed" style="border-color:#1ea7dc" /> Ruta óptima OSRM</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#16a34a;opacity:.25" /> Zonas</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#d97706" /> Pendiente</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#16a34a" /> Visitada</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#94a3b8" /> Omitida/Fallada</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-1 w-5 rounded" style="background:#eb5a28" /> Trayectoria vehículo</span>
          <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#dc2626" /> Conductor / Fin</span>
          <span
            v-if="offRoute"
            class="inline-flex items-center gap-1.5 rounded-full border border-danger bg-danger/15 px-2 py-0.5 font-semibold text-danger"
          >
            <span class="relative flex h-2 w-2">
              <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-danger opacity-75" />
              <span class="relative inline-flex h-2 w-2 rounded-full bg-danger" />
            </span>
            FUERA DE RUTA
          </span>
        </div>
        <div ref="mapContainer" class="relative h-[400px] w-full rounded-xl border border-line overflow-hidden">
          <button
            v-if="canRecenter"
            class="absolute right-3 top-3 z-[1010] rounded-lg bg-surface/90 px-3 py-1.5 text-xs font-medium text-content shadow-md transition-colors hover:bg-surface-raised"
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
        <button class="admin-btn admin-btn-ghost" @click="openPicking" :disabled="stops.length === 0" :title="stops.length === 0 ? 'Sin paradas para preparar' : 'Abrir hoja de picking para imprimir'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
          Hoja de picking
        </button>
        <p v-if="!canOptimize" class="basis-full text-xs text-subtle">
          {{ stops.length > 0 ? 'Se necesitan al menos 2 paradas pendientes con coordenadas para optimizar.' : 'Sin paradas pendientes para optimizar.' }}
        </p>
      </div>

      <!-- Stops table -->
      <div class="admin-card">
        <div class="flex items-center justify-between border-b border-line px-5 py-3">
          <h3 class="text-sm font-semibold text-content">Paradas ({{ stops.length }})</h3>
        </div>

        <div v-if="stops.length === 0" class="admin-empty-state py-8">
          <p class="admin-empty-state-title">Sin paradas</p>
          <p class="admin-empty-state-desc">Asigná paquetes para generar las paradas.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Dirección</th>
                <th>Tracking</th>
                <th>Ubicación</th>
                <th>Estado</th>
                <th>Observación</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in stops" :key="s.id">
                <td class="text-muted">
                  {{ s.stop_order }}
                  <span v-if="s.carried_over" class="ml-1 rounded-full bg-accent/15 px-1.5 py-0.5 text-[10px] font-semibold text-accent" title="Arrastrado del despacho anterior">↻</span>
                </td>
                <td class="font-medium text-content">{{ s.client_name || '—' }}</td>
                <td class="text-muted max-w-[200px] truncate" :title="s.address || ''">{{ s.address || '—' }}</td>
                <td class="text-muted">{{ s.tracking_number || '—' }}</td>
                <td>
                  <span v-if="s.storage_location_code" class="inline-flex items-center gap-1.5 font-mono text-xs text-muted">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: s.storage_category_color || '#0284c7' }" />
                    {{ s.storage_location_code }}
                  </span>
                  <span v-else class="text-xs text-subtle">—</span>
                </td>
                <td>
                  <span :class="['admin-badge', stopStatusBadge(s.status_name)]">{{ statusLabel(s.status_name) }}</span>
                </td>
                <td class="text-xs text-muted max-w-[220px] truncate" :title="s.notes || ''">{{ s.notes || '—' }}</td>
                <td>
                  <div v-if="s.status_name === 'pending'" class="flex gap-1">
                    <button class="admin-btn admin-btn-ghost admin-btn-sm text-base text-success" :disabled="!isRunActive" :title="isRunActive ? 'Entregar' : 'El despacho debe estar activo'" @click="markDelivered(s.id)">✔</button>
                    <button class="admin-btn admin-btn-ghost admin-btn-sm text-base text-muted" :disabled="!isRunActive" :title="isRunActive ? 'Saltar' : 'El despacho debe estar activo'" @click="openStopAction(s, 'skipped')">→</button>
                    <button class="admin-btn admin-btn-ghost admin-btn-sm text-base text-danger" :disabled="!isRunActive" :title="isRunActive ? 'Fallar' : 'El despacho debe estar activo'" @click="openStopAction(s, 'failed')">✕</button>
                  </div>
                  <span v-else class="text-xs text-subtle">—</span>
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
        <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
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
              ? 'border-primary bg-primary/10'
              : 'border-line hover:border-line-strong'
          ]"
          @click="togglePackage(pkg.id)"
        >
          <div :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded border text-[10px] font-bold',
            selectedPackageIds.includes(pkg.id) ? 'border-primary bg-primary text-surface' : 'border-line-strong text-subtle']">
            {{ selectedPackageIds.includes(pkg.id) ? '✓' : '' }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-content truncate">{{ pkg.tracking_number }}</p>
            <p class="text-xs text-muted truncate">{{ pkg.recipient_name }} — {{ pkg.recipient_address }}</p>
          </div>
          <span class="shrink-0 text-xs text-subtle">{{ pkg.zone_name }}</span>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-between gap-3">
          <span class="text-sm text-subtle">{{ selectedPackageIds.length }} seleccionados</span>
          <div class="flex gap-3">
            <button class="admin-btn admin-btn-ghost" @click="showAssignModal = false">Cancelar</button>
            <button class="admin-btn admin-btn-accent" :disabled="selectedPackageIds.length === 0 || saving" @click="generateStops">
              {{ saving ? 'Generando...' : `Generar ${selectedPackageIds.length} paradas` }}
            </button>
          </div>
        </div>
      </template>
    </AppModal>

    <!-- Stop action modal (skip/fail con observacion) -->
    <AppModal :visible="showStopActionModal" :title="`Marcar parada como '${stopActionStatus}'`" @close="showStopActionModal = false">
      <div class="space-y-4">
        <div class="rounded-lg border border-line bg-surface-raised p-3">
          <p class="text-sm font-medium text-content">{{ stopActionTarget?.client_name || 'Cliente' }}</p>
          <p class="mt-0.5 text-xs text-muted">{{ stopActionTarget?.tracking_number }} · {{ stopActionTarget?.address }}</p>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">Observación (se arrastrará al próximo despacho)</label>
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
        <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
      </div>
      <div v-else class="space-y-4">
        <p class="text-xs text-subtle">
          Los valores por defecto de la ruta se copian al crear el despacho; acá podés cambiarlos para este día.
        </p>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">Conductor</label>
          <select v-model="assignDriverId" class="admin-input w-full" :disabled="savingAssignment">
            <option :value="null">Sin conductor</option>
            <option v-for="d in driverOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">Vehículo</label>
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

    <!-- Kilometraje de jornada (inicio/fin) -->
    <VehicleKmModal
      :visible="showKmModal"
      :vehicle="kmVehicle"
      :default-type="kmModalType"
      :allowed-types="[kmModalType]"
      :lock-type="true"
      :route-run-id="runId"
      :title="kmModalTitle"
      @close="cancelKmModal"
      @saved="onKmSaved"
    />
  </div>
</template>
