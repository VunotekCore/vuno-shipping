<script setup lang="ts">
import { onMounted, ref, reactive, computed } from 'vue'
import { empleadoService, type Empleado } from '../services/empleadoService'
import { puestoService, type Puesto } from '../services/puestoService'
import { userService, type User } from '../services/userService'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import AppSwitch from '../components/ui/AppSwitch.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const puestos = ref<Pick<Puesto, 'id' | 'name' | 'area_name'>[]>([])
const usuarios = ref<Pick<User, 'id' | 'name' | 'email'>[]>([])

const {
  items: empleados, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load: loadCrud, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<Empleado>({
  service: empleadoService,
  dataKey: 'empleados',
  entityName: 'Empleado',
  getDefaultForm: () => ({
    name: '', email: '', phone: '', cedula: '', fecha_ingreso: '',
    puesto_id: puestos.value[0]?.id ?? 0, user_id: null, is_active: true,
  }),
  populateForm: (e) => ({
    name: e.name,
    email: e.email || '',
    phone: e.phone || '',
    cedula: e.cedula || '',
    fecha_ingreso: e.fecha_ingreso || '',
    puesto_id: e.puesto_id,
    user_id: e.user_id,
    is_active: !!e.is_active,
  }),
  validateForm: (f) => {
    if (!f.name) return 'El nombre es requerido'
    if (!f.puesto_id || f.puesto_id === 0) return 'El puesto es requerido'
    return null
  },
  getEntityId: (e) => e.id,
})

const fieldErrors = reactive<Record<string, string>>({
  name: '', cedula: '', phone: '', email: '', puesto_id: '',
})

function clearError(key: string) { fieldErrors[key] = '' }

function validateField(key: string) {
  const v = form.value[key]
  switch (key) {
    case 'name':
      if (!String(v ?? '').trim()) { fieldErrors.name = 'El nombre es requerido'; return }
      if (String(v).length > 100) { fieldErrors.name = 'Máximo 100 caracteres'; return }
      fieldErrors.name = ''
      break
    case 'cedula':
      if (String(v ?? '').length > 30) { fieldErrors.cedula = 'Máximo 30 caracteres'; return }
      fieldErrors.cedula = ''
      break
    case 'phone':
      if (String(v ?? '').length > 30) { fieldErrors.phone = 'Máximo 30 caracteres'; return }
      fieldErrors.phone = ''
      break
    case 'email':
      if (String(v ?? '') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v))) { fieldErrors.email = 'Email inválido'; return }
      fieldErrors.email = ''
      break
    case 'puesto_id':
      if (!v || v === 0) { fieldErrors.puesto_id = 'Seleccioná un puesto'; return }
      fieldErrors.puesto_id = ''
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

const puestoOptions = computed(() =>
  puestos.value.map((p) => ({ value: p.id, label: `${p.name} (${p.area_name})` }))
)

const userOptions = computed(() => {
  const assignedIds = empleados.value
    .filter((e) => e.user_id && (!editing.value || e.id !== editing.value.id))
    .map((e) => e.user_id)
  const available = usuarios.value.filter((u) => !assignedIds.includes(u.id))
  return [{ value: null, label: 'Sin usuario asignado' }, ...available.map((u) => ({ value: u.id, label: `${u.name} (${u.email})` }))]
})

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'cedula', label: 'Cédula', width: '120px' },
  { key: 'puesto_name', label: 'Puesto', width: '150px' },
  { key: 'phone', label: 'Teléfono', width: '130px' },
  { key: 'user_name', label: 'Usuario', width: '140px' },
  { key: 'is_active', label: 'Estado', width: '80px', align: 'center' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

async function load() {
  const [, puestoRes, userRes] = await Promise.all([
    loadCrud(),
    puestoService.listAll().catch(() => ({ data: { data: { puestos: [] as Pick<Puesto, 'id' | 'name' | 'area_name'>[] } } })),
    userService.list().catch(() => ({ data: { data: { users: [] as Pick<User, 'id' | 'name' | 'email'>[] } } })),
  ])
  puestos.value = puestoRes.data.data?.puestos ?? []
  usuarios.value = userRes.data.data?.users ?? []
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Empleados</h1>
        <p class="mt-1 text-sm text-[#64748b]">Gestionar empleados y su acceso al sistema</p>
      </div>
      <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear empleado
      </button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="empleados.length === 0" empty-title="Sin empleados" empty-desc="Creá el primer empleado.">
          <tr v-for="e in empleados" :key="e.id">
            <td><div><p class="font-medium text-[#dae2fd]">{{ e.name }}</p><p v-if="e.email" class="text-xs text-[#64748b]">{{ e.email }}</p></div></td>
            <td class="text-[#94a3b8]">{{ e.cedula || '—' }}</td>
            <td><span class="admin-badge admin-badge-info">{{ e.puesto_name }}</span></td>
            <td class="text-[#94a3b8]">{{ e.phone || '—' }}</td>
            <td class="text-[#94a3b8]">{{ e.user_name || '—' }}</td>
            <td class="text-center">
              <span :class="e.is_active ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-danger'">{{ e.is_active ? 'Activo' : 'Inactivo' }}</span>
            </td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(e)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(e)" title="Eliminar">
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
          <div class="flex items-center justify-between"><div class="h-4 w-1/3 animate-pulse rounded bg-[#1e293b]" /><div class="h-5 w-16 animate-pulse rounded-full bg-[#1e293b]" /></div>
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-[#1e293b]" />
          <div class="border-t border-[#1e293b] pt-3"><div class="flex gap-2"><div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" /><div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" /></div></div>
        </div>
      </template>
      <template v-else-if="empleados.length > 0">
        <div v-for="e in empleados" :key="e.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-[#dae2fd] truncate">{{ e.name }}</p>
            <span :class="e.is_active ? 'admin-badge admin-badge-success shrink-0' : 'admin-badge admin-badge-danger shrink-0'">{{ e.is_active ? 'Activo' : 'Inactivo' }}</span>
          </div>
          <p class="text-sm text-[#94a3b8]">{{ e.cedula || '—' }} · {{ e.puesto_name }} · {{ e.phone || '—' }}</p>
          <div class="border-t border-[#1e293b] pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(e)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(e)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              Eliminar
            </button>
          </div>
        </div>
      </template>
      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 00-3-3.87" /><path d="M16 3.13a4 4 0 010 7.75" /></svg>
          <p class="admin-empty-state-title">Sin empleados</p>
          <p class="admin-empty-state-desc">Creá el primer empleado.</p>
        </div>
      </div>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar empleado' : 'Crear empleado'" @close="showModal = false" size="md">
      <form class="space-y-5" @submit.prevent="handleSave" novalidate>
        <!-- Sección: Datos personales -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Datos personales
          </div>
          <div class="mt-3 space-y-4">
            <AppFormField
              :model-value="fv('name')"
              @update:model-value="(v: string | number | null) => form.name = v"
              label="Nombre"
              icon="user"
              placeholder="Nombre completo"
              :required="true"
              :disabled="saving"
              :maxlength="100"
              :error="fieldErrors.name"
              @blur="validateField('name')"
              @input="clearError('name')"
            />
            <div class="grid grid-cols-2 gap-4">
              <AppFormField
                :model-value="fv('cedula')"
                @update:model-value="(v: string | number | null) => form.cedula = v"
                label="Cédula"
                icon="credit-card"
                placeholder="000-000000-0000A"
                :disabled="saving"
                :maxlength="30"
                :error="fieldErrors.cedula"
                @blur="validateField('cedula')"
                @input="clearError('cedula')"
              />
              <AppPhoneInput
                :model-value="fv('phone')"
                @update:model-value="(v: string) => { form.phone = v; clearError('phone') }"
                label="Teléfono"
                placeholder="1234-5678"
                :disabled="saving"
                :error="fieldErrors.phone"
                @blur="validateField('phone')"
              />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <AppFormField
                :model-value="fv('email')"
                @update:model-value="(v: string | number | null) => form.email = v"
                label="Email"
                type="email"
                icon="mail"
                placeholder="correo@ejemplo.com"
                :disabled="saving"
                :error="fieldErrors.email"
                @blur="validateField('email')"
                @input="clearError('email')"
              />
              <AppFormField
                :model-value="fv('fecha_ingreso')"
                @update:model-value="(v: string | number | null) => form.fecha_ingreso = v"
                label="Fecha de ingreso"
                type="date"
                icon="calendar"
                :disabled="saving"
              />
            </div>
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Asignación -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
            Asignación
          </div>
          <div class="mt-3 space-y-4">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Puesto <span class="text-[#DC2626]">*</span></label>
              <AppDropdown
                :model-value="fv('puesto_id')"
                @update:model-value="(v: string | number | null) => { form.puesto_id = v; clearError('puesto_id') }"
                :options="puestoOptions"
                placeholder="Seleccionar puesto"
                :disabled="saving"
                :error="fieldErrors.puesto_id"
              />
              <p v-if="fieldErrors.puesto_id" class="mt-1.5 text-xs text-[#DC2626]">{{ fieldErrors.puesto_id }}</p>
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Usuario de acceso</label>
              <AppDropdown
                :model-value="fv('user_id')"
                @update:model-value="(v: string | number | null) => form.user_id = v"
                :options="userOptions"
                placeholder="Sin usuario asignado"
                :disabled="saving"
              />
              <p class="mt-1.5 text-xs text-[#64748b]">Opcional — Asigná un usuario para que pueda acceder al sistema</p>
            </div>
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Estado -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Estado
          </div>
          <div class="mt-3 flex items-center justify-between rounded-lg border border-[#1e293b] bg-[#0a1022]/50 px-4 py-3">
            <div>
              <p class="text-sm font-medium text-[#dae2fd]">Activo</p>
              <p class="text-xs text-[#64748b]">Los empleados inactivos no aparecen en asignaciones</p>
            </div>
            <AppSwitch :model-value="!!form.is_active" @update:model-value="(v: boolean) => { form.is_active = v }" :disabled="saving" />
          </div>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving || !form.name || !form.puesto_id || hasErrors" @click="handleSave">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar empleado"
      :message="`¿Seguro que querés eliminar a ${deleting?.name}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
