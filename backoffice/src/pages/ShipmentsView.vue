<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { shipmentService, type Shipment } from '../services/shipmentService'
import type { ShipmentPackage } from '../types'
import { useCrudView } from '../composables/useCrudView'
import { useToast } from '../composables/useToast'
import { statusLabel } from '../utils/format'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'

const toast = useToast()

const modeOptions = [
  { value: 'air', label: 'Aéreo' },
  { value: 'sea', label: 'Marítimo' },
]

const statusTabs = [
  { value: '', label: 'Todos' },
  { value: 'draft', label: 'Borrador' },
  { value: 'in_transit', label: 'En tránsito' },
  { value: 'arrived', label: 'Llegado' },
  { value: 'cancelled', label: 'Cancelado' },
]

const activeStatus = ref('')

function filterTabs() {
  page.value = 1
  load()
}

const {
  items: shipments, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<Shipment>({
  service: {
    ...shipmentService,
    list: async (params?: { page?: number; limit?: number }) => {
      const { data } = await shipmentService.list({ page: params?.page, limit: params?.limit, status: activeStatus.value || undefined })
      return { data: { data: data.data as Record<string, unknown>, pagination: data.data.pagination } }
    },
  },
  dataKey: 'shipments',
  entityName: 'Embarque',
  getDefaultForm: () => ({ code: '', mode: 'air', departure_date: null, arrival_date: null, notes: '' }),
  populateForm: (s) => ({ code: s.code, mode: s.mode, departure_date: s.departure_date, arrival_date: s.arrival_date, notes: s.notes ?? '' }),
  validateForm: (f) => {
    if (f.mode !== 'air' && f.mode !== 'sea') return 'Seleccioná un modo de envío válido'
    return null
  },
  prepareCreateData: (f) => ({
    code: (f.code as string || '').trim(),
    mode: f.mode,
    departure_date: (f.departure_date as string || '').trim() || null,
    arrival_date: (f.arrival_date as string || '').trim() || null,
    notes: (f.notes as string || '').trim() || null,
  }),
  prepareUpdateData: (f, s) => ({
    id: s.id,
    code: (f.code as string || '').trim(),
    mode: f.mode,
    departure_date: (f.departure_date as string || '').trim() || null,
    arrival_date: (f.arrival_date as string || '').trim() || null,
    notes: (f.notes as string || '').trim() || null,
  }),
  getEntityId: (s) => s.id,
})

function shipmentStatusColor(status: string): string {
  const map: Record<string, string> = {
    draft: 'admin-badge-info',
    in_transit: 'admin-badge-warning',
    arrived: 'admin-badge-success',
    cancelled: 'admin-badge-danger',
  }
  return map[status] || 'admin-badge-info'
}

function modeLabel(mode: string): string {
  return mode === 'sea' ? 'Marítimo' : 'Aéreo'
}

function fmtDate(d: string | null): string {
  if (!d) return '—'
  return d.slice(0, 10)
}

// --- Detail modal ---

const showDetail = ref(false)
const detailShipment = ref<Shipment | null>(null)
const detailPackages = ref<ShipmentPackage[]>([])
const loadingDetail = ref(false)
const showAddPackages = ref(false)
const eligible = ref<ShipmentPackage[]>([])
const loadingEligible = ref(false)
const selectedIds = ref<number[]>([])
const eligibleQuery = ref('')
const addingPackages = ref(false)
const removingId = ref<number | null>(null)
const busyAction = ref(false)

function isChecked(id: number): boolean {
  return selectedIds.value.includes(id)
}

function toggleSelect(id: number) {
  const idx = selectedIds.value.indexOf(id)
  if (idx >= 0) selectedIds.value.splice(idx, 1)
  else selectedIds.value.push(id)
}

async function openDetail(s: Shipment) {
  detailShipment.value = s
  showDetail.value = true
  detailPackages.value = []
  loadingDetail.value = true
  try {
    const { data } = await shipmentService.get(s.id)
    detailShipment.value = data.data.shipment
    detailPackages.value = data.data.packages
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al cargar el detalle del embarque')
  } finally {
    loadingDetail.value = false
  }
}

async function openAddPackages() {
  showAddPackages.value = true
  selectedIds.value = []
  eligibleQuery.value = ''
  await loadEligible()
}

async function loadEligible() {
  loadingEligible.value = true
  eligible.value = []
  const mode = detailShipment.value?.mode
  try {
    const { data } = await shipmentService.getEligible(eligibleQuery.value.trim() || undefined, mode)
    eligible.value = data.data.packages
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al cargar paquetes elegibles')
  } finally {
    loadingEligible.value = false
  }
}

function searchEligible() {
  loadEligible()
}

async function confirmAddPackages() {
  if (!detailShipment.value || selectedIds.value.length === 0) return
  addingPackages.value = true
  try {
    const { data } = await shipmentService.addPackages(detailShipment.value.id, selectedIds.value)
    toast.success(data.message || 'Paquetes agregados al embarque')
    showAddPackages.value = false
    await refreshDetail()
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al agregar paquetes')
  } finally {
    addingPackages.value = false
  }
}

async function removePackage(pkgId: number) {
  if (!detailShipment.value) return
  removingId.value = pkgId
  try {
    await shipmentService.removePackage(detailShipment.value.id, pkgId)
    toast.success('Paquete removido del embarque')
    await refreshDetail()
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al remover el paquete')
  } finally {
    removingId.value = null
  }
}

async function dispatch() {
  if (!detailShipment.value) return
  busyAction.value = true
  try {
    const departure = detailShipment.value.departure_date || new Date().toISOString().slice(0, 10)
    const { data } = await shipmentService.dispatch(detailShipment.value.id, departure)
    toast.success(data.message || 'Embarque despachado')
    await refreshDetail()
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al despachar el embarque')
  } finally {
    busyAction.value = false
  }
}

async function arrive() {
  if (!detailShipment.value) return
  busyAction.value = true
  try {
    const arrival = detailShipment.value.arrival_date || new Date().toISOString().slice(0, 10)
    const { data } = await shipmentService.arrive(detailShipment.value.id, arrival)
    toast.success(data.message || 'Llegada a Nicaragua registrada')
    await refreshDetail()
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al marcar la llegada')
  } finally {
    busyAction.value = false
  }
}

async function refreshDetail() {
  if (!detailShipment.value) return
  const { data } = await shipmentService.get(detailShipment.value.id)
  detailShipment.value = data.data.shipment
  detailPackages.value = data.data.packages
}

const canAddRemove = computed(() => detailShipment.value?.status === 'draft')
const canDispatch = computed(() => detailShipment.value?.status === 'draft' && (detailShipment.value.package_count ?? 0) > 0)
const canArrive = computed(() => detailShipment.value?.status === 'in_transit')

function fv(key: string): string | number | null {
  const v = form.value[key]
  if (v == null) return null
  if (typeof v === 'string' || typeof v === 'number') return v
  return null
}

const columns: TableColumn[] = [
  { key: 'code', label: 'Embarque', width: '130px' },
  { key: 'mode', label: 'Vía', width: '100px' },
  { key: 'status', label: 'Estado', width: '120px' },
  { key: 'package_count', label: 'Paq.', width: '70px', align: 'center' },
  { key: 'departure_date', label: 'Salida', width: '110px' },
  { key: 'arrival_date', label: 'Llegada', width: '110px' },
  { key: 'created_by_name', label: 'Creado por', width: '140px' },
  { key: 'actions', label: '', width: '110px', align: 'right' },
]

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Embarques</h1>
        <p class="mt-1 text-sm text-[#64748b]">Consolidar paquetes de Miami y despacharlos por vía aérea o marítima a Nicaragua</p>
      </div>
      <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear embarque
      </button>
    </div>

    <!-- Status filters -->
    <div class="flex flex-wrap items-center gap-2">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        :class="[
          'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
          activeStatus === tab.value
            ? 'bg-[#69dca4]/15 text-[#69dca4]'
            : 'bg-[#111d2e] text-[#94a3b8] hover:text-[#dae2fd] border border-[#1e293b]'
        ]"
        @click="activeStatus = tab.value; filterTabs()"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="shipments.length === 0" empty-title="Sin embarques" empty-desc="Creá el primer embarque para consolidar paquetes de Miami.">
          <tr v-for="s in shipments" :key="s.id" class="cursor-pointer" @click="openDetail(s)">
            <td class="font-medium text-[#dae2fd]">{{ s.code }}</td>
            <td class="text-[#94a3b8]">{{ modeLabel(s.mode) }}</td>
            <td>
              <span :class="['admin-badge', shipmentStatusColor(s.status)]">{{ s.status }}</span>
            </td>
            <td class="text-center text-[#dae2fd]">{{ s.package_count }}</td>
            <td class="text-[#94a3b8]">{{ fmtDate(s.departure_date) }}</td>
            <td class="text-[#94a3b8]">{{ fmtDate(s.arrival_date) }}</td>
            <td class="text-[#64748b]">{{ s.created_by_name ?? '—' }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1" @click.stop>
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openDetail(s)" title="Ver detalle">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
                <button v-if="s.status === 'draft'" class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(s)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button v-if="s.status === 'draft' || s.status === 'cancelled'" class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(s)" title="Eliminar">
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

      <template v-else-if="shipments.length > 0">
        <div v-for="s in shipments" :key="s.id" class="admin-card p-4 space-y-3 cursor-pointer" @click="openDetail(s)">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-[#dae2fd] truncate">{{ s.code }}</p>
            <span :class="['admin-badge shrink-0', shipmentStatusColor(s.status)]">{{ s.status }}</span>
          </div>
          <p class="text-sm text-[#94a3b8] truncate">{{ modeLabel(s.mode) }} · {{ s.package_count }} paquetes</p>
          <p class="text-xs text-[#64748b]">Salida {{ fmtDate(s.departure_date) }} · Llegada {{ fmtDate(s.arrival_date) }}</p>
          <div class="border-t border-[#1e293b] pt-3 flex items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click.stop="openEdit(s)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button v-if="s.status === 'draft' || s.status === 'cancelled'" class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click.stop="openDelete(s)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              Eliminar
            </button>
          </div>
        </div>
      </template>

      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          <p class="admin-empty-state-title">Sin embarques</p>
          <p class="admin-empty-state-desc">Creá el primer embarque para consolidar paquetes de Miami.</p>
        </div>
      </div>

      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar embarque' : 'Crear embarque'" size="lg" @close="showModal = false">
      <form class="space-y-5" @submit.prevent="save">
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Identificación y vía
          </div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('code')"
              @update:model-value="(v: string | number | null) => form.code = v"
              label="Código de embarque"
              icon="hash"
              placeholder="ej: EMB-0001"
              helper="Si lo dejás vacío, se genera automáticamente"
              :disabled="saving"
              :maxlength="50"
            />
            <AppFormField
              :model-value="fv('mode')"
              @update:model-value="(v: string | number | null) => form.mode = v"
              label="Vía de envío"
              type="select"
              icon="globe"
              :options="modeOptions"
              :disabled="saving"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M3 7l3 6a4 4 0 006 0l3-6M15 7l3 6a4 4 0 006 0l3-6"/></svg>
            Fechas estimadas
          </div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('departure_date')"
              @update:model-value="(v: string | number | null) => form.departure_date = v"
              label="Fecha de salida"
              type="date"
              icon="calendar"
              :disabled="saving"
            />
            <AppFormField
              :model-value="fv('arrival_date')"
              @update:model-value="(v: string | number | null) => form.arrival_date = v"
              label="Fecha estimada de llegada"
              type="date"
              icon="calendar"
              :disabled="saving"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
            Notas
          </div>
          <div class="mt-3">
            <AppFormField
              :model-value="fv('notes')"
              @update:model-value="(v: string | number | null) => form.notes = v"
              label="Notas del embarque"
              type="textarea"
              icon="file-text"
              placeholder="Observaciones sobre el embarque..."
              :disabled="saving"
              :maxlength="500"
              :rows="3"
            />
          </div>
        </div>
      </form>

      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving" @click="save">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Detail modal -->
    <AppModal :visible="showDetail" :title="detailShipment ? `Embarque ${detailShipment.code}` : ''" size="lg" @close="showDetail = false">
      <div v-if="detailShipment" class="space-y-5">
        <!-- Header info -->
        <div class="flex flex-wrap items-center gap-2">
          <span :class="['admin-badge', shipmentStatusColor(detailShipment.status)]">{{ detailShipment.status }}</span>
          <span class="rounded-full bg-[#111d2e] border border-[#1e293b] px-2.5 py-0.5 text-xs font-medium text-[#94a3b8]">{{ modeLabel(detailShipment.mode) }}</span>
          <span class="rounded-full bg-[#111d2e] border border-[#1e293b] px-2.5 py-0.5 text-xs font-medium text-[#94a3b8]">{{ detailShipment.package_count }} paquetes</span>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div class="rounded-lg border border-[#1e293b] bg-[#0b1326] p-3">
            <p class="text-xs text-[#64748b]">Salida</p>
            <p class="text-sm font-medium text-[#dae2fd] mt-0.5">{{ fmtDate(detailShipment.departure_date) }}</p>
          </div>
          <div class="rounded-lg border border-[#1e293b] bg-[#0b1326] p-3">
            <p class="text-xs text-[#64748b]">Llegada</p>
            <p class="text-sm font-medium text-[#dae2fd] mt-0.5">{{ fmtDate(detailShipment.arrival_date) }}</p>
          </div>
        </div>

        <div v-if="detailShipment.notes" class="rounded-lg border border-[#1e293b] bg-[#0b1326] p-3">
          <p class="text-xs text-[#64748b]">Notas</p>
          <p class="text-sm text-[#dae2fd] mt-0.5">{{ detailShipment.notes }}</p>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2">
          <button v-if="canAddRemove" class="admin-btn admin-btn-secondary admin-btn-sm" @click="openAddPackages">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Agregar paquetes
          </button>
          <button v-if="canDispatch" class="admin-btn admin-btn-primary admin-btn-sm" :disabled="busyAction" @click="dispatch">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12h20M2 12l4-4m-4 4l4 4M22 12l-4-4m4 4l-4 4"/></svg>
            Despachar desde Miami
          </button>
          <button v-if="canArrive" class="admin-btn admin-btn-success admin-btn-sm" :disabled="busyAction" @click="arrive">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M0 3h24M0 3l4 4m-4-4l4-4M22 3v12a2 2 0 01-2 2H4a2 2 0 01-2-2V3"/></svg>
            Llegó a Nicaragua
          </button>
        </div>

        <!-- Packages list -->
        <div>
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[#dae2fd]">Paquetes del embarque</h3>
          </div>
          <div v-if="loadingDetail" class="mt-3 space-y-2">
            <div v-for="i in 2" :key="i" class="h-10 animate-pulse rounded-lg bg-[#1e293b]" />
          </div>
          <div v-else class="mt-3 space-y-2 max-h-64 overflow-y-auto pr-1">
            <div
              v-for="p in detailPackages"
              :key="p.id"
              class="flex items-center justify-between gap-2 rounded-lg border border-[#1e293b] bg-[#0b1326] px-3 py-2.5"
            >
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-[#dae2fd]">{{ p.tracking_number }}</p>
                <p class="truncate text-xs text-[#64748b]">
                  {{ p.recipient_name }} · {{ p.chargeable_weight ? p.chargeable_weight + ' kg' : '—' }} · {{ p.origin === 'local' ? 'Ingreso local' : 'Compra EEUU' }}
                </p>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <span class="text-xs text-[#94a3b8]">{{ statusLabel(p.status_name) }}</span>
                <button
                  v-if="canAddRemove"
                  class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]"
                  :disabled="removingId === p.id"
                  :title="removingId === p.id ? 'Removiendo...' : 'Quitar del embarque'"
                  @click="removePackage(p.id)"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
            </div>
            <p v-if="detailPackages.length === 0" class="py-6 text-center text-sm text-[#64748b]">
              Sin paquetes. Agregá paquetes en estado "Recibido en Miami" para despachar el embarque.
            </p>
          </div>
        </div>

        <p class="text-xs text-[#64748b]">Creado por {{ detailShipment.created_by_name ?? '—' }} · {{ detailShipment.created_at?.slice(0, 10) }}</p>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <button class="admin-btn admin-btn-ghost" @click="showDetail = false">Cerrar</button>
        </div>
      </template>
    </AppModal>

    <!-- Add packages modal -->
    <AppModal :visible="showAddPackages" title="Agregar paquetes al embarque" size="lg" @close="showAddPackages = false">
      <div class="space-y-4">
        <div v-if="detailShipment" class="flex items-center gap-2 rounded-lg border border-[#1e293b] bg-[#111d2e] px-3 py-2.5">
          <span class="rounded-full bg-[#69dca4]/15 px-2.5 py-0.5 text-xs font-semibold text-[#69dca4]">
            {{ detailShipment.mode === 'sea' ? 'Marítimo' : 'Aéreo' }}
          </span>
          <p class="text-xs text-[#64748b]">
            Solo se listan paquetes con servicio {{ detailShipment.mode === 'sea' ? 'Marítimo' : 'Aéreo' }} en estado "Recibido en Miami".
          </p>
        </div>

        <div class="flex gap-2">
          <input
            v-model="eligibleQuery"
            class="admin-input flex-1"
            type="text"
            placeholder="Buscar por tracking, destinatario o cliente..."
            @keyup.enter="searchEligible"
          />
          <button class="admin-btn admin-btn-secondary" @click="searchEligible">Buscar</button>
        </div>

        <div v-if="loadingEligible" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-11 animate-pulse rounded-lg bg-[#1e293b]" />
        </div>

        <div v-else class="space-y-2 max-h-80 overflow-y-auto pr-1">
          <label
            v-for="p in eligible"
            :key="p.id"
            :class="[
              'flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2.5 transition-colors',
              isChecked(p.id) ? 'border-[#69dca4]/50 bg-[#69dca4]/10' : 'border-[#1e293b] bg-[#0b1326] hover:border-[#334155]'
            ]"
          >
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-[#dae2fd]">{{ p.tracking_number }}</p>
              <p class="truncate text-xs text-[#64748b]">
                {{ p.recipient_name }} · {{ p.chargeable_weight ? p.chargeable_weight + ' kg' : '—' }} · {{ p.origin === 'local' ? 'Ingreso local' : 'Compra EEUU' }}{{ p.shipping_service_name ? ' · ' + p.shipping_service_name : '' }}
              </p>
            </div>
            <input type="checkbox" class="h-4 w-4 shrink-0 accent-[#69dca4]" :checked="isChecked(p.id)" @change="toggleSelect(p.id)" />
          </label>

          <p v-if="eligible.length === 0" class="py-6 text-center text-sm text-[#64748b]">
            No hay paquetes elegibles para este embarque (deben estar en estado "Recibido en Miami", con servicio correspondiente y no pertenecer a otro embarque).
          </p>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showAddPackages = false" :disabled="addingPackages">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="addingPackages || selectedIds.length === 0" @click="confirmAddPackages">
            {{ addingPackages ? 'Agregando...' : `Agregar ${selectedIds.length} paquete(s)` }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Delete Confirmation -->
    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar embarque"
      :message="`¿Seguro que querés eliminar el embarque ${deleting?.code}? Solo se eliminan embarques en borrador o cancelados. Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>