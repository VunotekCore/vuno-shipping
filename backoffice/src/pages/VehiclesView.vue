<script setup lang="ts">
import { onMounted, reactive, computed } from 'vue'
import { vehicleService, type Vehicle } from '../services/vehicleService'
import { statusLabel } from '../utils/format'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'

const fuelOptions = [
  { value: 'gasolina', label: 'Gasolina' },
  { value: 'diesel', label: 'Diésel' },
  { value: 'electrico', label: 'Eléctrico' },
  { value: 'hibrido', label: 'Híbrido' },
  { value: 'otro', label: 'Otro' },
]

const statusOptions = [
  { value: 'disponible', label: 'Disponible' },
  { value: 'en_ruta', label: 'En ruta' },
  { value: 'en_espera', label: 'En espera' },
  { value: 'en_mantenimiento', label: 'En mantenimiento' },
  { value: 'en_reparacion', label: 'En reparación' },
  { value: 'fuera_de_servicio', label: 'Fuera de servicio' },
  { value: 'accidentado', label: 'Accidentado' },
  { value: 'dado_de_baja', label: 'Dado de baja' },
]

const {
  items: vehicles, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<Vehicle>({
  service: vehicleService,
  dataKey: 'vehicles',
  entityName: 'Vehículo',
  getDefaultForm: () => ({ plate: '', brand: '', model: '', year: null, color: '', fuel_type: '', capacity_kg: null, capacity_m3: null, current_km: 0, status: 'disponible', notes: '' }),
  populateForm: (v) => ({ plate: v.plate, brand: v.brand ?? '', model: v.model ?? '', year: v.year, color: v.color ?? '', fuel_type: v.fuel_type ?? '', capacity_kg: v.capacity_kg, capacity_m3: v.capacity_m3, current_km: v.current_km ?? 0, status: v.status ?? 'disponible', notes: v.notes ?? '' }),
  validateForm: (f) => {
    if (!f.plate) return 'La placa es requerida'
    return null
  },
  getEntityId: (v) => v.id,
})

const fieldErrors = reactive<Record<string, string>>({
  plate: '', brand: '', model: '', color: '', fuel_type: '', notes: '',
})

function clearError(key: string) {
  fieldErrors[key] = ''
}

function validateField(key: string) {
  const v = String(form.value[key] ?? '')
  switch (key) {
    case 'plate':
      if (!v.trim()) { fieldErrors.plate = 'La placa es requerida'; return }
      if (v.length > 20) { fieldErrors.plate = 'Máximo 20 caracteres'; return }
      fieldErrors.plate = ''
      break
    case 'brand':
      if (v.length > 100) { fieldErrors.brand = 'Máximo 100 caracteres'; return }
      fieldErrors.brand = ''
      break
    case 'model':
      if (v.length > 100) { fieldErrors.model = 'Máximo 100 caracteres'; return }
      fieldErrors.model = ''
      break
    case 'color':
      if (v.length > 50) { fieldErrors.color = 'Máximo 50 caracteres'; return }
      fieldErrors.color = ''
      break
    case 'fuel_type':
      if (v.length > 20) { fieldErrors.fuel_type = 'Máximo 20 caracteres'; return }
      fieldErrors.fuel_type = ''
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
  { key: 'plate', label: 'Placa', width: '100px' },
  { key: 'brand', label: 'Marca', width: '120px' },
  { key: 'model', label: 'Modelo', width: '120px' },
  { key: 'year', label: 'Año', width: '80px' },
  { key: 'capacity_kg', label: 'Cap. Kg', width: '90px' },
  { key: 'current_km', label: 'Km', width: '90px' },
  { key: 'status', label: 'Estado', width: '140px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Vehículos</h1>
        <p class="mt-1 text-sm text-[#64748b]">Gestionar vehículos del sistema</p>
      </div>
      <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear vehículo
      </button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="vehicles.length === 0" empty-title="Sin vehículos" empty-desc="Creá el primer vehículo del sistema.">
          <tr v-for="v in vehicles" :key="v.id">
            <td class="font-medium text-[#dae2fd]">{{ v.plate }}</td>
            <td class="text-[#94a3b8]">{{ v.brand }}</td>
            <td class="text-[#94a3b8]">{{ v.model }}</td>
            <td class="text-[#64748b]">{{ v.year ?? '—' }}</td>
            <td class="text-[#64748b]">{{ v.capacity_kg ?? '—' }}</td>
            <td class="text-[#94a3b8]">{{ v.current_km ?? 0 }}</td>
            <td>
              <span class="admin-badge admin-badge-info">{{ statusLabel(v.status || v.status_name) }}</span>
            </td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(v)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(v)" title="Eliminar">
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

      <template v-else-if="vehicles.length > 0">
        <div v-for="v in vehicles" :key="v.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-[#dae2fd] truncate">{{ v.plate }}</p>
            <span class="admin-badge admin-badge-info shrink-0">{{ statusLabel(v.status || v.status_name) }}</span>
          </div>
          <p class="text-sm text-[#94a3b8] truncate">{{ v.brand }} {{ v.model }}</p>
          <p class="text-xs text-[#64748b]">{{ v.year ?? '—' }} · {{ v.capacity_kg ? v.capacity_kg + ' kg' : '—' }}</p>
          <div class="border-t border-[#1e293b] pt-3 flex items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(v)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(v)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              Eliminar
            </button>
          </div>
        </div>
      </template>

      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2" />
            <circle cx="7" cy="17" r="2" />
            <path d="M9 17h6" />
            <circle cx="17" cy="17" r="2" />
          </svg>
          <p class="admin-empty-state-title">Sin vehículos</p>
          <p class="admin-empty-state-desc">Creá el primer vehículo del sistema.</p>
        </div>
      </div>

      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar vehículo' : 'Crear vehículo'" size="lg" @close="showModal = false">
      <form class="space-y-5" @submit.prevent="handleSave" novalidate>
        <!-- Sección: Identificación -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16M4 15h16M10 3l-2 18M16 3l-2 18"/></svg>
            Identificación
          </div>
          <div class="mt-3">
            <AppFormField
              :model-value="fv('plate')"
              @update:model-value="(v: string | number | null) => form.plate = v"
              label="Placa"
              icon="hash"
              placeholder="ej: ABC-123"
              :required="true"
              :disabled="saving"
              :maxlength="20"
              helper="Formato: letras y números, ej: ABC-123"
              :error="fieldErrors.plate"
              @blur="validateField('plate')"
              @input="clearError('plate')"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Información del vehículo -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2M7 17a2 2 0 100-4 2 2 0 000 4M17 17a2 2 0 100-4 2 2 0 000 4z"/></svg>
            Información del vehículo
          </div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('brand')"
              @update:model-value="(v: string | number | null) => form.brand = v"
              label="Marca"
              icon="car"
              placeholder="ej: Toyota, Nissan, Hyundai"
              :disabled="saving"
              :maxlength="100"
              :error="fieldErrors.brand"
              @blur="validateField('brand')"
              @input="clearError('brand')"
            />
            <AppFormField
              :model-value="fv('model')"
              @update:model-value="(v: string | number | null) => form.model = v"
              label="Modelo"
              icon="car"
              placeholder="ej: Hilux, Patrol, Tucson"
              :disabled="saving"
              :maxlength="100"
              :error="fieldErrors.model"
              @blur="validateField('model')"
              @input="clearError('model')"
            />
          </div>
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('year')"
              @update:model-value="(v: string | number | null) => form.year = v"
              label="Año"
              type="number"
              icon="calendar"
              placeholder="ej: 2024"
              :disabled="saving"
              :min="1900"
              :max="2099"
            />
            <AppFormField
              :model-value="fv('color')"
              @update:model-value="(v: string | number | null) => form.color = v"
              label="Color"
              icon="palette"
              placeholder="ej: Blanco, Negro, Rojo"
              :disabled="saving"
              :maxlength="50"
              :error="fieldErrors.color"
              @blur="validateField('color')"
              @input="clearError('color')"
            />
          </div>
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Estado</label>
              <select v-model="form.status" class="admin-input" :disabled="saving">
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>

          </div>
          <div class="mt-4">
            <AppFormField
              :model-value="fv('fuel_type')"
              @update:model-value="(v: string | number | null) => form.fuel_type = v"
              label="Tipo de combustible"
              type="select"
              icon="fuel"
              placeholder="Seleccionar combustible..."
              :options="fuelOptions"
              :disabled="saving"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Capacidad -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M3 7l3 6a4 4 0 006 0l3-6M15 7l3 6a4 4 0 006 0l3-6"/></svg>
            Capacidad
          </div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('capacity_kg')"
              @update:model-value="(v: string | number | null) => form.capacity_kg = v"
              label="Peso máximo"
              type="number"
              icon="scale"
              placeholder="ej: 2500"
              helper="Capacidad en kilogramos"
              :disabled="saving"
              :min="0"
              :step="0.01"
            />
            <AppFormField
              :model-value="fv('capacity_m3')"
              @update:model-value="(v: string | number | null) => form.capacity_m3 = v"
              label="Volumen máximo"
              type="number"
              icon="box"
              placeholder="ej: 8.5"
              helper="Capacidad en metros cúbicos"
              :disabled="saving"
              :min="0"
              :step="0.01"
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
              placeholder="Observaciones adicionales sobre el vehículo..."
              :disabled="saving"
              :maxlength="500"
              :rows="3"
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
            :disabled="saving || !form.plate || hasErrors"
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
      title="Eliminar vehículo"
      :message="`¿Seguro que querés eliminar el vehículo ${deleting?.plate}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
