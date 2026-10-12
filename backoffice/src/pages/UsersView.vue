<script setup lang="ts">
import { onMounted, ref, computed, reactive } from 'vue'
import { userService, type User } from '../services/userService'
import { roleService, type Role } from '../services/roleService'
import { permissionService, type Permission } from '../services/permissionService'
import { useCrudView } from '../composables/useCrudView'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import AppSwitch from '../components/ui/AppSwitch.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const roles = ref<Role[]>([])
const allPermissions = ref<Permission[]>([])

const showPermsModal = ref(false)
const savingPerms = ref(false)
const permUser = ref<User | null>(null)
const rolePermIds = ref<Set<number>>(new Set())
const selectedPerms = ref<Set<number>>(new Set())

const {
  items: users, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load: loadCrud, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<User>({
  service: userService,
  dataKey: 'users',
  entityName: 'Usuario',
  getDefaultForm: () => ({ email: '', password: '', name: '', role_id: roles.value[0]?.id ?? 0, commission_type: 'none', commission_value: null }),
  populateForm: (u) => ({ email: u.email, password: '', name: u.name, role_id: u.role_id, commission_type: u.commission_type ?? 'none', commission_value: u.commission_value ?? null }),
  validateForm: (f, isEdit) => {
    if (!f.name || !f.email || !f.role_id) return 'Nombre, email y rol son requeridos'
    if (!isEdit && !f.password) return 'La contraseña es requerida'
    if (f.commission_type && f.commission_type !== 'none' && (f.commission_value == null || Number(f.commission_value) < 0)) {
      return 'El valor de la comisión es requerido'
    }
    return null
  },
  getEntityId: (u) => u.id,
  prepareCreateData: (f) => ({
    email: f.email, password: f.password, name: f.name, role_id: f.role_id,
    commission_type: f.commission_type, commission_value: f.commission_type === 'none' ? null : f.commission_value,
  }),
  prepareUpdateData: (f, u) => {
    const data: Record<string, unknown> = {
      id: u.id, email: f.email, name: f.name, role_id: f.role_id,
      commission_type: f.commission_type, commission_value: f.commission_type === 'none' ? null : f.commission_value,
    }
    if (f.password) data.password = f.password
    return data
  },
})

const fieldErrors = reactive<Record<string, string>>({
  name: '', email: '', password: '', role_id: '',
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
    case 'email':
      if (!String(v ?? '').trim()) { fieldErrors.email = 'El email es requerido'; return }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v))) { fieldErrors.email = 'Email inválido'; return }
      fieldErrors.email = ''
      break
    case 'password':
      if (!editing.value && !String(v ?? '')) { fieldErrors.password = 'La contraseña es requerida'; return }
      if (String(v ?? '') && String(v).length < 8) { fieldErrors.password = 'Mínimo 8 caracteres'; return }
      fieldErrors.password = ''
      break
    case 'role_id':
      if (!v || v === 0) { fieldErrors.role_id = 'Seleccioná un rol'; return }
      fieldErrors.role_id = ''
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

const roleOptions = computed(() =>
  roles.value.map((r) => ({ value: r.id, label: r.name }))
)

const commissionTypeOptions = [
  { value: 'none', label: 'Sin comisión' },
  { value: 'percent', label: 'Porcentaje (%)' },
  { value: 'fixed', label: 'Monto fijo ($)' },
]

const selectedRoleSlug = computed(
  () => roles.value.find((r) => r.id === Number(form.value.role_id))?.slug ?? ''
)

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'email', label: 'Email' },
  { key: 'role_name', label: 'Rol', width: '150px' },
  { key: 'created_at', label: 'Creado', width: '140px' },
  { key: 'actions', label: '', width: '130px', align: 'right' },
]

const groupedPerms = computed(() => {
  const groups: Record<string, Permission[]> = {}
  for (const p of allPermissions.value) {
    if (!groups[p.module]) groups[p.module] = []
    groups[p.module].push(p)
  }
  return groups
})

async function load() {
  const [, rolesRes, permsRes] = await Promise.all([
    loadCrud(),
    roleService.list().catch(() => ({ data: { data: { roles: [] as Role[] } } })),
    permissionService.list().catch(() => ({ data: { data: { permissions: [] as Permission[] } } })),
  ])
  roles.value = rolesRes.data.data?.roles ?? []
  allPermissions.value = permsRes.data.data?.permissions ?? []
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('es-NI', { year: 'numeric', month: 'short', day: 'numeric' })
}

