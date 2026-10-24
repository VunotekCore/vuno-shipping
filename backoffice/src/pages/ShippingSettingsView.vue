<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { shippingSettingsService, type ShippingService, type DeliveryOption, type ServiceOption } from '../services/shippingSettingsService'
import { deliveryTypeService, type DeliveryType } from '../services/deliveryTypeService'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppSwitch from '../components/ui/AppSwitch.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const toast = useToast()
const activeTab = ref<'delivery_types' | 'services' | 'options' | 'mapping'>('delivery_types')

// --- Shared state ---
const saving = ref(false)
const showModal = ref(false)
const showDelete = ref(false)
const editing = ref<ShippingService | DeliveryOption | DeliveryType | null>(null)
const deleting = ref<ShippingService | DeliveryOption | DeliveryType | null>(null)
const form = ref({ name: '', description: '', delivery_frequency: '', is_active: true, category: 'international', delivery_type_id: null as number | null })

// Delivery types lookup for service form
const deliveryTypeOptions = ref<DeliveryType[]>([])

// ============================================================
// TAB 2: Shipping Services
// ============================================================
const services = ref<ShippingService[]>([])
const servicesLoading = ref(false)

const serviceColumns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '160px' },
  { key: 'description', label: 'Descripción' },
  { key: 'is_active', label: 'Estado', width: '90px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

async function loadServices() {
  servicesLoading.value = true
  try {
    const res = await shippingSettingsService.listServices()
    services.value = res.data.data?.shipping_services ?? []
  } catch { toast.error('Error al cargar servicios') }
  finally { servicesLoading.value = false }
}

function openCreateService() {
  editing.value = null
  form.value = { name: '', description: '', delivery_frequency: '', is_active: true, category: 'international', delivery_type_id: null }
  showModal.value = true
}

function openEditService(s: ShippingService) {
  editing.value = s
  form.value = { name: s.name, description: s.description ?? '', delivery_frequency: '', is_active: !!s.is_active, category: 'international', delivery_type_id: s.delivery_type_id ?? null }
  showModal.value = true
}

function openDeleteService(s: ShippingService) {
  deleting.value = s
  showDelete.value = true
}

async function saveService() {
  if (!form.value.name.trim()) { toast.error('El nombre es requerido'); return }
  saving.value = true
  try {
    if (editing.value) {
      await shippingSettingsService.updateService({ id: (editing.value as ShippingService).id, ...form.value, description: form.value.description || null, delivery_type_id: form.value.delivery_type_id })
      toast.success('Servicio actualizado')
    } else {
      await shippingSettingsService.createService({ ...form.value, description: form.value.description || undefined, delivery_type_id: form.value.delivery_type_id ?? undefined })
      toast.success('Servicio creado')
    }
    showModal.value = false
    await loadServices()
  } catch { toast.error('Error al guardar') }
  finally { saving.value = false }
}

async function confirmDeleteService() {
  if (!deleting.value) return
  try {
    await shippingSettingsService.deleteService((deleting.value as ShippingService).id)
    toast.success('Servicio eliminado')
    showDelete.value = false
    deleting.value = null
    await loadServices()
  } catch { toast.error('Error al eliminar') }
}

// ============================================================
// TAB 3: Delivery Options
// ============================================================
const options = ref<DeliveryOption[]>([])
const optionsLoading = ref(false)

const optionColumns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '160px' },
  { key: 'description', label: 'Descripción' },
  { key: 'is_active', label: 'Estado', width: '90px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

async function loadOptions() {
  optionsLoading.value = true
  try {
    const res = await shippingSettingsService.listOptions()
    options.value = res.data.data?.delivery_options ?? []
  } catch { toast.error('Error al cargar opciones') }
  finally { optionsLoading.value = false }
}

function openCreateOption() {
  editing.value = null
  form.value = { name: '', description: '', delivery_frequency: '', is_active: true, category: 'international', delivery_type_id: null }
  showModal.value = true
}

function openEditOption(o: DeliveryOption) {
  editing.value = o
  form.value = { name: o.name, description: o.description ?? '', delivery_frequency: '', is_active: !!o.is_active, category: 'international', delivery_type_id: null }
  showModal.value = true
}

function openDeleteOption(o: DeliveryOption) {
  deleting.value = o
  showDelete.value = true
}

async function saveOption() {
  if (!form.value.name.trim()) { toast.error('El nombre es requerido'); return }
  saving.value = true
  try {
    if (editing.value) {
      await shippingSettingsService.updateOption({ id: (editing.value as DeliveryOption).id, ...form.value, description: form.value.description || null })
      toast.success('Opción actualizada')
    } else {
      await shippingSettingsService.createOption({ ...form.value, description: form.value.description || undefined })
      toast.success('Opción creada')
    }
    showModal.value = false
    await loadOptions()
  } catch { toast.error('Error al guardar') }
  finally { saving.value = false }
}

async function confirmDeleteOption() {
  if (!deleting.value) return
  try {
    await shippingSettingsService.deleteOption((deleting.value as DeliveryOption).id)
    toast.success('Opción eliminada')
    showDelete.value = false
    deleting.value = null
    await loadOptions()
  } catch { toast.error('Error al eliminar') }
}

// ============================================================
// TAB 4: Service-Delivery Mapping
// ============================================================
const mappingServiceId = ref<number | null>(null)
const allDeliveryOptions = ref<DeliveryOption[]>([])
const assignedOptions = ref<ServiceOption[]>([])
const mappingLoading = ref(false)
const mappingSaving = ref(false)
const selectedDefault = ref<number | null>(null)

const mappingService = computed(() =>
  services.value.find(s => s.id === mappingServiceId.value) ?? null
)

// Build toggle state: which options are checked and which is default
const optionToggles = computed(() => {
  const assigned = new Set(assignedOptions.value.map(o => o.id))
  return allDeliveryOptions.value.map(o => ({
    ...o,
    checked: assigned.has(o.id),
    is_default: assignedOptions.value.find(a => a.id === o.id)?.is_default ?? false,
  }))
})

watch(mappingServiceId, async (id) => {
  if (!id) {
    assignedOptions.value = []
    selectedDefault.value = null
    return
  }
  mappingLoading.value = true
  try {
    const res = await shippingSettingsService.listServiceOptions(id)
    assignedOptions.value = res.data.data?.service_options ?? []
    const def = assignedOptions.value.find(o => o.is_default)
    selectedDefault.value = def?.id ?? null
  } catch { toast.error('Error al cargar asignaciones') }
  finally { mappingLoading.value = false }
})

function toggleOption(optionId: number) {
  const idx = assignedOptions.value.findIndex(o => o.id === optionId)
  if (idx >= 0) {
    assignedOptions.value.splice(idx, 1)
    if (selectedDefault.value === optionId) {
      selectedDefault.value = assignedOptions.value.length > 0 ? assignedOptions.value[0].id : null
    }
  } else {
    assignedOptions.value.push({ id: optionId, name: '', description: null, is_default: false })
    if (selectedDefault.value === null) {
      selectedDefault.value = optionId
    }
  }
}

function setDefault(optionId: number) {
  selectedDefault.value = optionId
}

async function saveMapping() {
  if (!mappingServiceId.value) return
  mappingSaving.value = true
  try {
    const ids = optionToggles.value
      .filter(o => o.checked)
      .map(o => ({ delivery_option_id: o.id, is_default: o.id === selectedDefault.value }))
    await shippingSettingsService.updateServiceOptions({
      shipping_service_id: mappingServiceId.value,
      delivery_option_ids: ids,
    })
    toast.success('Asignación guardada')
  } catch { toast.error('Error al guardar') }
  finally { mappingSaving.value = false }
}

// ============================================================
// TAB 1: Delivery Types
// ============================================================
const deliveryTypes = ref<DeliveryType[]>([])
const deliveryTypesLoading = ref(false)

const deliveryTypeColumns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '160px' },
  { key: 'description', label: 'Descripción' },
  { key: 'delivery_frequency', label: 'Frecuencia de entrega', width: '200px' },
  { key: 'category', label: 'Categoría', width: '140px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

async function loadDeliveryTypes() {
  deliveryTypesLoading.value = true
  try {
    const res = await deliveryTypeService.list()
    deliveryTypes.value = res.data.data?.delivery_types ?? []
  } catch { toast.error('Error al cargar tipos de entrega') }
  finally { deliveryTypesLoading.value = false }
}

function openCreateDeliveryType() {
  editing.value = null
  form.value = { name: '', description: '', delivery_frequency: '', is_active: true, category: 'international', delivery_type_id: null }
  showModal.value = true
}

function openEditDeliveryType(dt: DeliveryType) {
  editing.value = dt
  form.value = { name: dt.name, description: dt.description ?? '', delivery_frequency: dt.delivery_frequency ?? '', is_active: true, category: dt.category ?? 'international', delivery_type_id: null }
  showModal.value = true
}

function openDeleteDeliveryType(dt: DeliveryType) {
  deleting.value = dt
  showDelete.value = true
}

async function saveDeliveryType() {
  if (!form.value.name.trim()) { toast.error('El nombre es requerido'); return }
  saving.value = true
  try {
    if (editing.value) {
      await deliveryTypeService.update({ id: (editing.value as DeliveryType).id, name: form.value.name, description: form.value.description || null, delivery_frequency: form.value.delivery_frequency || null, category: form.value.category })
      toast.success('Tipo de entrega actualizado')
    } else {
      await deliveryTypeService.create({ name: form.value.name, description: form.value.description || undefined, delivery_frequency: form.value.delivery_frequency || null, category: form.value.category })
      toast.success('Tipo de entrega creado')
    }
    showModal.value = false
    await loadDeliveryTypes()
  } catch { toast.error('Error al guardar') }
  finally { saving.value = false }
}

async function confirmDeleteDeliveryType() {
  if (!deleting.value) return
  try {
    await deliveryTypeService.delete((deleting.value as DeliveryType).id)
    toast.success('Tipo de entrega eliminado')
    showDelete.value = false
    deleting.value = null
    await loadDeliveryTypes()
  } catch { toast.error('Error al eliminar') }
}

// ============================================================
// Modal logic — shared by tabs 1-3
// ============================================================
const modalTitle = computed(() => {
  const labels: Record<string, string> = { services: 'Servicio', options: 'Opción', delivery_types: 'Tipo de Entrega' }
  const base = labels[activeTab.value] || 'Elemento'
  return editing.value ? `Editar ${base}` : `Crear ${base}`
})

function handleSave() {
  if (activeTab.value === 'services') saveService()
  else if (activeTab.value === 'options') saveOption()
  else if (activeTab.value === 'delivery_types') saveDeliveryType()
}

function handleConfirmDelete() {
  if (activeTab.value === 'services') confirmDeleteService()
  else if (activeTab.value === 'options') confirmDeleteOption()
  else if (activeTab.value === 'delivery_types') confirmDeleteDeliveryType()
}

function handleOpenCreate() {
  if (activeTab.value === 'services') openCreateService()
  else if (activeTab.value === 'options') openCreateOption()
  else if (activeTab.value === 'delivery_types') openCreateDeliveryType()
}

// ============================================================
// Init
// ============================================================
onMounted(async () => {
  const dtRes = await deliveryTypeService.list().catch(() => null)
  deliveryTypeOptions.value = dtRes?.data?.data?.delivery_types ?? []
  await Promise.all([loadServices(), loadOptions(), loadDeliveryTypes()])
})

watch(options, (val) => { allDeliveryOptions.value = val }, { immediate: true })
</script>

<template>
  <div class="space-y-6">
    <!-- Page header -->
    <PageHeader title="Configuración de Envíos" subtitle="Administrar servicios de envío, opciones de entrega y su asignación">
      <template #actions>
        <button
          v-if="activeTab !== 'mapping'"
          class="admin-btn admin-btn-primary sm:w-auto w-full"
          @click="handleOpenCreate"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          {{ { services: 'Crear servicio', options: 'Crear opción', delivery_types: 'Crear tipo' }[activeTab] }}
        </button>
      </template>
    </PageHeader>

    <!-- Tabs -->
    <div class="flex gap-1 rounded-xl bg-canvas p-1 border border-line w-fit">
      <button
        :class="['rounded-lg px-4 py-2 text-sm font-medium transition-all', activeTab === 'delivery_types' ? 'bg-primary/15 text-primary' : 'text-subtle hover:text-muted']"
        @click="activeTab = 'delivery_types'"
      >Tipos de Entrega</button>
      <button
        :class="['rounded-lg px-4 py-2 text-sm font-medium transition-all', activeTab === 'services' ? 'bg-primary/15 text-primary' : 'text-subtle hover:text-muted']"
        @click="activeTab = 'services'"
      >Servicios de Envío</button>
      <button
        :class="['rounded-lg px-4 py-2 text-sm font-medium transition-all', activeTab === 'options' ? 'bg-primary/15 text-primary' : 'text-subtle hover:text-muted']"
        @click="activeTab = 'options'"
      >Opciones de Entrega</button>
      <button
        :class="['rounded-lg px-4 py-2 text-sm font-medium transition-all', activeTab === 'mapping' ? 'bg-primary/15 text-primary' : 'text-subtle hover:text-muted']"
        @click="activeTab = 'mapping'"
      >Mapping</button>
    </div>

    <!-- ==================== TAB 1: DELIVERY TYPES ==================== -->
    <div v-show="activeTab === 'delivery_types'" class="admin-card">
      <AppTable
        :columns="deliveryTypeColumns"
        :loading="deliveryTypesLoading"
        :is-empty="deliveryTypes.length === 0"
        empty-title="Sin tipos de entrega"
        empty-desc="Creá el primer tipo de entrega."
      >
            <tr v-for="dt in deliveryTypes" :key="dt.id">
              <td class="font-medium text-content">{{ dt.name }}</td>
              <td class="text-muted">{{ dt.description || '—' }}</td>
              <td class="text-muted">{{ dt.delivery_frequency || '—' }}</td>
              <td>
                <span :class="dt.category === 'international' ? 'admin-badge admin-badge-info' : 'admin-badge admin-badge-success'">
                  {{ dt.category === 'international' ? 'Internacional' : 'Local' }}
                </span>
              </td>
              <td class="text-right">
                <div class="flex items-center justify-end gap-1">
                  <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEditDeliveryType(dt)" title="Editar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDeleteDeliveryType(dt)" title="Eliminar">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                  </button>
                </div>
              </td>
            </tr>
      </AppTable>
    </div>

    <!-- ==================== TAB 2: SERVICES ==================== -->
    <div v-show="activeTab === 'services'" class="admin-card">
      <AppTable
        :columns="serviceColumns"
        :loading="servicesLoading"
        :is-empty="services.length === 0"
        empty-title="Sin servicios de envío"
        empty-desc="Creá el primer servicio de envío."
      >
        <tr v-for="s in services" :key="s.id">
          <td class="font-medium text-content">{{ s.name }}</td>
          <td class="text-muted">{{ s.description || '—' }}</td>
          <td>
            <span :class="s.is_active ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-warning'">
              {{ s.is_active ? 'Activo' : 'Inactivo' }}
            </span>
          </td>
          <td class="text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEditService(s)" title="Editar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDeleteService(s)" title="Eliminar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </div>
          </td>
        </tr>
      </AppTable>
    </div>

    <!-- ==================== TAB 3: OPTIONS ==================== -->
    <div v-show="activeTab === 'options'" class="admin-card">
      <AppTable
        :columns="optionColumns"
        :loading="optionsLoading"
        :is-empty="options.length === 0"
        empty-title="Sin opciones de entrega"
        empty-desc="Creá la primera opción de entrega."
      >
        <tr v-for="o in options" :key="o.id">
          <td class="font-medium text-content">{{ o.name }}</td>
          <td class="text-muted">{{ o.description || '—' }}</td>
          <td>
            <span :class="o.is_active ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-warning'">
              {{ o.is_active ? 'Activo' : 'Inactivo' }}
            </span>
          </td>
          <td class="text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEditOption(o)" title="Editar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDeleteOption(o)" title="Eliminar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </div>
          </td>
        </tr>
      </AppTable>
    </div>

    <!-- ==================== TAB 4: MAPPING ==================== -->
    <div v-show="activeTab === 'mapping'" class="space-y-4">
      <!-- Select service -->
      <div class="admin-card p-5">
        <label class="mb-1.5 block text-sm font-medium text-content">Servicio de envío</label>
        <AppDropdown
          :model-value="mappingServiceId"
          @update:model-value="(v: string | number | null) => mappingServiceId = v as number | null"
          :options="services.map(s => ({ value: s.id, label: s.name + (s.description ? ' — ' + s.description : '') }))"
          placeholder="Seleccionar servicio..."
        />
      </div>

      <!-- Options for selected service -->
      <div v-if="mappingServiceId" class="admin-card p-5">
        <div class="flex items-center justify-between mb-4">
          <p class="text-sm font-semibold text-content">Opciones de entrega para: {{ mappingService?.name }}</p>
          <button
            class="admin-btn admin-btn-primary admin-btn-sm"
            :disabled="mappingSaving"
            @click="saveMapping"
          >
            {{ mappingSaving ? 'Guardando...' : 'Guardar asignación' }}
          </button>
        </div>

        <div v-if="mappingLoading" class="space-y-3">
          <div v-for="i in 3" :key="i" class="h-12 animate-pulse rounded-lg bg-surface-raised" />
        </div>

        <div v-else-if="allDeliveryOptions.length === 0" class="text-sm text-subtle italic">
          No hay opciones de entrega disponibles. Creá opciones en la pestaña "Opciones de Entrega".
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="opt in allDeliveryOptions"
            :key="opt.id"
            class="flex items-center justify-between rounded-lg border border-line bg-canvas/50 px-4 py-3 transition-colors hover:border-line-strong"
            :class="{ 'border-primary/30': optionToggles.find(o => o.id === opt.id)?.checked }"
          >
            <div class="flex items-center gap-3">
              <input
                type="checkbox"
                :checked="optionToggles.find(o => o.id === opt.id)?.checked ?? false"
                @change="toggleOption(opt.id)"
                class="h-4 w-4 rounded border-muted bg-canvas text-primary focus:ring-primary"
              />
              <div>
                <p class="text-sm font-medium text-content">{{ opt.description || opt.name }}</p>
                <p class="text-xs text-subtle">{{ opt.name }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <label
                v-if="optionToggles.find(o => o.id === opt.id)?.checked"
                class="flex items-center gap-1.5 text-xs text-subtle cursor-pointer"
                @click.prevent="setDefault(opt.id)"
              >
                <input
                  type="radio"
                  name="default_option"
                  :checked="selectedDefault === opt.id"
                  class="h-3.5 w-3.5 border-muted bg-canvas text-primary focus:ring-primary"
                />
                Default
              </label>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="admin-card p-5">
        <div class="admin-empty-state py-8">
          <svg class="admin-empty-state-icon" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
          </svg>
          <p class="admin-empty-state-title">Seleccioná un servicio</p>
          <p class="admin-empty-state-desc">Elegí un servicio de envío para configurar sus opciones de entrega.</p>
        </div>
      </div>
    </div>

    <!-- ==================== SHARED CREATE/EDIT MODAL ==================== -->
    <AppModal :visible="showModal" :title="modalTitle" @close="showModal = false" size="sm">
      <form class="space-y-4" @submit.prevent="handleSave">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">Nombre *</label>
          <input v-model="form.name" class="admin-input" placeholder="Nombre" required :disabled="saving" maxlength="100" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-muted">Descripción</label>
          <input v-model="form.description" class="admin-input" placeholder="Descripción opcional" :disabled="saving" />
        </div>
        <div v-if="activeTab === 'delivery_types'">
          <label class="mb-1.5 block text-sm font-medium text-muted">Frecuencia de entrega</label>
          <input v-model="form.delivery_frequency" class="admin-input" placeholder="Ej: Lunes, Miércoles y Viernes / Diario / Semanal" :disabled="saving" />
          <p class="mt-1 text-xs text-subtle">Campo informativo. Se mostrará en la Lista de precios.</p>
        </div>
        <div v-if="activeTab === 'delivery_types'">
          <label class="mb-1.5 block text-sm font-medium text-content">Categoría</label>
          <select v-model="form.category" class="admin-input" :disabled="saving">
            <option value="international">Internacional</option>
            <option value="local">Local</option>
          </select>
        </div>
        <div v-if="activeTab === 'services'">
          <label class="mb-1.5 block text-sm font-medium text-content">Tipo de entrega asociado</label>
          <select v-model="form.delivery_type_id" class="admin-input" :disabled="saving">
            <option :value="null">Ninguno</option>
            <option v-for="dt in deliveryTypeOptions" :key="dt.id" :value="dt.id">{{ dt.name }} — {{ dt.description || '' }}</option>
          </select>
        </div>
        <div v-if="activeTab !== 'delivery_types'" class="flex items-center justify-between rounded-lg border border-line bg-canvas/50 px-4 py-3">
          <div>
            <p class="text-sm font-medium text-content">Activo</p>
            <p class="text-xs text-subtle">Los elementos inactivos no aparecen en los selectores</p>
          </div>
          <AppSwitch :model-value="!!form.is_active" @update:model-value="(v: boolean) => { form.is_active = v }" :disabled="saving" />
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving || !form.name.trim()" @click="handleSave">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- ==================== SHARED DELETE DIALOG ==================== -->
    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar"
      :message="`¿Seguro que querés eliminar ${deleting?.name}?`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="handleConfirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
