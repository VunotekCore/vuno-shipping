<script setup lang="ts">
import { onMounted, reactive, computed, ref } from 'vue'
import { clientService, type Client } from '../services/clientService'
import { zoneService, type Zone } from '../services/zoneService'
import { userService, type User } from '../services/userService'
import { useAuthStore } from '../stores/auth'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import AppPhoneInput from '../components/ui/AppPhoneInput.vue'
import AppRucInput from '../components/ui/AppRucInput.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import { classifyNicaraguanId } from '../utils/validation'
import PageHeader from '../components/ui/PageHeader.vue'

const auth = useAuthStore()
const isVendor = computed(() => auth.user?.role_slug === 'vendedor')

const {
  items: clients, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<Client>({
  service: clientService,
  dataKey: 'clients',
  entityName: 'Cliente',
  getDefaultForm: () => ({ name: '', email: '', phone: '', ruc: '', address: '', department: '', notes: '', agent_id: null }),
  populateForm: (c) => ({ name: c.name, email: c.email ?? '', phone: c.phone ?? '', ruc: c.ruc ?? '', address: c.address ?? '', department: c.department ?? '', notes: c.notes ?? '', agent_id: c.agent_id ?? null }),
  validateForm: (f) => {
    if (!f.name) return 'El nombre es requerido'
    return null
  },
  getEntityId: (c) => c.id,
})

const zones = ref<Zone[]>([])
const agents = ref<User[]>([])

const fieldErrors = reactive<Record<string, string>>({
  name: '', email: '', phone: '', ruc: '', address: '', department: '', notes: '',
})

function clearError(key: string) {
  fieldErrors[key] = ''
}

function validateField(key: string) {
  const v = String(form.value[key] ?? '')
  switch (key) {
    case 'name':
      if (!v.trim()) { fieldErrors.name = 'El nombre es requerido'; return }
      if (v.length > 255) { fieldErrors.name = 'Máximo 255 caracteres'; return }
      fieldErrors.name = ''
      break
    case 'email':
      if (v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { fieldErrors.email = 'Email inválido'; return }
      if (v.length > 255) { fieldErrors.email = 'Máximo 255 caracteres'; return }
      fieldErrors.email = ''
      break
    case 'phone':
      if (v && v.replace(/\D/g, '').length !== 8) { fieldErrors.phone = 'Debe tener 8 dígitos'; return }
      fieldErrors.phone = ''
      break
    case 'ruc':
      if (v) {
        const { error } = classifyNicaraguanId(v)
        if (error) { fieldErrors.ruc = error; return }
      }
      fieldErrors.ruc = ''
      break
    case 'address':
      if (v.length > 500) { fieldErrors.address = 'Máximo 500 caracteres'; return }
      fieldErrors.address = ''
      break
    case 'department':
      if (v.length > 100) { fieldErrors.department = 'Máximo 100 caracteres'; return }
      fieldErrors.department = ''
      break
    case 'notes':
      if (v.length > 500) { fieldErrors.notes = 'Máximo 500 caracteres'; return }
      fieldErrors.notes = ''
      break
  }
}

function fv(key: string): string | number | null {
  const v = form.value[key]
  if (v == null) return null
  if (typeof v === 'string' || typeof v === 'number') return v
  return null
}

const hasErrors = computed(() => Object.values(fieldErrors).some(Boolean))

const agentOptions = computed(() => agents.value.map((a) => ({ value: a.id, label: a.name })))

function handleSave() {
  for (const key of Object.keys(fieldErrors)) validateField(key)
  if (hasErrors.value) return
  save()
}

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Teléfono', width: '140px' },
  { key: 'ruc', label: 'RUC', width: '110px' },
  { key: 'department', label: 'Departamento', width: '140px' },
  { key: 'package_count', label: 'Paquetes', width: '90px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

onMounted(async () => {
  load()
  try {
    const res = await zoneService.listAll()
    zones.value = res.data.data.zones ?? []
  } catch {
    zones.value = []
  }
  if (!isVendor.value) {
    try {
      const res = await userService.listByRole('vendedor')
      agents.value = res.data.data.users ?? []
    } catch {
      agents.value = []
    }
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Clientes" subtitle="Gestionar clientes del sistema">
      <template #actions>
        <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Crear cliente
        </button>
      </template>
    </PageHeader>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="clients.length === 0" empty-title="Sin clientes" empty-desc="Creá el primer cliente del sistema.">
          <tr v-for="c in clients" :key="c.id">
            <td class="font-medium text-content">{{ c.name }}</td>
            <td class="text-muted">{{ c.email }}</td>
            <td class="text-muted">{{ c.phone || '—' }}</td>
            <td class="text-muted font-mono">{{ c.ruc || '—' }}</td>
            <td class="text-muted">{{ c.department || '—' }}</td>
            <td class="text-muted">{{ c.package_count ?? 0 }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(c)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(c)" title="Eliminar">
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

      <template v-else-if="clients.length > 0">
        <div v-for="c in clients" :key="c.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-content truncate">{{ c.name }}</p>
            <span class="admin-badge admin-badge-neutral shrink-0">{{ c.package_count ?? 0 }} paquetes</span>
          </div>
          <p class="text-sm text-muted truncate">{{ c.email || '—' }}</p>
          <p class="text-xs text-subtle">{{ c.phone || '—' }} · {{ c.ruc || 'Sin RUC' }}</p>
          <p class="text-xs text-subtle">{{ c.department || '—' }}</p>
          <div class="border-t border-line pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(c)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(c)">
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
          <p class="admin-empty-state-title">Sin clientes</p>
          <p class="admin-empty-state-desc">Creá el primer cliente del sistema.</p>
        </div>
      </div>

      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar cliente' : 'Crear cliente'" size="lg" @close="showModal = false">
      <form class="space-y-5" @submit.prevent="handleSave" novalidate>
        <!-- Sección: Información Personal -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
            Información personal
          </div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField
              :model-value="fv('name')"
              @update:model-value="(v: string | number | null) => form.name = v"
              label="Nombre"
              icon="user"
              placeholder="Nombre completo"
              :required="true"
              :disabled="saving"
              :maxlength="255"
              :error="fieldErrors.name"
              @blur="validateField('name')"
              @input="clearError('name')"
            />
            <AppFormField
              :model-value="fv('email')"
              @update:model-value="(v: string | number | null) => form.email = v"
              label="Email"
              type="email"
              icon="mail"
              placeholder="correo@ejemplo.com"
              :disabled="saving"
              :maxlength="255"
              :error="fieldErrors.email"
              @blur="validateField('email')"
              @input="clearError('email')"
            />
            <AppRucInput
              :model-value="fv('ruc')"
              @update:model-value="(v: string) => { form.ruc = v; clearError('ruc') }"
              label="RUC o cédula"
              helper="Cédula (persona natural) o RUC (persona jurídica). Aparece en la factura si la configuración lo muestra"
              :disabled="saving"
              :error="fieldErrors.ruc"
              @blur="validateField('ruc')"
            />
          </div>
          <div class="mt-4">
            <AppPhoneInput
              :model-value="fv('phone')"
              @update:model-value="(v: string) => { form.phone = v; clearError('phone') }"
              label="Teléfono"
              placeholder="1234-5678"
              :disabled="saving"
              :error="fieldErrors.phone"
              helper="Formato: +(505) 1234-5678"
              @blur="validateField('phone')"
            />
          </div>
          <div v-if="!isVendor" class="mt-4 max-w-sm" style="overflow: visible">
            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-content">Vendedor asignado</label>
            <AppDropdown
              :model-value="fv('agent_id')"
              @update:model-value="(v: string | number | null) => form.agent_id = v == null || v === '' ? null : Number(v)"
              :options="agentOptions"
              placeholder="Sin asignar"
              :disabled="saving"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Dirección -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0zM12 13a3 3 0 100-6 3 3 0 000 6z"/></svg>
            Dirección
          </div>
          <div class="mt-3 space-y-4">
            <AppFormField
              :model-value="fv('address')"
              @update:model-value="(v: string | number | null) => form.address = v"
              label="Dirección"
              type="textarea"
              icon="map-pin"
              placeholder="Dirección completa del cliente"
              :disabled="saving"
              :maxlength="500"
              :rows="2"
              :error="fieldErrors.address"
              @blur="validateField('address')"
              @input="clearError('address')"
            />
            <div class="max-w-sm" style="overflow: visible">
              <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-content">Departamento</label>
              <AppDropdown
                :model-value="fv('department')"
                @update:model-value="(v: string | number | null) => form.department = v"
                :options="zones.map(z => ({ value: z.name, label: z.name }))"
                placeholder="Seleccionar departamento..."
                :disabled="saving"
              />
            </div>
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
              placeholder="Información adicional sobre el cliente..."
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
            :disabled="saving || !form.name || hasErrors"
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
      title="Eliminar cliente"
      :message="`¿Seguro que querés eliminar a ${deleting?.name}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
