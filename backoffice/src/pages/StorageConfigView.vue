<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { warehouseService, type Warehouse } from '../services/warehouseService'
import { storageCategoryService, type StorageCategory } from '../services/storageCategoryService'
import { storageLocationService, type StorageLocation } from '../services/storageLocationService'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppSwitch from '../components/ui/AppSwitch.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'

const toast = useToast()
const activeTab = ref<'warehouses' | 'categories' | 'locations'>('warehouses')
const saving = ref(false)

// ============================================================
// TAB 1: Warehouse
// ============================================================
const warehouses = ref<Warehouse[]>([])
const warehousesLoading = ref(false)
const warehouseColumns: TableColumn[] = [
  { key: 'name', label: 'Bodega' },
  { key: 'code', label: 'Código', width: '120px' },
  { key: 'origin', label: 'Origen', width: '130px' },
  { key: 'location_count', label: 'Ubicaciones', width: '120px' },
  { key: 'actions', label: '', width: '100px' },
]

async function loadWarehouses() {
  warehousesLoading.value = true
  try {
    const res = await warehouseService.list({ limit: 100 })
    warehouses.value = res.data.data?.warehouses ?? []
  } catch { toast.error('Error al cargar bodegas') }
  finally { warehousesLoading.value = false }
}

const showWarehouseModal = ref(false)
const editingWarehouse = ref<Warehouse | null>(null)
const warehouseForm = ref({ name: '', code: '', origin: 'online' as 'online' | 'local', address: '', is_active: true })

function openCreateWarehouse() {
  editingWarehouse.value = null
  warehouseForm.value = { name: '', code: '', origin: 'online', address: '', is_active: true }
  showWarehouseModal.value = true
}

function openEditWarehouse(w: Warehouse) {
  editingWarehouse.value = w
  warehouseForm.value = { name: w.name, code: w.code, origin: w.origin, address: w.address ?? '', is_active: !!w.is_active }
  showWarehouseModal.value = true
}

async function saveWarehouse() {
  const f = warehouseForm.value
  if (!f.name.trim() || !f.code.trim()) { toast.error('Nombre y código son obligatorios'); return }
  saving.value = true
  try {
    const payload = { name: f.name, code: f.code, origin: f.origin, address: f.address || null, is_active: f.is_active ? 1 : 0 }
    if (editingWarehouse.value) {
      await warehouseService.update({ id: editingWarehouse.value.id, ...payload })
      toast.success('Bodega actualizada')
    } else {
      await warehouseService.create(payload)
      toast.success('Bodega creada')
    }
    showWarehouseModal.value = false
    await loadWarehouses()
  } catch { toast.error('Error al guardar bodega') }
  finally { saving.value = false }
}

// ============================================================
// TAB 2: Categories
// ============================================================
const categories = ref<StorageCategory[]>([])
const categoriesLoading = ref(false)
const categoryColumns: TableColumn[] = [
  { key: 'name', label: 'Clasificación' },
  { key: 'color', label: 'Color', width: '90px' },
  { key: 'description', label: 'Descripción' },
  { key: 'package_count', label: 'Paquetes', width: '100px' },
  { key: 'actions', label: '', width: '100px' },
]

async function loadCategories() {
  categoriesLoading.value = true
  try {
    const res = await storageCategoryService.list({ limit: 100 })
    categories.value = res.data.data?.storage_categories ?? []
  } catch { toast.error('Error al cargar clasificaciones') }
  finally { categoriesLoading.value = false }
}

const showCategoryModal = ref(false)
const editingCategory = ref<StorageCategory | null>(null)
const categoryForm = ref({ name: '', color: '#3b82f6', description: '', is_active: true })

function openCreateCategory() {
  editingCategory.value = null
  categoryForm.value = { name: '', color: '#3b82f6', description: '', is_active: true }
  showCategoryModal.value = true
}

function openEditCategory(c: StorageCategory) {
  editingCategory.value = c
  categoryForm.value = { name: c.name, color: c.color || '#3b82f6', description: c.description ?? '', is_active: !!c.is_active }
  showCategoryModal.value = true
}

async function saveCategory() {
  const f = categoryForm.value
  if (!f.name.trim()) { toast.error('El nombre es obligatorio'); return }
  saving.value = true
  try {
    const payload = { name: f.name, color: f.color || null, description: f.description || null, is_active: f.is_active ? 1 : 0 }
    if (editingCategory.value) {
      await storageCategoryService.update({ id: editingCategory.value.id, ...payload })
      toast.success('Clasificación actualizada')
    } else {
      await storageCategoryService.create(payload)
      toast.success('Clasificación creada')
    }
    showCategoryModal.value = false
    await loadCategories()
  } catch { toast.error('Error al guardar clasificación') }
  finally { saving.value = false }
}

