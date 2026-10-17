<script setup lang="ts">
import { onMounted, ref, computed, reactive } from 'vue'
import { roleService, type Role } from '../services/roleService'
import { permissionService, type Permission } from '../services/permissionService'
import { useCrudView } from '../composables/useCrudView'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppSwitch from '../components/ui/AppSwitch.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const allPermissions = ref<Permission[]>([])
const showPermsModal = ref(false)
const savingPerms = ref(false)
const permRole = ref<Role | null>(null)
const selectedPerms = ref<Set<number>>(new Set())

const {
  items: roles, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load: loadCrud, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<Role>({
  service: roleService,
  dataKey: 'roles',
  entityName: 'Rol',
  getDefaultForm: () => ({ name: '', slug: '' }),
  populateForm: (r) => ({ name: r.name, slug: r.slug }),
  validateForm: (f) => {
    if (!f.name) return 'El nombre es requerido'
    return null
  },
  getEntityId: (r) => r.id,
})

const fieldErrors = reactive<Record<string, string>>({ name: '', slug: '' })

function clearError(key: string) { fieldErrors[key] = '' }

function validateField(key: string) {
  const v = String(form.value[key] ?? '')
  switch (key) {
    case 'name':
      if (!v.trim()) { fieldErrors.name = 'El nombre es requerido'; return }
      if (v.length > 50) { fieldErrors.name = 'Máximo 50 caracteres'; return }
      fieldErrors.name = ''
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
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'slug', label: 'Slug', width: '150px' },
  { key: 'permission_count', label: 'Permisos', width: '100px', align: 'center' },
  { key: 'user_count', label: 'Usuarios', width: '100px', align: 'center' },
  { key: 'actions', label: '', width: '140px', align: 'right' },
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
  const [, permsRes] = await Promise.all([
    loadCrud(),
    permissionService.listAll().catch(() => ({ data: { data: { permissions: [] as Permission[] } } })),
  ])
  allPermissions.value = permsRes.data.data?.permissions ?? []
}

function openPerms(r: Role) {
  permRole.value = r
  selectedPerms.value = new Set()
  loadRolePermissions(r.id)
  showPermsModal.value = true
}

async function loadRolePermissions(roleId: number) {
  try {
    const { data } = await roleService.getPermissions(roleId)
    const perms = data.data?.permissions ?? []
    selectedPerms.value = new Set(perms.map((p) => p.id))
  } catch {
    useToast().error('Error al cargar permisos del rol')
  }
}

function togglePerm(permId: number) {
  if (selectedPerms.value.has(permId)) {
    selectedPerms.value.delete(permId)
  } else {
    selectedPerms.value.add(permId)
  }
}

function toggleModule(_module: string, perms: Permission[]) {
  const allSelected = perms.every((p) => selectedPerms.value.has(p.id))
  for (const p of perms) {
    if (allSelected) {
      selectedPerms.value.delete(p.id)
    } else {
      selectedPerms.value.add(p.id)
    }
  }
}

function isModuleChecked(_module: string, perms: Permission[]): boolean {
  return perms.every((p) => selectedPerms.value.has(p.id))
}

async function savePerms() {
  if (!permRole.value) return
  savingPerms.value = true
  try {
    await roleService.setPermissions(permRole.value.id, Array.from(selectedPerms.value))
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
    <PageHeader title="Roles" subtitle="Gestionar roles y sus permisos asignados">
      <template #actions>
        <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Crear rol
        </button>
      </template>
    </PageHeader>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="roles.length === 0" empty-title="Sin roles" empty-desc="Creá el primer rol del sistema.">
          <tr v-for="r in roles" :key="r.id">
            <td class="font-medium text-content">{{ r.name }}</td>
            <td><span class="admin-badge admin-badge-info">{{ r.slug }}</span></td>
            <td class="text-center text-muted">{{ r.permission_count }}</td>
            <td class="text-center text-muted">{{ r.user_count }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openPerms(r)" title="Permisos">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(r)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(r)" title="Eliminar" :disabled="r.user_count > 0">
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
      <template v-else-if="roles.length > 0">
        <div v-for="r in roles" :key="r.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-content truncate">{{ r.name }}</p>
            <span class="admin-badge admin-badge-info shrink-0">{{ r.slug }}</span>
          </div>
          <p class="text-sm text-muted">P: {{ r.permission_count }} · U: {{ r.user_count }}</p>
          <div class="border-t border-line pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openPerms(r)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              Permisos
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(r)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(r)" :disabled="r.user_count > 0">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              Eliminar
            </button>
          </div>
        </div>
      </template>
      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
          <p class="admin-empty-state-title">Sin roles</p>
          <p class="admin-empty-state-desc">Creá el primer rol del sistema.</p>
        </div>
      </div>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar rol' : 'Crear rol'" @close="showModal = false">
      <form class="space-y-5" @submit.prevent="handleSave" novalidate>
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Identificación
          </div>
          <div class="mt-3 space-y-4">
            <AppFormField
              :model-value="fv('name')"
              @update:model-value="(v: string | number | null) => form.name = v"
              label="Nombre"
              icon="shield"
              placeholder="ej: Administrador"
              :required="true"
              :disabled="saving"
              :maxlength="50"
              :error="fieldErrors.name"
              @blur="validateField('name')"
              @input="clearError('name')"
            />
            <AppFormField
              :model-value="fv('slug')"
              @update:model-value="(v: string | number | null) => form.slug = v"
              label="Slug (opcional)"
              icon="hash"
              placeholder="Se genera del nombre si se deja vacío"
              :disabled="saving"
              helper="Identificador único — se auto-genera si está vacío"
            />
          </div>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving || !form.name || hasErrors" @click="handleSave">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Permissions Matrix Modal -->
    <AppModal :visible="showPermsModal" :title="`Permisos — ${permRole?.name}`" size="lg" @close="showPermsModal = false">
      <div class="space-y-4">
        <div v-for="(perms, module) in groupedPerms" :key="module" class="rounded-lg border border-line bg-canvas/50 p-4">
          <div class="mb-3 flex items-center gap-3">
            <AppSwitch :model-value="isModuleChecked(module, perms)" @update:model-value="toggleModule(module, perms)" />
            <span class="text-sm font-semibold text-content uppercase tracking-wide">{{ module }}</span>
          </div>
          <div class="grid grid-cols-2 gap-2 pl-8 sm:grid-cols-3 md:grid-cols-4">
            <label v-for="p in perms" :key="p.id" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-content transition-colors hover:bg-surface-raised">
              <input type="checkbox" :checked="selectedPerms.has(p.id)" @change="togglePerm(p.id)" class="h-4 w-4 rounded border-line-strong bg-surface-raised text-primary focus:ring-primary/50" />
              {{ p.action }}
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
      title="Eliminar rol"
      :message="`¿Seguro que querés eliminar el rol ${deleting?.name}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
