<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { routeService, routeRunService, type DeliveryRoute } from '../services/routeService'
import { zoneService, type Zone } from '../services/zoneService'
import { userService, type User } from '../services/userService'
import { vehicleService, type Vehicle } from '../services/vehicleService'
import { useCrudView } from '../composables/useCrudView'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import RouteTrajectoryEditor from '../components/ui/RouteTrajectoryEditor.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const router = useRouter()
const toast = useToast()

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

const zoneOptions = ref<Zone[]>([])
const loadingZones = ref(false)
const driverOptions = ref<User[]>([])
const vehicleOptions = ref<Vehicle[]>([])
const loadingAssign = ref(false)
const openingRun = ref<number | null>(null)

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
    name: '', description: '', color: '#16a34a', zone_ids: [], trajectory: [],
    default_driver_id: null, default_vehicle_id: null,
  }),
  populateForm: (r) => ({
    name: r.name, description: r.description ?? '',
    color: r.color, zone_ids: r.zones?.map((z: any) => z.id) ?? [],
    trajectory: parseTrajectory(r.trajectory),
    default_driver_id: r.default_driver_id ?? null,
    default_vehicle_id: r.default_vehicle_id ?? null,
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
  await Promise.all([loadZones(), loadAssignOptions()])
})

async function loadZones() {
  loadingZones.value = true
  try {
    const res = await zoneService.listAll()
    zoneOptions.value = res.data.data.zones ?? []
  } catch { zoneOptions.value = [] }
  finally { loadingZones.value = false }
}

async function loadAssignOptions() {
  loadingAssign.value = true
  try {
    const [driversRes, vehiclesRes] = await Promise.all([
      userService.listByRole('conductor'),
      vehicleService.listAll(),
    ])
    driverOptions.value = driversRes.data.data.users ?? []
    vehicleOptions.value = vehiclesRes.data.data.vehicles ?? []
  } catch {
    driverOptions.value = []
    vehicleOptions.value = []
  } finally { loadingAssign.value = false }
}

function selectedZone(id: number): Zone | undefined {
  return zoneOptions.value.find((z) => z.id === id)
}

function deriveTrajectoryFromZones() {
  const traj = (form.value.zone_ids as number[])
    .map((id: number) => selectedZone(id))
    .filter((z): z is Zone => !!z && z.lat != null && z.lng != null)
    .map((z: Zone) => [Number(z.lng), Number(z.lat)] as [number, number])
  form.value.trajectory = traj
}

function toggleZone(id: number) {
  const ids = form.value.zone_ids as number[]
  if (ids.includes(id)) {
    form.value.zone_ids = ids.filter((i) => i !== id)
  } else {
    form.value.zone_ids = [...ids, id]
  }
  deriveTrajectoryFromZones()
}

function moveZone(id: number, dir: -1 | 1) {
  const ids = [...(form.value.zone_ids as number[])]
  const idx = ids.indexOf(id)
  const target = idx + dir
  if (idx < 0 || target < 0 || target >= ids.length) return
  ;[ids[idx], ids[target]] = [ids[target], ids[idx]]
  form.value.zone_ids = ids
  deriveTrajectoryFromZones()
}

function zoneOrderLabel(id: number): number {
  return (form.value.zone_ids as number[]).indexOf(id) + 1
}

const zoneMarkers = computed(() =>
  (form.value.zone_ids as number[])
    .map((id: number) => selectedZone(id))
    .filter((z): z is Zone => !!z && z.lat != null && z.lng != null)
    .map((z: Zone) => ({ id: z.id, name: z.name, lat: Number(z.lat), lng: Number(z.lng) }))
)

const statusBadge = (status: string | null | undefined): string => {
  const badges: Record<string, string> = {
    draft: 'admin-badge-warning',
    active: 'admin-badge-success',
    completed: 'admin-badge-info',
    cancelled: 'admin-badge-danger',
  }
  return badges[status ?? ''] ?? 'admin-badge-default'
}

