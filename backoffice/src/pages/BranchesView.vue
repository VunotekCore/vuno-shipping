<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { branchService } from '../services/branchService'
import { warehouseService } from '../services/warehouseService'
import type { Branch } from '../types'
import type { Warehouse } from '../types'
import AppModal from '../components/ui/AppModal.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppTable from '../components/ui/AppTable.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import AppSwitch from '../components/ui/AppSwitch.vue'
import { useToast } from '../composables/useToast'

const toast = useToast()

const branches = ref<Branch[]>([])
const warehouses = ref<Warehouse[]>([])
const loading = ref(false)
const saving = ref(false)

const showModal = ref(false)
const showDelete = ref(false)
const editing = ref<Branch | null>(null)
const deleting = ref<Branch | null>(null)

const form = ref({
  code: '',
  name: '',
  country_code: 'NI',
  type: 'direct' as 'consolidation' | 'direct',
  receiving_warehouse_id: null as number | null,
  transfer_to_warehouse_id: null as number | null,
  allows_pickup: false,
  is_active: true,
  sort_order: 0,
})

const columns = [
  { key: 'code', label: 'Código', width: '100px' },
  { key: 'name', label: 'Sucursal' },
  { key: 'type', label: 'Tipo', width: '140px' },
  { key: 'receiving', label: 'Bodega receptora', width: '200px' },
  { key: 'transfer', label: 'Trasladar a', width: '200px' },
  { key: 'pickup', label: 'Permite retiro', width: '130px', align: 'center' as const },
  { key: 'status', label: 'Estado', width: '110px', align: 'center' as const },
  { key: 'actions', label: '', width: '110px', align: 'right' as const },
]

const warehouseOptions = computed(() => [
  { value: null, label: 'Sin bodega' },
  ...warehouses.value.map(w => ({ value: w.id, label: `${w.code} - ${w.name}` }))
])