// ============================================================
// TAB 3: Locations
// ============================================================
const locations = ref<StorageLocation[]>([])
const locationsLoading = ref(false)
const locationFilter = ref<number | null>(null)
const locationColumns: TableColumn[] = [
  { key: 'code', label: 'Código' },
  { key: 'warehouse_name', label: 'Bodega' },
  { key: 'category_name', label: 'Clasificación', width: '140px' },
  { key: 'occupied', label: 'Ocupación', width: '120px' },
  { key: 'is_active', label: 'Estado', width: '110px' },
  { key: 'actions', label: '', width: '100px' },
]

async function loadLocations() {
  locationsLoading.value = true
  try {
    const res = locationFilter.value
      ? await storageLocationService.list({ warehouse_id: locationFilter.value, limit: 100 })
      : await storageLocationService.list({ limit: 100 })
    locations.value = res.data.data?.storage_locations ?? []
  } catch { toast.error('Error al cargar ubicaciones') }
  finally { locationsLoading.value = false }
}

const showLocationModal = ref(false)
const editingLocation = ref<StorageLocation | null>(null)
const locationForm = ref({
  warehouse_id: null as number | null, code: '', aisle: '', rack: '', level: '', position: '',
  category_id: null as number | null, capacity: 1, is_active: true,
})

function openCreateLocation() {
  editingLocation.value = null
  locationForm.value = { warehouse_id: locationFilter.value, code: '', aisle: '', rack: '', level: '', position: '', category_id: null, capacity: 1, is_active: true }
  showLocationModal.value = true
}

function openEditLocation(l: StorageLocation) {
  editingLocation.value = l
  locationForm.value = {
    warehouse_id: l.warehouse_id, code: l.code, aisle: l.aisle ?? '', rack: l.rack ?? '',
    level: l.level ?? '', position: l.position ?? '', category_id: l.category_id, capacity: l.capacity ?? 1, is_active: !!l.is_active,
  }
  showLocationModal.value = true
}

async function saveLocation() {
  const f = locationForm.value
  if (!f.warehouse_id) { toast.error('Seleccioná una bodega'); return }
  saving.value = true
  try {
    if (editingLocation.value) {
      await storageLocationService.update({
        id: editingLocation.value.id, code: f.code, aisle: f.aisle, rack: f.rack,
        level: f.level, position: f.position, category_id: f.category_id, capacity: f.capacity, is_active: f.is_active ? 1 : 0,
      })
      toast.success('Ubicación actualizada')
    } else {
      await storageLocationService.create({
        warehouse_id: f.warehouse_id, code: f.code, aisle: f.aisle, rack: f.rack,
        level: f.level, position: f.position, category_id: f.category_id, capacity: f.capacity, is_active: f.is_active ? 1 : 0,
      })
      toast.success('Ubicación creada')
    }
    showLocationModal.value = false
    await loadLocations()
  } catch { toast.error('Error al guardar ubicación (revisá los campos o el código duplicado)') }
  finally { saving.value = false }
}

// Generate modal
const showGenerateModal = ref(false)
const generateForm = ref({ warehouse_id: null as number | null, aisle: 'A', rack: '01', levels: 2, positions: 6, capacity: 1, category_id: null as number | null })

function openGenerate() {
  generateForm.value = { warehouse_id: locationFilter.value, aisle: 'A', rack: '01', levels: 2, positions: 4, capacity: 1, category_id: null }
  showGenerateModal.value = true
}

async function saveGenerate() {
  const f = generateForm.value
  if (!f.warehouse_id) { toast.error('Seleccioná una bodega'); return }
  saving.value = true
  try {
    const res = await storageLocationService.generate({
      warehouse_id: f.warehouse_id, aisle: f.aisle, rack: f.rack,
      levels: f.levels, positions: f.positions, capacity: f.capacity, category_id: f.category_id,
    })
    toast.success(`Se generaron ${res.data.data?.created ?? 0} ubicaciones`)
    showGenerateModal.value = false
    await Promise.all([loadLocations(), loadWarehouses()])
  } catch { toast.error('Error al generar ubicaciones') }
  finally { saving.value = false }
}

// ============================================================
// Shared save/delete state
// ============================================================
const showDelete = ref(false)
const deleting = ref<{ id: number; label: string; kind: 'warehouse' | 'category' | 'location' } | null>(null)

function openDelete(kind: 'warehouse' | 'category' | 'location', id: number, label: string) {
  deleting.value = { id, label, kind }
  showDelete.value = true
}

