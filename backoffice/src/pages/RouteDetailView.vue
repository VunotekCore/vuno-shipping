<script setup lang="ts">
import { ref, onMounted, computed, nextTick, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { routeService, routeStopService, driverLocationService, type DeliveryRoute, type RouteStop, type DriverLocation } from '../services/routeService'
import { useToast } from '../composables/useToast'
import AppModal from '../components/ui/AppModal.vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const routeId = computed(() => Number(route.params.id))
const loading = ref(false)
const routeData = ref<DeliveryRoute | null>(null)
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

// Leaflet
let map: L.Map | null = null
const mapContainer = ref<HTMLElement | null>(null)
let liveMarker: L.CircleMarker | null = null
let driverTimer: ReturnType<typeof setInterval> | null = null

function startDriverPolling() {
  stopDriverPolling()
  const userId = routeData.value?.assigned_to
  if (!userId) return
  const tick = async () => {
    try {
      const res = await driverLocationService.get(userId)
      const loc = (res.data.data as { location?: DriverLocation }).location
      if (!loc || !loc.latitude || !loc.longitude) return
      upsertLiveMarker(Number(loc.latitude), Number(loc.longitude))
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
  return transitions[routeData.value?.status_name ?? ''] ?? []
})

function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-NI', { day: '2-digit', month: 'short', year: 'numeric' })
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
    const res = await routeService.get(routeId.value)
    routeData.value = res.data.data.route
    stops.value = routeData.value.stops ?? []
    await nextTick()
    initMap()
    startDriverPolling()
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar la ruta'
  } finally {
    loading.value = false
  }
}

function initMap() {
  if (!mapContainer.value) return

  if (map) {
    map.remove()
    map = null
  }
  liveMarker = null

  map = L.map(mapContainer.value, {
    zoomControl: true,
    attributionControl: false,
  }).setView([12.1149, -86.2362], 8)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
  }).addTo(map!)

  const rd = routeData.value
  if (!rd) return

  const bounds: L.LatLngBoundsLiteral = []

  // Trajectory (green line)
  if (rd.trajectory) {
    try {
      const coords = typeof rd.trajectory === 'string' ? JSON.parse(rd.trajectory) : rd.trajectory
      if (Array.isArray(coords) && coords.length >= 2) {
        const latlngs: [number, number][] = coords.map((c: any) => [c[1] ?? c.lat, c[0] ?? c.lng])
        L.polyline(latlngs, { color: rd.color || '#22c55e', weight: 4, opacity: 0.8 }).addTo(map!)
        latlngs.forEach((ll) => bounds.push(ll))
      }
    } catch {}
  }

  // OSRM optimal route (blue dashed line)
  const optimized = parseCoords(rd.optimized_geometry)
  if (optimized.length >= 2) {
    L.polyline(optimized, { color: '#00A8FF', weight: 3, dashArray: '8 8', opacity: 0.9 }).addTo(map!)
    optimized.forEach((ll) => bounds.push(ll))
  }

  // Stops markers (yellow)
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
      marker.bindPopup(`<b>#${stop.stop_order}</b> ${stop.client_name}<br/>${stop.address || ''}`)
      bounds.push([lat, lng])
    }
  })

  // Start/end markers
  if (rd.start_latitude && rd.start_longitude) {
    const ll: [number, number] = [Number(rd.start_latitude), Number(rd.start_longitude)]
    L.circleMarker(ll, { radius: 6, fillColor: '#22c55e', color: '#fff', weight: 2, fillOpacity: 1 }).addTo(map!)
      .bindPopup('Inicio')
    bounds.push(ll)
  }
  if (rd.end_latitude && rd.end_longitude) {
    const ll: [number, number] = [Number(rd.end_latitude), Number(rd.end_longitude)]
    L.circleMarker(ll, { radius: 6, fillColor: '#ef4444', color: '#fff', weight: 2, fillOpacity: 1 }).addTo(map!)
      .bindPopup('Fin')
    bounds.push(ll)
  }

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 })
  }
}

async function changeStatus(newStatus: string) {
  statusLoading.value = true
  try {
    const res = await routeService.updateStatus(routeId.value, newStatus)
    routeData.value = res.data.data.route
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
    const res = await routeService.getCandidatePackages(routeId.value)
    candidatePackages.value = res.data.data.packages ?? []
    showAssignModal.value = true
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al cargar paquetes')
  } finally {
    loadingPackages.value = false
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
    const res = await routeService.generateStops(routeId.value, selectedPackageIds.value)
    const newStops = res.data.data.stops ?? []
    stops.value = newStops
    if (routeData.value) routeData.value.optimized_geometry = null
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
    const res = await routeService.optimize(routeId.value)
    stops.value = res.data.data.stops ?? stops.value
    const optimized = (res.data.data as any).optimized_route as [number, number][] | null
    if (routeData.value) {
      routeData.value.optimized_geometry = optimized && optimized.length > 0 ? JSON.stringify(optimized) : null
    }
    toast.success(res.data.message || 'Ruta optimizada')
    await nextTick()
    initMap()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al optimizar')
  } finally {
    optimizing.value = false
  }
}

