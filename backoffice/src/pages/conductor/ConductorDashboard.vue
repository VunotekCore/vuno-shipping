<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { driverLocationService, routeRunService, type RouteRun, type RouteStop } from '../../services/routeService'
import { useBackgroundGeo } from '../../composables/useBackgroundGeo'
import { useToast } from '../../composables/useToast'
import { statusLabel } from '../../utils/format'

const router = useRouter()
const toast = useToast()
const geo = useBackgroundGeo()

const loading = ref(true)
const starting = ref(false)
const routeData = ref<RouteRun | null>(null)
const stops = ref<RouteStop[]>([])
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await driverLocationService.driverRoutes()
    const runs: RouteRun[] = res.data.data.runs ?? []
    const active = runs.find(r => r.status_name === 'active') || runs[0] || null
    routeData.value = active

    if (active) {
      const stopRes = await routeRunService.get(active.id)
      stops.value = stopRes.data.data.run.stops ?? []
    }
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar datos'
  } finally {
    loading.value = false
  }
}

const isDraft = computed(() => routeData.value?.status_name === 'draft')
const canOperate = computed(() => routeData.value?.status_name === 'active')

const gpsWarning = computed(() => canOperate.value && !geo.tracking.value)

async function startJourney() {
  const run = routeData.value
  if (!run) return
  starting.value = true
  try {
    const res = await routeRunService.updateStatus(run.id, 'active')
    routeData.value = res.data.data.run
    toast.success('Jornada iniciada — el despacho ahora está en curso')
    geo.start()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al iniciar la jornada')
  } finally {
    starting.value = false
  }
}

const totalStops = computed(() => stops.value.length)
const deliveredStops = computed(() => stops.value.filter(s => s.status_name === 'delivered').length)
const progress = computed(() => totalStops.value > 0 ? Math.round((deliveredStops.value / totalStops.value) * 100) : 0)

const nextStop = computed(() => stops.value.find(s => s.status_name === 'pending'))

const pendingStops = computed(() => stops.value.filter(s => s.status_name === 'pending').length)
const canFinishJourney = computed(() => canOperate.value && pendingStops.value === 0)

const finishing = ref(false)

async function finishJourney() {
  const run = routeData.value
  if (!run || !canFinishJourney.value) return
  finishing.value = true
  try {
    const res = await routeRunService.updateStatus(run.id, 'completed')
    routeData.value = res.data.data.run
    geo.stop()
    toast.success('Jornada terminada — el despacho fue marcado como completado')
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al terminar la jornada')
  } finally {
    finishing.value = false
  }
}

function goToStop(stopId: number) {
  if (!canOperate.value) return
  router.push(`/conductor/stop/${stopId}`)
}

function goToRoute() {
  router.push('/conductor/ruta')
}

