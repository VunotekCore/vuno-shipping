<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { driverLocationService, routeService, type DeliveryRoute, type RouteStop } from '../../services/routeService'

const router = useRouter()

const loading = ref(true)
const routeData = ref<DeliveryRoute | null>(null)
const stops = ref<RouteStop[]>([])
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await driverLocationService.driverRoutes()
    const routes: DeliveryRoute[] = res.data.data.routes ?? []
    const active = routes.find(r => r.status_name === 'active') || routes[0] || null
    routeData.value = active

    if (active) {
      const stopRes = await routeService.get(active.id)
      stops.value = stopRes.data.data.route.stops ?? []
    }
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar datos'
  } finally {
    loading.value = false
  }
}

const totalStops = computed(() => stops.value.length)
const visitedStops = computed(() => stops.value.filter(s => s.status_name === 'visited').length)
const progress = computed(() => totalStops.value > 0 ? Math.round((visitedStops.value / totalStops.value) * 100) : 0)

const nextStop = computed(() => stops.value.find(s => s.status_name === 'pending'))

function goToStop(stopId: number) {
  router.push(`/conductor/stop/${stopId}`)
}

function goToRoute() {
  router.push('/conductor/ruta')
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-NI', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div>
      <h1 class="text-lg font-bold">Mis entregas</h1>
      <p class="text-sm text-[#64748b]">Ruta de despacho del día</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-[#69dca4] border-t-transparent" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-sm text-[#DC2626]">{{ error }}</p>
    </div>

    <!-- No route -->
    <div v-else-if="!routeData" class="admin-card p-8 text-center">
      <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#1e293b]">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-[#64748b]">
          <path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
        </svg>
      </div>
      <p class="font-medium">Sin ruta asignada</p>
      <p class="mt-1 text-sm text-[#64748b]">No tenés ninguna ruta asignada hoy.</p>
    </div>

    <!-- Active route -->
    <template v-else>
      <!-- Route info card -->
      <div class="admin-card overflow-hidden">
        <div class="relative p-5">
          <div class="absolute inset-0 opacity-5" :style="{ backgroundColor: routeData.color || '#22c55e' }" />
          <div class="relative">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: routeData.color || '#22c55e' }" />
                <h2 class="font-semibold">{{ routeData.name }}</h2>
              </div>
              <span class="rounded-full bg-[#69dca4]/15 px-2.5 py-0.5 text-[11px] font-medium text-[#69dca4]">{{ routeData.status_name }}</span>
            </div>
            <p class="mt-1.5 text-sm text-[#64748b]">{{ routeData.description || 'Sin descripción' }}</p>
            <p class="mt-1 text-xs text-[#64748b]">{{ formatDate(routeData.scheduled_date) }} · {{ routeData.driver_name }}</p>
          </div>
        </div>

        <!-- Progress bar -->
        <div class="border-t border-[#1e293b] px-5 py-4">
          <div class="mb-2 flex items-center justify-between text-sm">
            <span class="text-[#94a3b8]">Progreso</span>
            <span class="font-medium text-[#dae2fd]">{{ visitedStops }} / {{ totalStops }} entregados</span>
          </div>
          <div class="h-2 overflow-hidden rounded-full bg-[#1e293b]">
            <div class="h-full rounded-full bg-[#69dca4] transition-all duration-500" :style="{ width: progress + '%' }" />
          </div>
          <p class="mt-1.5 text-right text-xs text-[#64748b]">{{ progress }}% completado</p>
        </div>

        <!-- Actions -->
        <div class="border-t border-[#1e293b] p-4 flex gap-3">
          <button class="flex-1 rounded-lg bg-[#69dca4] py-2.5 text-center text-sm font-semibold text-[#0b1326] transition-opacity hover:opacity-90" @click="goToRoute">
            Ver ruta en mapa
          </button>
        </div>
      </div>

      <!-- Next stop -->
      <div v-if="nextStop" class="admin-card p-5">
        <div class="mb-3 flex items-center gap-2">
          <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#eab308]/15 text-xs font-bold text-[#eab308]">{{ nextStop.stop_order }}</span>
          <div>
            <p class="text-sm font-medium">Siguiente parada</p>
            <p class="text-xs text-[#64748b]">#{{ nextStop.stop_order }} · Pendiente</p>
          </div>
        </div>
        <p class="font-medium">{{ nextStop.client_name || 'Sin nombre' }}</p>
        <p class="mt-0.5 text-sm text-[#64748b]">{{ nextStop.address || 'Sin dirección' }}</p>
        <div v-if="nextStop.tracking_number" class="mt-2">
          <span class="rounded-md bg-[#1e293b] px-2 py-0.5 text-xs text-[#94a3b8]">{{ nextStop.tracking_number }}</span>
        </div>
        <button class="mt-4 w-full rounded-lg bg-[#1e293b] py-2.5 text-sm font-medium text-[#dae2fd] transition-colors hover:bg-[#334155]" @click="goToStop(nextStop.id)">
          Ir a entregar
        </button>
      </div>

      <!-- Stops list -->
      <div class="admin-card">
        <div class="border-b border-[#1e293b] px-5 py-3">
          <h3 class="text-sm font-semibold">Paradas ({{ totalStops }})</h3>
        </div>
        <div class="divide-y divide-[#1e293b]">
          <div
            v-for="s in stops"
            :key="s.id"
            class="flex cursor-pointer items-center gap-3 px-5 py-3 transition-colors hover:bg-white/[0.02]"
            @click="goToStop(s.id)"
          >
            <span
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
              :class="s.status_name === 'visited' ? 'bg-[#22c55e]/15 text-[#22c55e]' : s.status_name === 'pending' ? 'bg-[#eab308]/15 text-[#eab308]' : 'bg-[#1e293b] text-[#64748b]'"
            >
              {{ s.status_name === 'visited' ? '✓' : s.stop_order }}
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium" :class="s.status_name === 'visited' ? 'text-[#64748b] line-through' : 'text-[#dae2fd]'">
                {{ s.client_name || 'Sin nombre' }}
              </p>
              <p class="truncate text-xs text-[#64748b]">{{ s.address || 'Sin dirección' }}</p>
            </div>
            <svg v-if="s.status_name === 'pending'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0 text-[#64748b]">
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
