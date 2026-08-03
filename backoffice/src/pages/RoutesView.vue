<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { routeService, type DeliveryRoute } from '../services/routeService'
import { userService, type User } from '../services/userService'
import { vehicleService, type Vehicle } from '../services/vehicleService'
import { zoneService, type Zone } from '../services/zoneService'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import RouteTrajectoryEditor from '../components/ui/RouteTrajectoryEditor.vue'

const router = useRouter()

function parseTrajectory(raw: string | null | undefined): Array<[number, number]> {
  if (!raw) return []
  try {
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []
    return parsed.map((c: any): [number, number] => [Number(c[0] ?? c.lng), Number(c[1] ?? c.lat)])
  } catch {
    return []
  }
}

function buildRoutePayload(f: Record<string, unknown>): Record<string, unknown> {
  const payload = { ...f }
  const traj = (f.trajectory as Array<[number, number]>) ?? []
  if (traj.length >= 2) {
    const first = traj[0]
    const last = traj[traj.length - 1]
    payload.trajectory = traj
    payload.start_latitude = first[1]
    payload.start_longitude = first[0]
    payload.end_latitude = last[1]
    payload.end_longitude = last[0]
  } else {
    payload.trajectory = null
    payload.start_latitude = null
    payload.start_longitude = null
    payload.end_latitude = null
    payload.end_longitude = null
  }
  return payload
}

const drivers = ref<User[]>([])
const vehicles = ref<Vehicle[]>([])
const zoneOptions = ref<Zone[]>([])
const loadingDrivers = ref(false)
const loadingVehicles = ref(false)
const loadingZones = ref(false)

const {
  items: routes, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<DeliveryRoute>({
  service: routeService as any,
  dataKey: 'routes',
  entityName: 'Ruta',
  getDefaultForm: () => ({
    name: '', description: '', assigned_to: null, vehicle_id: null,
    scheduled_date: '', start_time: '', end_time: '',
    color: '#22c55e', zone_ids: [], trajectory: [],
  }),
  populateForm: (r) => ({
    name: r.name, description: r.description ?? '', assigned_to: r.assigned_to ?? null,
    vehicle_id: r.vehicle_id ?? null,
    scheduled_date: r.scheduled_date ?? '', start_time: r.start_time ?? '', end_time: r.end_time ?? '',
    color: r.color, zone_ids: r.zones?.map((z: any) => z.id) ?? [],
    trajectory: parseTrajectory(r.trajectory),
  }),
  validateForm: (f) => {
    if (!f.name) return 'El nombre es requerido'
    return null
  },
  prepareCreateData: (f) => buildRoutePayload(f),
  prepareUpdateData: (f, r) => ({ id: r.id, ...buildRoutePayload(f) }),
  getEntityId: (r) => r.id,
})

watch(showModal, async (val) => {
  if (!val) return
  await Promise.all([loadDrivers(), loadVehicles(), loadZones()])
})

async function loadDrivers() {
  loadingDrivers.value = true
  try {
    const res = await userService.listByRole('conductor')
    drivers.value = res.data.data.users ?? []
  } catch { drivers.value = [] }
  finally { loadingDrivers.value = false }
}

async function loadVehicles() {
  loadingVehicles.value = true
  try {
    const res = await vehicleService.listAll()
    vehicles.value = res.data.data.vehicles ?? []
  } catch { vehicles.value = [] }
  finally { loadingVehicles.value = false }
}

async function loadZones() {
  loadingZones.value = true
  try {
    const res = await zoneService.listAll()
    zoneOptions.value = res.data.data.zones ?? []
  } catch { zoneOptions.value = [] }
  finally { loadingZones.value = false }
}

const statusBadge = (status: string | null | undefined): string => {
  const badges: Record<string, string> = {
    draft: 'admin-badge-warning',
    active: 'admin-badge-success',
    completed: 'admin-badge-info',
    cancelled: 'admin-badge-danger',
  }
  return badges[status ?? ''] ?? 'admin-badge-default'
}

function formatDate(d: string | null | undefined): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-NI', { day: '2-digit', month: 'short', year: 'numeric' })
}