async function load() {
  loading.value = true
  try {
    const [bRes, wRes] = await Promise.all([
      branchService.list(false),
      warehouseService.list().catch(() => ({ data: { data: { warehouses: [] } } } as any))
    ])
    branches.value = bRes.data?.data?.branches || []
    const wData: any = wRes.data
    warehouses.value = wData?.data?.warehouses || wData?.warehouses || []
  } catch {
    toast.error('Error al cargar sucursales')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = {
    code: '',
    name: '',
    country_code: 'NI',
    type: 'direct',
    receiving_warehouse_id: null,
    transfer_to_warehouse_id: null,
    allows_pickup: false,
    is_active: true,
    sort_order: 0,
  }
  showModal.value = true
}

function openEdit(branch: Branch) {
  editing.value = branch
  form.value = {
    code: branch.code,
    name: branch.name,
    country_code: branch.country_code || 'NI',
    type: branch.type,
    receiving_warehouse_id: branch.receiving_warehouse_id,
    transfer_to_warehouse_id: branch.transfer_to_warehouse_id,
    allows_pickup: !!branch.allows_pickup,
    is_active: !!branch.is_active,
    sort_order: branch.sort_order || 0,
  }
  showModal.value = true
}

function openDelete(branch: Branch) {
  deleting.value = branch
  showDelete.value = true
}

async function save() {
  if (!form.value.code || !form.value.name) {
    toast.error('Código y nombre son requeridos')
    return
  }
  saving.value = true
  try {
    if (editing.value) {
      await branchService.update({ id: editing.value.id, ...form.value })
      toast.success('Sucursal actualizada')
    } else {
      await branchService.create(form.value)
      toast.success('Sucursal creada')
    }
    showModal.value = false
    await load()
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Error al guardar sucursal')
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!deleting.value) return
  saving.value = true
  try {
    await branchService.delete(deleting.value.id)
    toast.success('Sucursal eliminada')
    showDelete.value = false
    await load()
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Error al eliminar sucursal')
  } finally {
    saving.value = false
  }
}

function getWarehouseName(id: number | null) {
  if (!id) return '—'
  const w = warehouses.value.find(x => x.id === id)
  return w ? `${w.code} - ${w.name}` : `#${id}`
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-content">Sucursales</h1>
        <p class="mt-1 text-sm text-subtle">Puntos de ingreso, traslados a bodega central y retiros en sucursal</p>
      </div>
      <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
        <svg class="-ml-1 mr-1.5 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Nueva sucursal
      </button>
    </div>

    <div class="admin-card">
      <AppTable :columns="columns" :loading="loading" :is-empty="branches.length === 0" empty-title="Sin sucursales" empty-desc="Crea la primera sucursal para habilitarla en admisión.">
        <tr v-for="b in branches" :key="b.id">
          <td class="font-mono text-xs text-muted">{{ b.code }}</td>
          <td class="font-medium text-content">{{ b.name }}</td>
          <td>
            <span class="admin-badge" :class="b.type === 'consolidation' ? 'admin-badge-info' : 'admin-badge-success'">
              {{ b.type === 'consolidation' ? 'Consolidación' : 'Ingreso local' }}
            </span>
          </td>
          <td class="text-sm text-subtle">{{ getWarehouseName(b.receiving_warehouse_id) }}</td>
          <td class="text-sm text-subtle">{{ getWarehouseName(b.transfer_to_warehouse_id) }}</td>
          <td class="text-center">
            <span class="admin-badge" :class="b.allows_pickup ? 'admin-badge-success' : 'admin-badge-neutral'">
              {{ b.allows_pickup ? 'Sí' : 'No' }}
            </span>
          </td>
          <td class="text-center">
            <span class="admin-badge" :class="b.is_active ? 'admin-badge-success' : 'admin-badge-neutral'">
              {{ b.is_active ? 'Activa' : 'Inactiva' }}
            </span>
          </td>
          <td class="text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(b)">Editar</button>
              <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(b)">Eliminar</button>
            </div>
          </td>
        </tr>
      </AppTable>
    </div>

    <AppModal :visible="showModal" :title="editing ? 'Editar sucursal' : 'Nueva sucursal'" size="lg" @close="showModal = false">
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">Código *</label>
          <input v-model="form.code" class="admin-input" placeholder="LEON, MIA, MGA" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">Nombre *</label>
          <input v-model="form.name" class="admin-input" placeholder="Sucursal León" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">Tipo de flujo *</label>
          <AppDropdown :model-value="form.type" @update:model-value="(v: string | number | null) => form.type = v as any" :options="[{value:'direct',label:'Ingreso local'}, {value:'consolidation',label:'Consolidación (fuera de Nicaragua)'}]" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">País</label>
          <input v-model="form.country_code" class="admin-input" placeholder="NI" maxlength="2" />
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1.5 block text-sm font-medium text-content">Bodega receptora *</label>
          <AppDropdown :model-value="form.receiving_warehouse_id" @update:model-value="(v: string | number | null) => form.receiving_warehouse_id = v as number | null" :options="warehouseOptions" />
          <p class="mt-1 text-xs text-subtle">Bodega física donde se asigna el paquete al admitirlo.</p>
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1.5 block text-sm font-medium text-content">Trasladar a bodega central</label>
          <AppDropdown :model-value="form.transfer_to_warehouse_id" @update:model-value="(v: string | number | null) => form.transfer_to_warehouse_id = v as number | null" :options="warehouseOptions" />
          <p class="mt-1 text-xs text-subtle">Para sucursales locales que reciben y deben trasladarse a bodega central para despacho. Dejar vacío si se despacha desde esta misma bodega.</p>
        </div>
        <div class="flex items-center justify-between rounded-lg border border-line p-3 sm:col-span-2">
          <div>
            <p class="text-sm font-medium text-content">Permite retiro en sucursal</p>
            <p class="text-xs text-subtle">Aparecerá en el selector "Retirar en" al elegir entrega en sucursal</p>
          </div>
          <AppSwitch :model-value="form.allows_pickup" @update:model-value="(v: boolean) => form.allows_pickup = v" />
        </div>
        <div class="flex items-center justify-between rounded-lg border border-line p-3 sm:col-span-2">
          <div>
            <p class="text-sm font-medium text-content">Activa</p>
            <p class="text-xs text-subtle">Visible en admisión</p>
          </div>
          <AppSwitch :model-value="form.is_active" @update:model-value="(v: boolean) => form.is_active = v" />
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving" @click="save">{{ saving ? 'Guardando...' : 'Guardar' }}</button>
        </div>
      </template>
    </AppModal>

    <AppConfirmDialog :visible="showDelete" title="Eliminar sucursal" :message="`¿Eliminar ${deleting?.name}?`" confirm-text="Eliminar" variant="danger" @confirm="confirmDelete" @cancel="showDelete = false" />
  </div>
</template>