async function confirmDelete() {
  if (!deleting.value) return
  const d = deleting.value
  try {
    if (d.kind === 'warehouse') await warehouseService.delete(d.id)
    else if (d.kind === 'category') await storageCategoryService.delete(d.id)
    else await storageLocationService.delete(d.id)
    toast.success('Eliminado')
    showDelete.value = false
    if (d.kind === 'warehouse') await loadWarehouses()
    else if (d.kind === 'category') await loadCategories()
    else await loadLocations()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'No se pudo eliminar (puede estar en uso)')
  } finally {
    deleting.value = null
  }
}

const warehouseOptions = computed(() => warehouses.value.map(w => ({ value: w.id, label: `${w.name} (${w.code})` })))
const categoryOptions = computed(() => [{ value: null as number | null, label: '— Sin clasificación —' }, ...categories.value.map(c => ({ value: c.id, label: c.name }))])

onMounted(async () => {
  await Promise.all([loadWarehouses(), loadCategories()])
  await loadLocations()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Page header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Almacenaje</h1>
        <p class="mt-1 text-sm text-[#64748b]">Bodegas, clasificaciones y ubicaciones físicas</p>
      </div>
      <div class="flex gap-2 sm:w-auto w-full">
        <template v-if="activeTab === 'warehouses'">
          <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreateWarehouse">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Crear bodega
          </button>
        </template>
        <template v-else-if="activeTab === 'categories'">
          <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreateCategory">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Crear clasificación
          </button>
        </template>
        <template v-else>
          <button class="admin-btn admin-btn-ghost sm:w-auto w-full" @click="openCreateLocation">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Nueva ubicación
          </button>
          <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openGenerate">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            Generar ubicaciones
          </button>
        </template>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 rounded-xl bg-[#0a1022] p-1 border border-[#1e293b] w-fit">
      <button :class="['rounded-lg px-4 py-2 text-sm font-medium transition-all', activeTab === 'warehouses' ? 'bg-[#69dca4]/15 text-[#69dca4]' : 'text-[#64748b] hover:text-[#94a3b8]']" @click="activeTab = 'warehouses'">Bodegas</button>
      <button :class="['rounded-lg px-4 py-2 text-sm font-medium transition-all', activeTab === 'categories' ? 'bg-[#69dca4]/15 text-[#69dca4]' : 'text-[#64748b] hover:text-[#94a3b8]']" @click="activeTab = 'categories'">Clasificaciones</button>
      <button :class="['rounded-lg px-4 py-2 text-sm font-medium transition-all', activeTab === 'locations' ? 'bg-[#69dca4]/15 text-[#69dca4]' : 'text-[#64748b] hover:text-[#94a3b8]']" @click="activeTab = 'locations'">Ubicaciones</button>
    </div>

    <!-- TAB 1: WAREHOUSES -->
    <div v-show="activeTab === 'warehouses'" class="admin-card">
      <AppTable :columns="warehouseColumns" :loading="warehousesLoading" :is-empty="warehouses.length === 0" empty-title="Sin bodegas" empty-desc="Creá la primera bodega.">
        <tr v-for="w in warehouses" :key="w.id">
          <td class="font-medium text-[#dae2fd]">{{ w.name }}</td>
          <td class="text-[#94a3b8] font-mono text-xs">{{ w.code }}</td>
          <td>
            <span :class="w.origin === 'online' ? 'admin-badge admin-badge-info' : 'admin-badge admin-badge-success'">
              {{ w.origin === 'online' ? 'Online (Miami)' : 'Local (Nicaragua)' }}
            </span>
          </td>
          <td class="text-[#94a3b8]">{{ w.location_count }}</td>
          <td class="text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEditWarehouse(w)" title="Editar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete('warehouse', w.id, w.name)" title="Eliminar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </div>
          </td>
        </tr>
      </AppTable>
    </div>

    <!-- TAB 2: CATEGORIES -->
    <div v-show="activeTab === 'categories'" class="admin-card">
      <AppTable :columns="categoryColumns" :loading="categoriesLoading" :is-empty="categories.length === 0" empty-title="Sin clasificaciones" empty-desc="Creá la primera clasificación.">
        <tr v-for="c in categories" :key="c.id">
          <td class="font-medium text-[#dae2fd]">{{ c.name }}</td>
          <td><span class="inline-block h-5 w-5 rounded-full border border-[#1e293b]" :style="{ backgroundColor: c.color || '#3b82f6' }" /></td>
          <td class="text-[#94a3b8]">{{ c.description || '—' }}</td>
          <td class="text-[#94a3b8]">{{ c.package_count ?? 0 }}</td>
          <td class="text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEditCategory(c)" title="Editar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete('category', c.id, c.name)" title="Eliminar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </div>
          </td>
        </tr>
      </AppTable>
    </div>

    <!-- TAB 3: LOCATIONS -->
    <div v-show="activeTab === 'locations'" class="space-y-4">
      <div class="admin-card p-4 max-w-sm">
        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#64748b]">Filtrar por bodega</label>
        <AppDropdown
          :model-value="locationFilter"
          @update:model-value="(v: string | number | null) => { locationFilter = v as number | null; loadLocations() }"
          :options="[{ value: null, label: 'Todas las bodegas' }, ...warehouseOptions]"
          placeholder="Todas las bodegas"
        />
      </div>
      <div class="admin-card">
        <AppTable :columns="locationColumns" :loading="locationsLoading" :is-empty="locations.length === 0" empty-title="Sin ubicaciones" empty-desc="Generá o creá ubicaciones para tus bodegas.">
          <tr v-for="l in locations" :key="l.id">
            <td class="font-mono text-xs font-medium text-[#dae2fd]">{{ l.code }}</td>
            <td class="text-[#94a3b8]">{{ l.warehouse_name }}</td>
            <td>
              <span v-if="l.category_name" class="inline-flex items-center gap-1.5 text-[#94a3b8]">
                <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: l.category_color || '#3b82f6' }" />
                {{ l.category_name }}
              </span>
              <span v-else class="text-[#64748b]">—</span>
            </td>
            <td class="text-[#94a3b8]">{{ l.occupied }} / {{ l.capacity }}</td>
            <td>
              <span :class="l.is_active ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-neutral'">{{ l.is_active ? 'Activa' : 'Inactiva' }}</span>
            </td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEditLocation(l)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete('location', l.id, l.code)" title="Eliminar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </AppTable>
      </div>
    </div>

    <!-- Warehouse modal -->
    <AppModal :visible="showWarehouseModal" :title="editingWarehouse ? 'Editar bodega' : 'Crear bodega'" @close="showWarehouseModal = false">
      <form class="space-y-4" @submit.prevent="saveWarehouse">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Nombre</label>
          <input v-model="warehouseForm.name" class="admin-input" placeholder="Almacén Miami" :disabled="saving" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Código</label>
          <input v-model="warehouseForm.code" class="admin-input font-mono uppercase" placeholder="MIAMI" :disabled="saving" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Origen</label>
          <AppDropdown
            :model-value="warehouseForm.origin"
            @update:model-value="(v: string | number | null) => warehouseForm.origin = (v as 'online' | 'local')"
            :options="[{ value: 'online', label: 'Online (Miami)' }, { value: 'local', label: 'Local (Nicaragua)' }]"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Dirección</label>
          <input v-model="warehouseForm.address" class="admin-input" placeholder="Opcional" :disabled="saving" />
        </div>
        <div class="flex items-center justify-between rounded-lg border border-[#1e293b] bg-[#0a1022]/50 px-4 py-3">
          <span class="text-sm text-[#94a3b8]">Activa</span>
          <AppSwitch v-model="warehouseForm.is_active" :disabled="saving" />
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showWarehouseModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="saveWarehouse" :disabled="saving || !warehouseForm.name || !warehouseForm.code">
            {{ saving ? 'Guardando...' : (editingWarehouse ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Category modal -->
    <AppModal :visible="showCategoryModal" :title="editingCategory ? 'Editar clasificación' : 'Crear clasificación'" @close="showCategoryModal = false">
      <form class="space-y-4" @submit.prevent="saveCategory">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Nombre</label>
          <input v-model="categoryForm.name" class="admin-input" placeholder="Frágil" :disabled="saving" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Color</label>
          <div class="flex items-center gap-3">
            <input v-model="categoryForm.color" type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-[#1e293b] bg-[#0a1022]" :disabled="saving" />
            <input v-model="categoryForm.color" class="admin-input font-mono" placeholder="#ef4444" :disabled="saving" />
          </div>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Descripción</label>
          <input v-model="categoryForm.description" class="admin-input" placeholder="Opcional" :disabled="saving" />
        </div>
        <div class="flex items-center justify-between rounded-lg border border-[#1e293b] bg-[#0a1022]/50 px-4 py-3">
          <span class="text-sm text-[#94a3b8]">Activa</span>
          <AppSwitch v-model="categoryForm.is_active" :disabled="saving" />
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showCategoryModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="saveCategory" :disabled="saving || !categoryForm.name">
            {{ saving ? 'Guardando...' : (editingCategory ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Location modal -->
    <AppModal :visible="showLocationModal" :title="editingLocation ? 'Editar ubicación' : 'Nueva ubicación'" @close="showLocationModal = false">
      <form class="space-y-4" @submit.prevent="saveLocation">
        <div v-if="!editingLocation">
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Bodega</label>
          <AppDropdown :model-value="locationForm.warehouse_id" @update:model-value="(v: string | number | null) => locationForm.warehouse_id = v as number | null" :options="warehouseOptions" placeholder="Seleccionar bodega..." />
        </div>
        <div v-if="editingLocation">
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Código</label>
          <input v-model="locationForm.code" class="admin-input font-mono uppercase" :disabled="saving" />
        </div>
        <template v-else>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div><label class="mb-1.5 block text-xs text-[#64748b]">Pasillo</label><input v-model="locationForm.aisle" class="admin-input font-mono uppercase" placeholder="A" :disabled="saving" /></div>
            <div><label class="mb-1.5 block text-xs text-[#64748b]">Estante</label><input v-model="locationForm.rack" class="admin-input font-mono uppercase" placeholder="01" :disabled="saving" /></div>
            <div><label class="mb-1.5 block text-xs text-[#64748b]">Nivel</label><input v-model="locationForm.level" class="admin-input font-mono" placeholder="1" :disabled="saving" /></div>
            <div><label class="mb-1.5 block text-xs text-[#64748b]">Posición</label><input v-model="locationForm.position" class="admin-input font-mono" placeholder="1" :disabled="saving" /></div>
          </div>
          <p class="text-xs text-[#64748b]">Dejá los campos vacíos y escribí un código manual abajo si preferís.</p>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Código manual (opcional)</label>
            <input v-model="locationForm.code" class="admin-input font-mono uppercase" placeholder="A-01-01-01" :disabled="saving" />
          </div>
        </template>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Clasificación</label>
            <AppDropdown :model-value="locationForm.category_id" @update:model-value="(v: string | number | null) => locationForm.category_id = v as number | null" :options="categoryOptions" placeholder="— Sin clasificación —" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Capacidad</label>
            <input v-model.number="locationForm.capacity" type="number" min="1" class="admin-input" :disabled="saving" />
          </div>
        </div>
        <div class="flex items-center justify-between rounded-lg border border-[#1e293b] bg-[#0a1022]/50 px-4 py-3">
          <span class="text-sm text-[#94a3b8]">Activa</span>
          <AppSwitch v-model="locationForm.is_active" :disabled="saving" />
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showLocationModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="saveLocation" :disabled="saving || (!editingLocation && !locationForm.warehouse_id)">
            {{ saving ? 'Guardando...' : (editingLocation ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Generate modal -->
    <AppModal :visible="showGenerateModal" title="Generar ubicaciones" @close="showGenerateModal = false">
      <form class="space-y-4" @submit.prevent="saveGenerate">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Bodega</label>
          <AppDropdown :model-value="generateForm.warehouse_id" @update:model-value="(v: string | number | null) => generateForm.warehouse_id = v as number | null" :options="warehouseOptions" placeholder="Seleccionar bodega..." />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Pasillo</label><input v-model="generateForm.aisle" class="admin-input font-mono uppercase" placeholder="A" :disabled="saving" /></div>
          <div><label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Estante</label><input v-model="generateForm.rack" class="admin-input font-mono uppercase" placeholder="01" :disabled="saving" /></div>
          <div><label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Niveles</label><input v-model.number="generateForm.levels" type="number" min="1" max="50" class="admin-input" :disabled="saving" /></div>
          <div><label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Posiciones</label><input v-model.number="generateForm.positions" type="number" min="1" max="100" class="admin-input" :disabled="saving" /></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Clasificación</label>
            <AppDropdown :model-value="generateForm.category_id" @update:model-value="(v: string | number | null) => generateForm.category_id = v as number | null" :options="categoryOptions" placeholder="— Sin clasificación —" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Capacidad</label>
            <input v-model.number="generateForm.capacity" type="number" min="1" class="admin-input" :disabled="saving" />
          </div>
        </div>
        <p class="text-xs text-[#64748b]">Se crearán {{ (generateForm.levels || 0) * (generateForm.positions || 0) }} ubicaciones con códigos únicos (ej: {{ generateForm.aisle || 'A' }}-{{ generateForm.rack || '01' }}-01-01).</p>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showGenerateModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="saveGenerate" :disabled="saving || !generateForm.warehouse_id">
            {{ saving ? 'Generando...' : 'Generar' }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Delete confirmation -->
    <AppConfirmDialog
      :visible="showDelete"
      title="Confirmar eliminación"
      :message="`Seguro que querés eliminar ${deleting?.label}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