function formatSchedule(): string {
  const start = routeData.value?.start_time
  const end = routeData.value?.end_time
  if (!start && !end) return 'Sin horario'
  if (start && end) return `${start}–${end}`
  return start || end || 'Sin horario'
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div>
      <h1 class="text-lg font-bold">Mis entregas</h1>
      <p class="text-sm text-subtle">Ruta de despacho del día</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-sm text-danger">{{ error }}</p>
    </div>

    <!-- No route -->
    <div v-else-if="!routeData" class="admin-card p-8 text-center">
      <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-surface-raised">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-subtle">
          <path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
        </svg>
      </div>
      <p class="font-medium">Sin ruta asignada</p>
      <p class="mt-1 text-sm text-subtle">No tenés ninguna ruta asignada hoy.</p>
    </div>

    <!-- Active route -->
    <template v-else>
      <!-- Route info card -->
      <div class="admin-card overflow-hidden">
        <div class="relative p-5">
          <div class="absolute inset-0 opacity-5" :style="{ backgroundColor: routeData.route_color || '#16a34a' }" />
          <div class="relative">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: routeData.route_color || '#16a34a' }" />
                <h2 class="font-semibold">{{ routeData.route_name }}</h2>
              </div>
              <span
                class="rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                :class="isDraft ? 'bg-warning/15 text-warning' : routeData.status_name === 'completed' ? 'bg-secondary/15 text-secondary' : 'bg-primary/15 text-primary'"
              >{{ statusLabel(routeData.status_name) }}</span>
            </div>
            <p class="mt-1.5 text-sm text-subtle">{{ routeData.route_description || 'Sin descripción' }}</p>
            <p class="mt-1 text-xs text-subtle">{{ formatSchedule() }} · {{ routeData.driver_name }}</p>
          </div>
        </div>

        <!-- Start journey (draft) -->
        <div v-if="isDraft" class="border-t border-warning/20 bg-warning/5 px-5 py-4">
          <p class="text-sm text-content">Tu jornada está en planificación</p>
          <p class="mt-0.5 text-xs text-muted">Las entregas se habilitan cuando inicies la jornada.</p>
          <button
            class="mt-3 w-full rounded-lg bg-primary py-2.5 text-center text-sm font-semibold text-surface transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="starting"
            @click="startJourney"
          >
            {{ starting ? 'Iniciando...' : 'Iniciar jornada' }}
          </button>
        </div>

        <!-- Progress bar -->
        <div class="border-t border-line px-5 py-4">
          <div class="mb-2 flex items-center justify-between text-sm">
            <span class="text-muted">Progreso</span>
            <span class="font-medium text-content">{{ deliveredStops }} / {{ totalStops }} entregados</span>
          </div>
          <div class="h-2 overflow-hidden rounded-full bg-surface-raised">
            <div class="h-full rounded-full bg-primary transition-all duration-500" :style="{ width: progress + '%' }" />
          </div>
          <p class="mt-1.5 text-right text-xs text-subtle">{{ progress }}% completado</p>
        </div>

        <!-- Actions -->
        <div class="border-t border-line p-4 flex gap-3">
          <button class="flex-1 rounded-lg bg-primary py-2.5 text-center text-sm font-semibold text-surface transition-opacity hover:opacity-90" @click="goToRoute">
            Ver ruta en mapa
          </button>
          <button
            v-if="canOperate"
            class="flex-1 rounded-lg py-2.5 text-center text-sm font-semibold transition-colors"
            :class="canFinishJourney
              ? 'bg-secondary text-surface hover:opacity-90'
              : 'cursor-not-allowed bg-surface-raised text-subtle'"
            :disabled="!canFinishJourney || finishing"
            :title="canFinishJourney ? 'Cerrar el despacho al finalizar todas las paradas' : `Quedan ${pendingStops} paradas pendientes`"
            @click="finishJourney"
          >
            {{ finishing ? 'Terminando...' : 'Terminar jornada' }}
          </button>
        </div>
      </div>

      <!-- GPS warning -->
      <div
        v-if="gpsWarning || geo.geoError.value"
        class="flex items-start gap-3 rounded-2xl border p-4"
        :class="geo.geoError.value ? 'border-danger/40 bg-danger/10' : 'border-warning/30 bg-warning/10'"
        role="alert"
      >
        <svg class="mt-0.5 shrink-0 text-warning" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 8h.01M12 12v4" />
        </svg>
        <div class="flex-1">
          <p class="text-sm font-semibold" :class="geo.geoError.value ? 'text-danger' : 'text-warning'">
            {{ geo.geoError.value ? 'No se pudo activar el GPS' : 'GPS apagado' }}
          </p>
          <p class="text-xs" :class="geo.geoError.value ? 'text-danger' : 'text-warning'">
            {{ geo.geoError.value || 'Activá tu ubicación para registrar el recorrido en tiempo real. El navegador te pedirá permiso de geolocalización.' }}
          </p>
          <button
            v-if="geo.geoError.value"
            class="mt-2 text-xs font-medium text-danger underline underline-offset-2"
            @click="geo.geoError.value = ''"
          >
            Entendido
          </button>
          <button
            v-else
            class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-warning px-3 py-1.5 text-xs font-semibold text-surface transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="!geo.supported"
            @click="geo.start()"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
            Activar GPS
          </button>
        </div>
      </div>

      <!-- Next stop -->
      <div v-if="nextStop" class="admin-card p-5">
        <div class="mb-3 flex items-center gap-2">
          <span class="flex h-6 w-6 items-center justify-center rounded-full bg-warning/15 text-xs font-bold text-warning">{{ nextStop.stop_order }}</span>
          <div>
            <p class="text-sm font-medium">Siguiente parada</p>
            <p class="text-xs text-subtle">#{{ nextStop.stop_order }} · Pendiente</p>
          </div>
        </div>
        <p class="font-medium">{{ nextStop.client_name || 'Sin nombre' }}</p>
        <p class="mt-0.5 text-sm text-subtle">{{ nextStop.address || 'Sin dirección' }}</p>
        <div v-if="nextStop.tracking_number" class="mt-2">
          <span class="rounded-md bg-surface-raised px-2 py-0.5 text-xs text-muted">{{ nextStop.tracking_number }}</span>
        </div>
        <button
          class="mt-4 w-full rounded-lg py-2.5 text-sm font-medium transition-colors"
          :class="canOperate ? 'bg-surface-raised text-content hover:bg-line-strong' : 'cursor-not-allowed bg-surface-raised/50 text-subtle'"
          :disabled="!canOperate"
          :title="canOperate ? '' : 'El despacho debe estar en curso para entregar'"
          @click="goToStop(nextStop.id)"
        >
          {{ canOperate ? 'Ir a entregar' : (isDraft ? 'Inicia tu jornada para entregar' : 'Despacho finalizado') }}
        </button>
      </div>

      <!-- Stops list -->
      <div class="admin-card">
        <div class="border-b border-line px-5 py-3">
          <h3 class="text-sm font-semibold">Paradas ({{ totalStops }})</h3>
        </div>
        <div class="divide-y divide-line">
          <div
            v-for="s in stops"
            :key="s.id"
            class="flex cursor-pointer items-center gap-3 px-5 py-3 transition-colors hover:bg-surface-raised"
            @click="goToStop(s.id)"
          >
            <span
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
              :class="s.status_name === 'delivered' ? 'bg-success/15 text-success' : s.status_name === 'pending' ? 'bg-warning/15 text-warning' : 'bg-surface-raised text-subtle'"
            >
              {{ s.status_name === 'delivered' ? '✓' : s.stop_order }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium" :class="s.status_name === 'delivered' ? 'text-subtle line-through' : 'text-content'">
                {{ s.client_name || 'Sin nombre' }}
              </p>
              <p class="truncate text-xs text-subtle">{{ s.address || 'Sin dirección' }}</p>
            </div>
            <svg v-if="s.status_name === 'pending'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 text-subtle">
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