function openPerms(u: User) {
  permUser.value = u
  rolePermIds.value = new Set()
  selectedPerms.value = new Set()
  loadUserPermissions(u)
  showPermsModal.value = true
}

async function loadUserPermissions(user: User) {
  try {
    const [rolePermsRes, userPermsRes] = await Promise.all([
      roleService.getPermissions(user.role_id),
      userService.getPermissions(user.id),
    ])
    const rps = rolePermsRes.data.data?.permissions ?? []
    rolePermIds.value = new Set(rps.map((p) => p.id))
    const basePerms = new Set(rps.map((p) => p.id))
    const ups = userPermsRes.data.data?.permissions ?? []
    for (const up of ups) {
      if (up.granted) { basePerms.add(up.id) } else { basePerms.delete(up.id) }
    }
    selectedPerms.value = basePerms
  } catch {
    useToast().error('Error al cargar permisos del usuario')
  }
}

function togglePerm(permId: number) {
  if (selectedPerms.value.has(permId)) { selectedPerms.value.delete(permId) } else { selectedPerms.value.add(permId) }
}

function toggleModule(_module: string, perms: Permission[]) {
  const allSelected = perms.every((p) => selectedPerms.value.has(p.id))
  for (const p of perms) {
    if (allSelected) { selectedPerms.value.delete(p.id) } else { selectedPerms.value.add(p.id) }
  }
}

function isModuleChecked(_module: string, perms: Permission[]): boolean {
  return perms.every((p) => selectedPerms.value.has(p.id))
}