async function openTodayRun(r: DeliveryRoute) {
  if (openingRun.value) return
  if (r.open_run_id) {
    router.push(`/runs/${r.open_run_id}`)
    return
  }
  openingRun.value = r.id
  try {
    const res = await routeRunService.create({ route_id: r.id })
    toast.success(`Despacho creado — ${res.data.data.carried_over} paquetes arrastrados`)
    router.push(`/runs/${res.data.data.run.id}`)
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'No se pudo crear el despacho')
  } finally {
    openingRun.value = null
  }
}

const columns: TableColumn[] = [
  { key: 'name', label: 'Ruta', width: '220px' },
  { key: 'zone_count', label: 'Zonas', width: '80px' },
  { key: 'runs_count', label: 'Despachos', width: '100px' },
  { key: 'open_run_status', label: 'Despacho de hoy', width: '140px' },
  { key: 'actions', label: '', width: '130px', align: 'right' },
]

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Rutas maestras" subtitle="Rutas geográficas fijas y sus despachos diarios">
      <template #actions>
        <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Crear ruta
        </button>
      </template>
    </PageHeader>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="routes.length === 0" empty-title="Sin rutas" empty-desc="Creá la primera ruta maestra.">
          <tr v-for="r in routes" :key="r.id">
            <td class="font-medium text-content">
              <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: r.color || '#16a34a' }" />
                {{ r.name }}
              </div>
            </td>
            <td class="text-muted">{{ r.zone_count ?? 0 }}</td>
            <td class="text-muted">{{ r.runs_count ?? 0 }}</td>
            <td>
              <span v-if="r.open_run_status" :class="['admin-badge', statusBadge(r.open_run_status)]">{{ r.open_run_status }}</span>
              <span v-else class="text-subtle">Sin despacho</span>
            </td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-primary admin-btn-sm" :disabled="openingRun !== null" @click="openTodayRun(r)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  {{ openingRun === r.id ? 'Creando...' : (r.open_run_id ? 'Abrir' : 'Despacho de hoy') }}
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(r)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(r)" title="Eliminar">
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
            <div class="h-4 w-1/3 animate-pulse rounded bg-surface-raised" />
            <div class="h-5 w-16 animate-pulse rounded-full bg-surface-raised" />
          </div>
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-surface-raised" />
          <div class="h-3 w-1/4 animate-pulse rounded bg-surface-raised" />
          <div class="border-t border-line pt-3">
            <div class="flex gap-2">
              <div class="h-7 w-16 animate-pulse rounded bg-surface-raised" />
              <div class="h-7 w-16 animate-pulse rounded bg-surface-raised" />
            </div>
          </div>
        </div>
      </template>

      <template v-else-if="routes.length > 0">
        <div v-for="r in routes" :key="r.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
              <span class="inline-block h-3 w-3 rounded-full shrink-0" :style="{ backgroundColor: r.color || '#16a34a' }" />
              <p class="font-medium text-content truncate">{{ r.name }}</p>
            </div>
            <span v-if="r.open_run_status" :class="['admin-badge shrink-0', statusBadge(r.open_run_status)]">{{ r.open_run_status }}</span>
          </div>
          <p class="text-sm text-muted">{{ r.zone_count ?? 0 }} zonas · {{ r.runs_count ?? 0 }} despachos</p>
          <p v-if="!r.open_run_status" class="text-xs text-subtle">Sin despacho para hoy</p>
          <div class="border-t border-line pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-primary admin-btn-sm" :disabled="openingRun !== null" @click="openTodayRun(r)">
              {{ openingRun === r.id ? 'Creando...' : (r.open_run_id ? 'Abrir despacho' : 'Despacho de hoy') }}
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(r)">Editar</button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(r)">Eliminar</button>
          </div>
        </div>
      </template>

      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
          </svg>
          <p class="admin-empty-state-title">Sin rutas</p>
          <p class="admin-empty-state-desc">Creá la primera ruta maestra.</p>
        </div>
      </div>

      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar ruta' : 'Crear ruta'" size="lg" :close-on-backdrop="false" @close="showModal = false">
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
              <label class="text-sm text-muted">Color:</label>
              <input v-model="form.color" type="color" class="h-9 w-16 rounded border border-line bg-transparent cursor-pointer" :disabled="saving" />
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
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0zM12 13a3 3 0 100-6 3 3 0 000 6z"/></svg>
            Zonas de la ruta
          </div>
          <p class="mt-1 text-xs text-subtle">
            Elegí las zonas en orden de visita: la primera es el inicio y la última el fin. La trayectoria se dibuja automáticamente.
          </p>
          <div v-if="loadingZones" class="mt-3 text-sm text-subtle">Cargando zonas...</div>
          <div v-else class="mt-3 flex flex-wrap gap-2">
            <button
              v-for="z in zoneOptions"
              :key="z.id"
              type="button"
              :class="[
                'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm transition-colors',
                (form.zone_ids as number[]).includes(z.id)
                  ? 'border-primary bg-primary/10 text-primary'
                  : 'border-line text-muted hover:border-line-strong'
              ]"
              :disabled="saving"
              @click="toggleZone(z.id)"
            >
              <span v-if="(form.zone_ids as number[]).includes(z.id)" class="flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-canvas">
                {{ zoneOrderLabel(z.id) }}
              </span>
              {{ z.name }}
            </button>
            <p v-if="zoneOptions.length === 0" class="text-sm text-subtle">No hay zonas disponibles.</p>
          </div>

          <!-- Orden de la ruta -->
          <div v-if="(form.zone_ids as number[]).length > 0" class="mt-4 rounded-lg border border-line divide-y divide-line">
            <div
              v-for="(zid, idx) in (form.zone_ids as number[])"
              :key="zid"
              class="flex items-center gap-3 px-3 py-2"
            >
              <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary">{{ idx + 1 }}</span>
              <span class="min-w-0 flex-1 truncate text-sm text-content">{{ selectedZone(zid)?.name || 'Zona' }}</span>
              <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" :disabled="saving || idx === 0" title="Mover arriba" @click="moveZone(zid, -1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
              </button>
              <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" :disabled="saving || idx === (form.zone_ids as number[]).length - 1" title="Mover abajo" @click="moveZone(zid, 1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
              </button>
              <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm text-danger" :disabled="saving" title="Quitar" @click="toggleZone(zid)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div class="admin-form-divider" />

        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Asignación por defecto
          </div>
          <p class="mt-1 text-xs text-subtle">
            Se copian al crear el despacho diario ("Despacho de hoy") y se pueden cambiar por día desde el detalle del despacho.
          </p>
          <div v-if="loadingAssign" class="mt-3 text-sm text-subtle">Cargando conductores y vehículos...</div>
          <div v-else class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-muted">Conductor por defecto</label>
              <select
                v-model="form.default_driver_id"
                class="admin-input w-full"
                :disabled="saving"
              >
                <option :value="null">Sin conductor</option>
                <option v-for="d in driverOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-muted">Vehículo por defecto</label>
              <select
                v-model="form.default_vehicle_id"
                class="admin-input w-full"
                :disabled="saving"
              >
                <option :value="null">Sin vehículo</option>
                <option v-for="v in vehicleOptions" :key="v.id" :value="v.id">{{ v.plate }}{{ v.brand ? ` · ${v.brand}` : '' }}</option>
              </select>
            </div>
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
              :color="(form.color as string) || '#16a34a'"
              :zone-markers="zoneMarkers"
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
      :message="`¿Seguro que querés eliminar la ruta ${deleting?.name}? Se eliminarán también todos sus despachos.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
