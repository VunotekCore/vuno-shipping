<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { routeRunService, routeService, type RouteRun, type DeliveryRoute } from '../services/routeService'
import { statusLabel } from '../utils/format'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const router = useRouter()
const toast = useToast()

const runs = ref<RouteRun[]>([])
const routes = ref<DeliveryRoute[]>([])
const loading = ref(false)
const page = ref(1)
const limit = ref(20)
const total = ref(0)
const pages = ref(0)

const filters = ref<{ date: string; status: string; route_id: string }>({
  date: '',
  status: '',
  route_id: '',
})

const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'draft', label: 'Borrador' },
  { value: 'active', label: 'Activo' },
  { value: 'completed', label: 'Completado' },
  { value: 'cancelled', label: 'Cancelado' },
]

const statusBadge = (status: string | null | undefined): string => {
  const m: Record<string, string> = {
    draft: 'admin-badge-warning',
    active: 'admin-badge-success',
    completed: 'admin-badge-info',
    cancelled: 'admin-badge-danger',
  }
  return m[status ?? ''] ?? 'admin-badge-default'
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  return new Date(d + (d.length === 10 ? 'T00:00:00' : '')).toLocaleDateString('es-NI', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

async function load() {
  loading.value = true
  try {
    const res = await routeRunService.list({
      page: page.value,
      limit: limit.value,
      date: filters.value.date || undefined,
      status: filters.value.status || undefined,
      route_id: filters.value.route_id ? Number(filters.value.route_id) : undefined,
    })
    runs.value = res.data.data.runs ?? []
    total.value = res.data.data.pagination?.total ?? 0
    pages.value = res.data.data.pagination?.pages ?? 0
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al cargar los despachos')
  } finally {
    loading.value = false
  }
}

async function loadRoutes() {
  try {
    const res = await routeService.list({ limit: 500 })
    routes.value = res.data.data.routes ?? []
  } catch {
    routes.value = []
  }
}

function applyFilters() {
  page.value = 1
  load()
}

function resetFilters() {
  filters.value = { date: '', status: '', route_id: '' }
  page.value = 1
  load()
}

function goToPage(n: number) {
  page.value = n
  load()
}

function setLimit(newLimit: number) {
  limit.value = newLimit
  page.value = 1
  load()
}

function openRun(id: number) {
  router.push(`/runs/${id}`)
}

onMounted(() => {
  load()
  loadRoutes()
})

watch(() => filters.value.status, applyFilters)
watch(() => filters.value.route_id, applyFilters)

const columns: TableColumn[] = [
  { key: 'run_date', label: 'Fecha', width: '110px' },
  { key: 'route_name', label: 'Ruta' },
  { key: 'driver_name', label: 'Conductor', width: '150px' },
  { key: 'vehicle_plate', label: 'Vehículo', width: '120px' },
  { key: 'status_name', label: 'Estado', width: '120px' },
  { key: 'stop_count', label: 'Paradas', width: '90px' },
  { key: 'actions', label: '', width: '80px', align: 'right' },
]
</script>

<template>
  <div class="space-y-6">
    <!-- Page header -->
    <PageHeader title="Despachos" subtitle="Todos los despachos diarios del sistema">
      <template #actions>
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="mb-1.5 block text-xs font-medium text-subtle">Fecha</label>
            <input v-model="filters.date" type="date" class="admin-input h-10" @change="applyFilters" />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-medium text-subtle">Estado</label>
            <select v-model="filters.status" class="admin-input h-10">
              <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-medium text-subtle">Ruta</label>
            <select v-model="filters.route_id" class="admin-input h-10">
              <option value="">Todas las rutas</option>
              <option v-for="r in routes" :key="r.id" :value="String(r.id)">{{ r.name }}</option>
            </select>
          </div>
          <button v-if="filters.date || filters.status || filters.route_id" class="admin-btn admin-btn-ghost h-10" @click="resetFilters">
            Limpiar
          </button>
        </div>
      </template>
    </PageHeader>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="runs.length === 0" empty-title="Sin despachos" empty-desc="Creá el primer despacho desde una ruta maestra.">
          <tr v-for="run in runs" :key="run.id" class="cursor-pointer transition-colors hover:bg-surface-raised" @click="openRun(run.id)">
            <td class="text-content font-medium">{{ formatDate(run.run_date) }}</td>
            <td class="text-muted">
              <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: run.route_color || '#16a34a' }" />
                <span class="truncate">{{ run.route_name }}</span>
                <span v-if="run.carried_over" class="shrink-0 rounded-full bg-accent/15 px-1.5 py-0.5 text-[10px] font-semibold text-accent" title="Paquetes arrastrados">↻ {{ run.carried_over }}</span>
              </div>
            </td>
            <td class="text-muted">{{ run.driver_name || '—' }}</td>
            <td class="text-muted">{{ run.vehicle_plate || '—' }}</td>
            <td>
              <span :class="['admin-badge', statusBadge(run.status_name)]">{{ statusLabel(run.status_name) }}</span>
            </td>
            <td class="text-muted">{{ run.stop_count ?? 0 }} <span v-if="run.pending_stops" class="text-warning">({{ run.pending_stops }} pend.)</span></td>
            <td class="text-right">
              <button class="admin-btn admin-btn-ghost admin-btn-sm" @click.stop="openRun(run.id)">Abrir</button>
            </td>
          </tr>
        </AppTable>
        <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
      </div>
    </div>

    <!-- Mobile/Tablet: Card view (< lg) -->
    <div class="lg:hidden space-y-3">
      <template v-if="loading">
        <div v-for="i in 3" :key="i" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-4 w-1/3 animate-pulse rounded bg-surface-raised" />
            <div class="h-5 w-16 animate-pulse rounded-full bg-surface-raised" />
          </div>
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-surface-raised" />
          <div class="h-3.5 w-1/3 animate-pulse rounded bg-surface-raised" />
          <div class="border-t border-line pt-3">
            <div class="h-7 w-16 animate-pulse rounded bg-surface-raised" />
          </div>
        </div>
      </template>

      <template v-else-if="runs.length > 0">
        <div v-for="run in runs" :key="run.id" class="admin-card p-4 space-y-3 cursor-pointer" @click="openRun(run.id)">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
              <span class="inline-block h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: run.route_color || '#16a34a' }" />
              <p class="font-medium text-content truncate">{{ run.route_name }}</p>
            </div>
            <span :class="['admin-badge shrink-0', statusBadge(run.status_name)]">{{ statusLabel(run.status_name) }}</span>
          </div>
          <p class="text-sm text-muted">{{ formatDate(run.run_date) }} · {{ run.driver_name || 'Sin conductor' }}</p>
          <p class="text-xs text-subtle">
            {{ run.vehicle_plate || 'Sin vehículo' }} · {{ run.stop_count ?? 0 }} paradas
            <template v-if="run.pending_stops"> · {{ run.pending_stops }} pendientes</template>
            <template v-if="run.carried_over"> · ↻ {{ run.carried_over }} arrastrados</template>
          </p>
          <div class="border-t border-line pt-3 flex items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click.stop="openRun(run.id)">Abrir despacho</button>
          </div>
        </div>
      </template>

      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M8 17h.01M16 17h.01M3 11l1.5-5A2 2 0 016.4 4h11.2a2 2 0 011.9 1.4L21 11M3 11h18M3 11v6a1 1 0 001 1h1m16-7v6a1 1 0 01-1 1h-1M3 11v-1m18 0v1" />
          </svg>
          <p class="admin-empty-state-title">Sin despachos</p>
          <p class="admin-empty-state-desc">Creá el primer despacho desde una ruta maestra.</p>
        </div>
      </div>

      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>
  </div>
</template>