async function savePerms() {
  if (!permUser.value) return
  savingPerms.value = true
  try {
    const grantedIds: number[] = []
    const revokedIds: number[] = []
    for (const p of allPermissions.value) {
      const inRole = rolePermIds.value.has(p.id)
      const inSelected = selectedPerms.value.has(p.id)
      if (inSelected && !inRole) grantedIds.push(p.id)
      if (!inSelected && inRole) revokedIds.push(p.id)
    }
    await userService.setPermissions(permUser.value.id, grantedIds, revokedIds)
    useToast().success('Permisos actualizados')
    showPermsModal.value = false
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    useToast().error(msg || 'Error al guardar permisos')
  } finally {
    savingPerms.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Usuarios" subtitle="Gestionar usuarios del sistema">
      <template #actions>
        <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Crear usuario
        </button>
      </template>
    </PageHeader>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="users.length === 0" empty-title="Sin usuarios" empty-desc="Creá el primer usuario del sistema.">
          <tr v-for="u in users" :key="u.id">
            <td class="font-medium text-content">{{ u.name }}</td>
            <td class="text-muted">{{ u.email }}</td>
            <td><span class="admin-badge admin-badge-info">{{ u.role_name }}</span></td>
            <td class="text-subtle">{{ u.created_at ? formatDate(u.created_at) : '—' }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openPerms(u)" title="Permisos">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(u)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(u)" title="Eliminar">
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
          <div class="flex items-center justify-between"><div class="h-4 w-1/3 animate-pulse rounded bg-surface-raised" /><div class="h-5 w-16 animate-pulse rounded-full bg-surface-raised" /></div>
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-surface-raised" />
          <div class="border-t border-line pt-3"><div class="flex gap-2"><div class="h-7 w-20 animate-pulse rounded bg-surface-raised" /><div class="h-7 w-16 animate-pulse rounded bg-surface-raised" /><div class="h-7 w-16 animate-pulse rounded bg-surface-raised" /></div></div>
        </div>
      </template>
      <template v-else-if="users.length > 0">
        <div v-for="u in users" :key="u.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-content truncate">{{ u.name }}</p>
            <span class="admin-badge admin-badge-info shrink-0">{{ u.role_name }}</span>
          </div>
          <p class="text-sm text-muted truncate">{{ u.email }}</p>
          <p class="text-xs text-subtle">{{ u.created_at ? formatDate(u.created_at) : '—' }}</p>
          <div class="border-t border-line pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openPerms(u)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              Permisos
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(u)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(u)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              Eliminar
            </button>
          </div>
        </div>
      </template>
      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
          <p class="admin-empty-state-title">Sin usuarios</p>
          <p class="admin-empty-state-desc">Creá el primer usuario del sistema.</p>
        </div>
      </div>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar usuario' : 'Crear usuario'" @close="showModal = false">
      <form class="space-y-5" @submit.prevent="handleSave" novalidate>
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Datos del usuario
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
            <AppFormField
              :model-value="fv('email')"
              @update:model-value="(v: string | number | null) => form.email = v"
              label="Email"
              type="email"
              icon="mail"
              placeholder="correo@ejemplo.com"
              :required="true"
              :disabled="saving"
              :error="fieldErrors.email"
              @blur="validateField('email')"
              @input="clearError('email')"
            />
            <AppFormField
              :model-value="fv('password')"
              @update:model-value="(v: string | number | null) => form.password = v"
              :label="editing ? 'Contraseña (dejar vacío para no cambiar)' : 'Contraseña'"
              type="password"
              icon="lock"
              :placeholder="editing ? '••••••••' : 'Mínimo 8 caracteres'"
              :required="!editing"
              :disabled="saving"
              :minlength="8"
              :error="fieldErrors.password"
              @blur="validateField('password')"
              @input="clearError('password')"
            />
            <div>
              <label class="mb-1.5 block text-sm font-medium text-muted">Rol <span class="text-danger">*</span></label>
              <AppDropdown
                :model-value="fv('role_id')"
                @update:model-value="(v: string | number | null) => { form.role_id = v; clearError('role_id') }"
                :options="roleOptions"
                placeholder="Seleccionar rol"
                :disabled="saving"
                :error="fieldErrors.role_id"
              />
              <p v-if="fieldErrors.role_id" class="mt-1.5 text-xs text-danger">{{ fieldErrors.role_id }}</p>
            </div>
            <div v-if="selectedRoleSlug === 'vendedor'">
              <label class="mb-1.5 block text-sm font-medium text-muted">Comisión por venta</label>
              <div class="grid grid-cols-2 gap-3">
                <AppDropdown
                  :model-value="fv('commission_type') ?? 'none'"
                  @update:model-value="(v: string | number | null) => form.commission_type = String(v ?? 'none')"
                  :options="commissionTypeOptions"
                  :disabled="saving"
                />
                <AppFormField
                  v-if="form.commission_type && form.commission_type !== 'none'"
                  :model-value="fv('commission_value')"
                  @update:model-value="(v: string | number | null) => form.commission_value = v == null ? null : Number(v)"
                  :label="form.commission_type === 'percent' ? 'Porcentaje' : 'Monto (USD)'"
                  type="number"
                  :step="0.01"
                  :min="0"
                  :disabled="saving"
                />
              </div>
            </div>
          </div>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving || !form.name || !form.email || !form.role_id || (!editing && !form.password) || hasErrors" @click="handleSave">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Permissions Matrix Modal -->
    <AppModal :visible="showPermsModal" :title="`Permisos — ${permUser?.name}`" size="lg" @close="showPermsModal = false">
      <div class="space-y-4">
        <div class="rounded-lg border border-line bg-canvas/50 p-3 text-sm text-muted">
          Rol: <span class="font-medium text-content">{{ permUser?.role_name }}</span>
          — Los permisos del rol son la base. Los toggles controlan permisos extra o excluidos para este usuario.
        </div>
        <div v-for="(perms, module) in groupedPerms" :key="module" class="rounded-lg border border-line bg-canvas/50 p-4">
          <div class="mb-3 flex items-center gap-3">
            <AppSwitch :model-value="isModuleChecked(module, perms)" @update:model-value="toggleModule(module, perms)" />
            <span class="text-sm font-semibold text-content uppercase tracking-wide">{{ module }}</span>
            <span v-if="rolePermIds.size > 0" class="ml-auto text-xs text-subtle">{{ perms.filter(p => rolePermIds.has(p.id)).length }}/{{ perms.length }} del rol</span>
          </div>
          <div class="grid grid-cols-2 gap-2 pl-8 sm:grid-cols-3 md:grid-cols-4">
            <label v-for="p in perms" :key="p.id" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-surface-raised">
              <input type="checkbox" :checked="selectedPerms.has(p.id)" @change="togglePerm(p.id)" class="h-4 w-4 rounded border-line-strong bg-surface-raised text-primary focus:ring-primary/50" />
              <span :class="rolePermIds.has(p.id) ? 'text-muted' : 'text-primary'">{{ p.action }}</span>
              <span v-if="rolePermIds.has(p.id)" class="ml-auto text-[10px] text-subtle">rol</span>
              <span v-else-if="selectedPerms.has(p.id)" class="ml-auto text-[10px] text-accent">+extra</span>
            </label>
          </div>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showPermsModal = false" :disabled="savingPerms">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="savePerms" :disabled="savingPerms">
            {{ savingPerms ? 'Guardando...' : 'Guardar permisos' }}
          </button>
        </div>
      </template>
    </AppModal>

    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar usuario"
      :message="`¿Seguro que querés eliminar a ${deleting?.name}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
