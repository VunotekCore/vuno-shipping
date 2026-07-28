<script setup lang="ts">
import { onMounted, ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { packageService, type Package } from '../services/packageService'
import { clientService, type Client } from '../services/clientService'
import { zoneService, type Zone } from '../services/zoneService'
import { useCrudView } from '../composables/useCrudView'
import { formatCurrency, statusColor } from '../utils/format'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'

const router = useRouter()
const clients = ref<Client[]>([])
const zones = ref<Zone[]>([])
const statusFilter = ref<string>('')

const {
  items: packages, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load: loadCrud, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<Package>({
  service: {
    list: (params) => packageService.list({ ...params, status: statusFilter.value || undefined }),
    create: (data) => packageService.create(data as any),
    update: (data) => packageService.update(data as any),
    delete: (id) => packageService.delete(id),
  },
  dataKey: 'packages',
  entityName: 'Paquete',
  getDefaultForm: () => ({
    client_id: null, sender_name: '', sender_phone: '', recipient_name: '',
    recipient_phone: '', recipient_address: '', recipient_city: '',
    recipient_department: '', zone_id: null, description: '', weight: null, length: null,
    width: null, height: null, declared_value: null, delivery_type: 'door', notes: '',
  }),
  populateForm: (p) => ({
    client_id: p.client_id, sender_name: p.sender_name, sender_phone: p.sender_phone ?? '',
    recipient_name: p.recipient_name, recipient_phone: p.recipient_phone,
    recipient_address: p.recipient_address, recipient_city: p.recipient_city ?? '',
    recipient_department: p.recipient_department ?? '', zone_id: p.zone_id, description: p.description ?? '',
    weight: p.weight, length: p.length, width: p.width, height: p.height,
    declared_value: p.declared_value, delivery_type: p.delivery_type_name ?? 'door',
    notes: p.notes ?? '',
  }),
  validateForm: (f) => {
    if (!f.sender_name) return 'El nombre del remitente es requerido'
    if (!f.recipient_name) return 'El nombre del destinatario es requerido'
    if (!f.recipient_phone) return 'El teléfono del destinatario es requerido'
    if (!f.recipient_address) return 'La dirección del destinatario es requerida'
    return null
  },
  getEntityId: (p) => p.id,
})

const clientOptions = computed(() => [
  { value: null as number | null, label: 'Sin cliente (avulso)' },
  ...clients.value.map(c => ({ value: c.id, label: c.name })),
])

const zoneOptions = computed(() => [
  { value: null as number | null, label: 'Sin zona' },
  ...zones.value.map(z => ({ value: z.id, label: `${z.name} ($${Number(z.surcharge).toFixed(2)})` })),
])

const deliveryOptions = [
  { value: 'door', label: 'Domicilio' },
  { value: 'branch', label: 'Sucursal' },
]

const fieldErrors = reactive<Record<string, string>>({
  sender_name: '', sender_phone: '',
  recipient_name: '', recipient_phone: '', recipient_address: '', recipient_city: '', recipient_department: '',
  description: '', notes: '',
})

function clearError(key: string) {
  fieldErrors[key] = ''
}

function validateField(key: string) {
  const v = String(form.value[key] ?? '')
  switch (key) {
    case 'sender_name':
      if (!v.trim()) { fieldErrors.sender_name = 'El nombre del remitente es requerido'; return }
      if (v.length > 255) { fieldErrors.sender_name = 'Máximo 255 caracteres'; return }
      fieldErrors.sender_name = ''
      break
    case 'sender_phone':
      if (v.length > 50) { fieldErrors.sender_phone = 'Máximo 50 caracteres'; return }
      fieldErrors.sender_phone = ''
      break
    case 'recipient_name':
      if (!v.trim()) { fieldErrors.recipient_name = 'El nombre del destinatario es requerido'; return }
      if (v.length > 255) { fieldErrors.recipient_name = 'Máximo 255 caracteres'; return }
      fieldErrors.recipient_name = ''
      break
    case 'recipient_phone':
      if (!v.trim()) { fieldErrors.recipient_phone = 'El teléfono del destinatario es requerido'; return }
      if (v.length > 50) { fieldErrors.recipient_phone = 'Máximo 50 caracteres'; return }
      fieldErrors.recipient_phone = ''
      break
    case 'recipient_address':
      if (!v.trim()) { fieldErrors.recipient_address = 'La dirección es requerida'; return }
      if (v.length > 500) { fieldErrors.recipient_address = 'Máximo 500 caracteres'; return }
      fieldErrors.recipient_address = ''
      break
    case 'recipient_city':
      if (v.length > 100) { fieldErrors.recipient_city = 'Máximo 100 caracteres'; return }
      fieldErrors.recipient_city = ''
      break
    case 'recipient_department':
      if (v.length > 100) { fieldErrors.recipient_department = 'Máximo 100 caracteres'; return }
      fieldErrors.recipient_department = ''
      break
    case 'description':
      if (v.length > 500) { fieldErrors.description = 'Máximo 500 caracteres'; return }
      fieldErrors.description = ''
      break
    case 'notes':
      if (v.length > 500) { fieldErrors.notes = 'Máximo 500 caracteres'; return }
      fieldErrors.notes = ''
      break
  }
}

const hasErrors = computed(() => Object.values(fieldErrors).some(Boolean))

function fv(key: string): string | number | null {
  const v = form.value[key]
  if (v == null) return null
  if (typeof v === 'string' || typeof v === 'number') return v
  return null
}

function handleSave() {
  for (const key of Object.keys(fieldErrors)) validateField(key)
  if (hasErrors.value) return
  save()
}

const columns: TableColumn[] = [
  { key: 'tracking_number', label: 'Tracking', width: '130px' },
  { key: 'recipient_name', label: 'Destinatario', width: '140px' },
  { key: 'recipient_department', label: 'Depto', width: '100px' },
  { key: 'status_name', label: 'Estado', width: '100px' },
  { key: 'flete_cost', label: 'Flete', width: '80px' },
  { key: 'delivery_cost', label: 'Delivery', width: '80px' },
  { key: 'shipping_cost', label: 'Total', width: '80px' },
  { key: 'delivery_type_name', label: 'Tipo', width: '80px' },
  { key: 'actions', label: '', width: '130px', align: 'right' },
]

function viewDetail(pkg: Package) {
  router.push(`/packages/${pkg.id}`)
}

async function load() {
  const [, clientsRes, zonesRes] = await Promise.all([
    loadCrud(),
    clientService.listAll().catch(() => ({ data: { data: { clients: [] as Client[] } } })),
    zoneService.listAll().catch(() => ({ data: { data: { zones: [] as Zone[] } } })),
  ])
  clients.value = clientsRes.data.data?.clients ?? []
  zones.value = zonesRes.data.data?.zones ?? []
}

function filterByStatus(status: string) {
  statusFilter.value = status
  page.value = 1
  load()
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Paquetes</h1>
        <p class="mt-1 text-sm text-[#64748b]">Gestionar envíos y paquetes</p>
      </div>
      <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear paquete
      </button>
    </div>

    <!-- Status filter tabs -->
    <div class="flex flex-wrap gap-2">
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === '' && 'bg-white/[0.08]'"
        @click="filterByStatus('')"
      >Todos</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'pending' && 'bg-white/[0.08]'"
        @click="filterByStatus('pending')"
      >Pendientes</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'in_transit' && 'bg-white/[0.08]'"
        @click="filterByStatus('in_transit')"
      >En tránsito</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'delivered' && 'bg-white/[0.08]'"
        @click="filterByStatus('delivered')"
      >Entregados</button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="packages.length === 0" empty-title="Sin paquetes" empty-desc="Creá el primer paquete del sistema.">
          <tr v-for="p in packages" :key="p.id">
            <td class="font-mono font-medium text-[#dae2fd]">{{ p.tracking_number }}</td>
            <td class="text-[#94a3b8]">{{ p.recipient_name }}</td>
            <td class="text-[#94a3b8]">{{ p.recipient_department ?? '—' }}</td>
            <td>
              <span class="admin-badge" :class="statusColor(p.status_name)">{{ p.status_name ?? '—' }}</span>
            </td>
            <td class="text-[#94a3b8] text-xs" :title="'Costo del envío internacional Miami → Nicaragua'">{{ formatCurrency(p.flete_cost) }}</td>
            <td class="text-[#94a3b8] text-xs" :title="'Costo de entrega a domicilio en Nicaragua (última milla)'">{{ formatCurrency(p.delivery_cost) }}</td>
            <td class="text-[#94a3b8] font-medium" :title="'Total = flete + delivery. Monto final que paga el cliente'">{{ formatCurrency(p.shipping_cost) }}</td>
            <td class="text-[#94a3b8]">{{ p.delivery_type_name ?? '—' }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="viewDetail(p)" title="Ver detalle">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(p)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button
                  class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]"
                  @click="openDelete(p)"
                  title="Eliminar"
                  :disabled="p.status_name !== 'pending'"
                >
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
              <div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" />
            </div>
          </div>
        </div>
      </template>
      <template v-else-if="packages.length > 0">
        <div v-for="p in packages" :key="p.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-mono font-medium text-[#dae2fd] truncate">{{ p.tracking_number }}</p>
            <span class="admin-badge shrink-0" :class="statusColor(p.status_name)">{{ p.status_name ?? '—' }}</span>
          </div>
          <p class="text-sm text-[#94a3b8] truncate">{{ p.recipient_name }}{{ p.recipient_department ? ' · ' + p.recipient_department : '' }}</p>
          <p class="text-xs text-[#64748b]" title="Flete (envío internacional) + Delivery (última milla)">
            Flete: {{ formatCurrency(p.flete_cost) }} | Delivery: {{ formatCurrency(p.delivery_cost) }} | Total: {{ formatCurrency(p.shipping_cost) }}
            {{ p.delivery_type_name ? ' · ' + p.delivery_type_name : '' }}
          </p>
          <div class="border-t border-[#1e293b] pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="viewDetail(p)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              Ver
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(p)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button
              class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]"
              @click="openDelete(p)"
              :disabled="p.status_name !== 'pending'"
            >
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
          <p class="admin-empty-state-title">Sin paquetes</p>
          <p class="admin-empty-state-desc">Creá el primer paquete del sistema.</p>
        </div>
      </div>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar paquete' : 'Crear paquete'" size="lg" @close="showModal = false">
      <form class="space-y-5" @submit.prevent="handleSave" novalidate>
        <!-- Sección: Cliente -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
            Cliente
          </div>
          <div class="mt-3">
            <AppFormField
              :model-value="fv('client_id')"
              @update:model-value="(v: string | number | null) => form.client_id = v"
              label="Cliente asociado"
              type="select"
              icon="user"
              placeholder="Seleccionar cliente..."
              :options="clientOptions"
              :disabled="saving"
              helper="Seleccioná un cliente o dejalo vacío para venta avulso"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Remitente -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
            Remitente
          </div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('sender_name')"
              @update:model-value="(v: string | number | null) => form.sender_name = v"
              label="Nombre"
              icon="user"
              placeholder="Nombre del remitente"
              :required="true"
              :disabled="saving"
              :maxlength="255"
              :error="fieldErrors.sender_name"
              @blur="validateField('sender_name')"
              @input="clearError('sender_name')"
            />
            <AppFormField
              :model-value="fv('sender_phone')"
              @update:model-value="(v: string | number | null) => form.sender_phone = v"
              label="Teléfono"
              icon="phone"
              placeholder="+1 (305) 000-0000"
              :disabled="saving"
              :maxlength="50"
              helper="Formato: +1 (305) 000-0000"
              :error="fieldErrors.sender_phone"
              @blur="validateField('sender_phone')"
              @input="clearError('sender_phone')"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Destinatario -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
            Destinatario
          </div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('recipient_name')"
              @update:model-value="(v: string | number | null) => form.recipient_name = v"
              label="Nombre"
              icon="user"
              placeholder="Nombre del destinatario"
              :required="true"
              :disabled="saving"
              :maxlength="255"
              :error="fieldErrors.recipient_name"
              @blur="validateField('recipient_name')"
              @input="clearError('recipient_name')"
            />
            <AppFormField
              :model-value="fv('recipient_phone')"
              @update:model-value="(v: string | number | null) => form.recipient_phone = v"
              label="Teléfono"
              icon="phone"
              placeholder="+505 8888-8888"
              :required="true"
              :disabled="saving"
              :maxlength="50"
              :error="fieldErrors.recipient_phone"
              @blur="validateField('recipient_phone')"
              @input="clearError('recipient_phone')"
            />
          </div>
          <div class="mt-4">
            <AppFormField
              :model-value="fv('recipient_address')"
              @update:model-value="(v: string | number | null) => form.recipient_address = v"
              label="Dirección"
              icon="map-pin"
              placeholder="Dirección completa de entrega"
              :required="true"
              :disabled="saving"
              :maxlength="500"
              :error="fieldErrors.recipient_address"
              @blur="validateField('recipient_address')"
              @input="clearError('recipient_address')"
            />
          </div>
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('recipient_city')"
              @update:model-value="(v: string | number | null) => form.recipient_city = v"
              label="Ciudad"
              icon="map-pin"
              placeholder="Ciudad"
              :disabled="saving"
              :maxlength="100"
              :error="fieldErrors.recipient_city"
              @blur="validateField('recipient_city')"
              @input="clearError('recipient_city')"
            />
            <AppFormField
              :model-value="fv('recipient_department')"
              @update:model-value="(v: string | number | null) => form.recipient_department = v"
              label="Departamento"
              icon="map-pin"
              placeholder="Departamento"
              :disabled="saving"
              :maxlength="100"
              :error="fieldErrors.recipient_department"
              @blur="validateField('recipient_department')"
              @input="clearError('recipient_department')"
            />
          </div>
          <div class="mt-4">
            <AppFormField
              :model-value="fv('zone_id')"
              @update:model-value="(v: string | number | null) => form.zone_id = v"
              label="Zona de entrega"
              type="select"
              icon="map-pin"
              placeholder="Seleccionar zona..."
              :options="zoneOptions"
              :disabled="saving"
              helper="Zona para calcular el costo de entrega a domicilio"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Detalles del paquete -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16.5 9.4l-9-5.19M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            Detalles del paquete
          </div>
          <div class="mt-3">
            <AppFormField
              :model-value="fv('description')"
              @update:model-value="(v: string | number | null) => form.description = v"
              label="Descripción"
              icon="package"
              placeholder="Contenido del paquete"
              :disabled="saving"
              :maxlength="500"
              :error="fieldErrors.description"
              @blur="validateField('description')"
              @input="clearError('description')"
            />
          </div>
          <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <AppFormField
              :model-value="fv('weight')"
              @update:model-value="(v: string | number | null) => form.weight = v"
              label="Peso"
              type="number"
              icon="scale"
              placeholder="0.0"
              helper="libras"
              :disabled="saving"
              :min="0"
              :step="0.1"
            />
            <AppFormField
              :model-value="fv('length')"
              @update:model-value="(v: string | number | null) => form.length = v"
              label="Largo"
              type="number"
              icon="hash"
              placeholder="0.0"
              helper="pulgadas"
              :disabled="saving"
              :min="0"
              :step="0.1"
            />
            <AppFormField
              :model-value="fv('width')"
              @update:model-value="(v: string | number | null) => form.width = v"
              label="Ancho"
              type="number"
              icon="hash"
              placeholder="0.0"
              helper="pulgadas"
              :disabled="saving"
              :min="0"
              :step="0.1"
            />
            <AppFormField
              :model-value="fv('height')"
              @update:model-value="(v: string | number | null) => form.height = v"
              label="Alto"
              type="number"
              icon="hash"
              placeholder="0.0"
              helper="pulgadas"
              :disabled="saving"
              :min="0"
              :step="0.1"
            />
          </div>
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('declared_value')"
              @update:model-value="(v: string | number | null) => form.declared_value = v"
              label="Valor declarado"
              type="number"
              icon="dollar"
              placeholder="0.00"
              helper="USD — Valor de compra del paquete (usado para reglas de tipo porcentaje)"
              :disabled="saving"
              :min="0"
              :step="0.01"
            />
            <AppFormField
              :model-value="fv('delivery_type')"
              @update:model-value="(v: string | number | null) => form.delivery_type = v"
              label="Tipo de entrega"
              type="select"
              icon="truck"
              placeholder="Seleccionar tipo..."
              :options="deliveryOptions"
              :disabled="saving"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Notas -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
            Notas adicionales
          </div>
          <div class="mt-3">
            <AppFormField
              :model-value="fv('notes')"
              @update:model-value="(v: string | number | null) => form.notes = v"
              label="Notas"
              type="textarea"
              icon="file-text"
              placeholder="Observaciones adicionales sobre el paquete..."
              :disabled="saving"
              :maxlength="500"
              :rows="2"
              :error="fieldErrors.notes"
              @blur="validateField('notes')"
              @input="clearError('notes')"
            />
          </div>
        </div>
      </form>

      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button
            class="admin-btn admin-btn-primary"
            :disabled="saving || !form.sender_name || !form.recipient_name || !form.recipient_phone || !form.recipient_address || hasErrors"
            @click="handleSave"
          >
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Delete Confirmation -->
    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar paquete"
      :message="`¿Seguro que querés eliminar el paquete ${deleting?.tracking_number}? Solo se pueden eliminar paquetes con estado pendiente. Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