async function updateStopStatus(stopId: number, status: string) {
  try {
    await routeStopService.updateStatus(stopId, status)
    const res = await routeService.get(routeId.value)
    stops.value = res.data.data.route.stops ?? []
    await nextTick()
    initMap()
    toast.success(`Parada marcada como "${status}"`)
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al actualizar')
  }
}

onMounted(() => load())

onBeforeUnmount(() => {
  stopDriverPolling()
  if (map) {
    map.remove()
    map = null
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Back button -->
    <button class="flex items-center gap-2 text-sm text-[#94a3b8] transition-colors hover:text-[#dae2fd]" @click="router.push('/routes')">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Volver a rutas
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
    <template v-else-if="routeData">
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-center gap-3">
          <span class="inline-block h-5 w-5 rounded-full shrink-0" :style="{ backgroundColor: routeData.color || '#22c55e' }" />
          <div>
            <h1 class="text-xl font-bold text-[#dae2fd]">{{ routeData.name }}</h1>
            <p class="mt-0.5 text-sm text-[#64748b]">{{ routeData.description || 'Sin descripción' }}</p>
          </div>
          <span :class="['admin-badge', statusBadge(routeData.status_name)]">{{ routeData.status_name }}</span>
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
          <p class="text-xs text-[#64748b]">Conductor</p>
          <p class="mt-1 text-sm font-medium text-[#dae2fd]">{{ routeData.driver_name || 'Sin asignar' }}</p>
        </div>
        <div class="admin-card p-3">
          <p class="text-xs text-[#64748b]">Vehículo</p>
          <p class="mt-1 text-sm font-medium text-[#dae2fd]">{{ routeData.vehicle_plate || 'Sin asignar' }}</p>
        </div>
        <div class="admin-card p-3">
          <p class="text-xs text-[#64748b]">Fecha</p>
          <p class="mt-1 text-sm font-medium text-[#dae2fd]">{{ formatDate(routeData.scheduled_date) }}</p>
        </div>
        <div class="admin-card p-3">
          <p class="text-xs text-[#64748b]">Paradas</p>
          <p class="mt-1 text-sm font-medium text-[#dae2fd]">{{ routeData.stop_count ?? 0 }} ({{ routeData.pending_stops ?? 0 }} pendientes)</p>
        </div>
      </div>

      <!-- Map -->
      <div>
        <div class="mb-2 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-[#94a3b8]">
          <span class="inline-flex items-center gap-1.5"><span class="h-1 w-5 rounded" style="background:#22c55e" /> Trayectoria</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-0 w-5 border-t-2 border-dashed" style="border-color:#00A8FF" /> Ruta óptima OSRM</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#eab308" /> Pendiente</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#22c55e" /> Visitada</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#64748b" /> Omitida/Fallada</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#ef4444" /> Conductor / Fin</span>
        </div>
        <div ref="mapContainer" class="h-[400px] w-full rounded-xl border border-[#1e293b] overflow-hidden" />
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap gap-3">
        <button class="admin-btn admin-btn-primary" :disabled="routeData.status_name !== 'draft'" @click="loadCandidatePackages">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
          Asignar paquetes
        </button>
        <button class="admin-btn admin-btn-ghost" :disabled="stops.length < 2 || optimizing" @click="optimizeStops">
          {{ optimizing ? 'Optimizando...' : 'Optimizar ruta (OSRM)' }}
        </button>
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
                <th class="px-5 py-3 font-medium">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in stops" :key="s.id" class="border-b border-[#1e293b] transition-colors hover:bg-white/[0.02]">
                <td class="px-5 py-3 text-[#94a3b8]">{{ s.stop_order }}</td>
                <td class="px-5 py-3 font-medium text-[#dae2fd]">{{ s.client_name || '—' }}</td>
                <td class="px-5 py-3 text-[#94a3b8] max-w-[200px] truncate">{{ s.address || '—' }}</td>
                <td class="px-5 py-3 text-[#94a3b8]">{{ s.tracking_number || '—' }}</td>
                <td class="px-5 py-3">
                  <span :class="['admin-badge', stopStatusBadge(s.status_name)]">{{ s.status_name }}</span>
                </td>
                <td class="px-5 py-3">
                  <div v-if="s.status_name === 'pending'" class="flex gap-1">
                    <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#22c55e]" @click="updateStopStatus(s.id, 'visited')" title="Visitar">✔</button>
                    <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#94a3b8]" @click="updateStopStatus(s.id, 'skipped')" title="Saltar">→</button>
                    <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="updateStopStatus(s.id, 'failed')" title="Fallar">✕</button>
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
    <AppModal :visible="showAssignModal" title="Asignar paquetes a la ruta" size="lg" @close="showAssignModal = false">
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
  </div>
</template>