function goToDetail(id: number) {
  router.push(`/routes/${id}`)
}

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '200px' },
  { key: 'status_name', label: 'Estado', width: '110px' },
  { key: 'driver_name', label: 'Conductor', width: '160px' },
  { key: 'scheduled_date', label: 'Fecha', width: '130px' },
  { key: 'stop_count', label: 'Paradas', width: '90px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

onMounted(() => load())

function toggleZone(zoneId: number) {
  const ids = form.value.zone_ids as number[]
  if (ids.includes(zoneId)) {
    form.value.zone_ids = ids.filter((id: number) => id !== zoneId)
  } else {
    form.value.zone_ids = [...ids, zoneId]
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Rutas de despacho</h1>
        <p class="mt-1 text-sm text-[#64748b]">Gestionar rutas de entrega y asignación de paquetes</p>
      </div>
      <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear ruta
      </button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="routes.length === 0" empty-title="Sin rutas" empty-desc="Creá la primera ruta de despacho.">
          <tr v-for="r in routes" :key="r.id" class="cursor-pointer transition-colors hover:bg-white/[0.02]" @click="goToDetail(r.id)">
            <td class="font-medium text-[#dae2fd]">
              <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: r.color || '#22c55e' }" />
                {{ r.name }}
              </div>
            </td>
            <td>
              <span :class="['admin-badge', statusBadge(r.status_name)]">{{ r.status_name }}</span>
            </td>
            <td class="text-[#94a3b8]">{{ r.driver_name || '—' }}</td>
            <td class="text-[#94a3b8]">{{ formatDate(r.scheduled_date) }}</td>
            <td class="text-[#94a3b8]">{{ r.stop_count ?? 0 }}</td>
            <td class="text-right" @click.stop>
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="goToDetail(r.id)" title="Ver detalle">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(r)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(r)" title="Eliminar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
              </div>
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
            <div class="h-4 w-1/3 animate-pulse rounded bg-[#1e293b]" />
            <div class="h-5 w-16 animate-pulse rounded-full bg-[#1e293b]" />
          </div>
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-[#1e293b]" />
          <div class="h-3 w-1/4 animate-pulse rounded bg-[#1e293b]" />
          <div class="border-t border-[#1e293b] pt-3">
            <div class="flex gap-2">
              <div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" />
              <div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" />
            </div>
          </div>
        </div>
      </template>

      <template v-else-if="routes.length > 0">
        <div v-for="r in routes" :key="r.id" class="admin-card p-4 space-y-3 cursor-pointer transition-colors hover:bg-white/[0.02]" @click="goToDetail(r.id)">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
              <span class="inline-block h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: r.color || '#22c55e' }" />
              <p class="font-medium text-[#dae2fd] truncate">{{ r.name }}</p>
            </div>
            <span :class="['admin-badge shrink-0', statusBadge(r.status_name)]">{{ r.status_name }}</span>
          </div>
          <p class="text-sm text-[#94a3b8] truncate">{{ r.driver_name || 'Sin conductor' }}</p>
          <p class="text-xs text-[#64748b]">{{ formatDate(r.scheduled_date) }} · {{ r.stop_count ?? 0 }} paradas</p>
          <div class="border-t border-[#1e293b] pt-3 flex flex-wrap items-center gap-2" @click.stop>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="goToDetail(r.id)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              Ver
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(r)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(r)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              Eliminar
            </button>
          </div>
        </div>
      </template>

      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
          </svg>
          <p class="admin-empty-state-title">Sin rutas</p>
          <p class="admin-empty-state-desc">Creá la primera ruta de despacho.</p>
        </div>
      </div>

      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar ruta' : 'Crear ruta'" size="lg" @close="showModal = false">
      <form class="space-y-5" @submit.prevent="save" novalidate>
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            Información general
          </div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="(form.name as string)"
              @update:model-value="(v: string | number | null) => form.name = v"
              label="Nombre"
              icon="edit-3"
              placeholder="Ej: Ruta Managua Norte"
              :required="true"
              :disabled="saving"
              :maxlength="255"
            />
            <div class="flex items-center gap-3">
              <label class="text-sm text-[#94a3b8]">Color:</label>
              <input v-model="form.color" type="color" class="h-9 w-16 rounded border border-[#1e293b] bg-transparent cursor-pointer" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Conductor</label>
              <select v-model="form.assigned_to" class="admin-input" :disabled="saving || loadingDrivers">
                <option :value="null">Sin asignar</option>
                <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }} ({{ d.email }})</option>
              </select>
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Vehículo</label>
              <select v-model="form.vehicle_id" class="admin-input" :disabled="saving || loadingVehicles">
                <option :value="null">Sin asignar</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate }} — {{ v.brand }} {{ v.model }}</option>
              </select>
            </div>
          </div>
          <div class="mt-4">
            <AppFormField
              :model-value="(form.description as string)"
              @update:model-value="(v: string | number | null) => form.description = v"
              label="Descripción"
              type="textarea"
              icon="file-text"
              placeholder="Descripción de la ruta..."
              :disabled="saving"
              :maxlength="1000"
              :rows="2"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Programación
          </div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Fecha programada</label>
              <input v-model="form.scheduled_date" type="date" class="admin-input" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Hora inicio</label>
              <input v-model="form.start_time" type="time" class="admin-input" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Hora fin</label>
              <input v-model="form.end_time" type="time" class="admin-input" :disabled="saving" />
            </div>
          </div>
        </div>

        <div class="admin-form-divider" />

        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0zM12 13a3 3 0 100-6 3 3 0 000 6z"/></svg>
            Zonas que cubre
          </div>
          <div v-if="loadingZones" class="mt-3 text-sm text-[#64748b]">Cargando zonas...</div>
          <div v-else class="mt-3 flex flex-wrap gap-2">
            <button
              v-for="z in zoneOptions"
              :key="z.id"
              type="button"
              :class="[
                'rounded-lg border px-3 py-1.5 text-sm transition-colors',
                (form.zone_ids as number[]).includes(z.id)
                  ? 'border-[#69dca4] bg-[#69dca4]/10 text-[#69dca4]'
                  : 'border-[#1e293b] text-[#94a3b8] hover:border-[#334155]'
              ]"
              :disabled="saving"
              @click="toggleZone(z.id)"
            >
              {{ z.name }}
            </button>
            <p v-if="zoneOptions.length === 0" class="text-sm text-[#64748b]">No hay zonas disponibles.</p>
          </div>
        </div>

        <div class="admin-form-divider" />

        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Trayectoria en el mapa
          </div>
          <div class="mt-3">
            <RouteTrajectoryEditor
              :model-value="(form.trajectory as Array<[number, number]>)"
              :color="(form.color as string) || '#22c55e'"
              :disabled="saving"
              @update:model-value="(v: Array<[number, number]>) => form.trajectory = v"
            />
          </div>
        </div>
      </form>

      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving || !form.name" @click="save">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Delete Confirmation -->
    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar ruta"
      :message="`¿Seguro que querés eliminar la ruta ${deleting?.name}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
