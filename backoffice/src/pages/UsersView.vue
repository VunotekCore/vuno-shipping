<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { userService, type User } from '../services/userService'
import { roleService, type Role } from '../services/roleService'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const roles = ref<Role[]>([])

const {
  items: users, loading, showModal, showDelete,
  editing, deleting, saving, form,
  load: loadCrud, openCreate, openEdit, openDelete, save, confirmDelete,
} = useCrudView<User>({
  service: userService,
  dataKey: 'users',
  entityName: 'Usuario',
  getDefaultForm: () => ({ email: '', password: '', name: '', role_id: roles.value[0]?.id ?? 0 }),
  populateForm: (u) => ({ email: u.email, password: '', name: u.name, role_id: u.role_id }),
  validateForm: (f, isEdit) => {
    if (!f.name || !f.email || !f.role_id) return 'Nombre, email y rol son requeridos'
    if (!isEdit && !f.password) return 'La contraseña es requerida'
    return null
  },
  getEntityId: (u) => u.id,
  prepareCreateData: (f) => ({ email: f.email, password: f.password, name: f.name, role_id: f.role_id }),
  prepareUpdateData: (f, u) => {
    const data: Record<string, unknown> = { id: u.id, email: f.email, name: f.name, role_id: f.role_id }
    if (f.password) data.password = f.password
    return data
  },
})

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'email', label: 'Email' },
  { key: 'role_name', label: 'Rol', width: '150px' },
  { key: 'created_at', label: 'Creado', width: '140px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

async function load() {
  const [, rolesRes] = await Promise.all([
    loadCrud(),
    roleService.list().catch(() => ({ data: { data: { roles: [] as Role[] } } })),
  ])
  roles.value = rolesRes.data.data?.roles ?? []
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('es-NI', { year: 'numeric', month: 'short', day: 'numeric' })
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Usuarios</h1>
        <p class="mt-1 text-sm text-[#64748b]">Gestionar usuarios del sistema</p>
      </div>
      <button class="admin-btn admin-btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear usuario
      </button>
    </div>

    <div class="admin-card">
      <AppTable :columns="columns" :loading="loading" empty-title="Sin usuarios" empty-desc="Creá el primer usuario del sistema.">
        <tr v-for="u in users" :key="u.id">
          <td class="font-medium text-[#dae2fd]">{{ u.name }}</td>
          <td class="text-[#94a3b8]">{{ u.email }}</td>
          <td>
            <span class="admin-badge admin-badge-info">{{ u.role_name }}</span>
          </td>
          <td class="text-[#64748b]">{{ u.created_at ? formatDate(u.created_at) : '—' }}</td>
          <td class="text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(u)" title="Editar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(u)" title="Eliminar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </div>
          </td>
        </tr>
      </AppTable>
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar usuario' : 'Crear usuario'" @close="showModal = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Nombre</label>
          <input v-model="form.name" class="admin-input" placeholder="Nombre completo" required :disabled="saving" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Email</label>
          <input v-model="form.email" type="email" class="admin-input" placeholder="correo@ejemplo.com" required :disabled="saving" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">
            {{ editing ? 'Contraseña (dejar vacío para no cambiar)' : 'Contraseña' }}
          </label>
          <input
            v-model="form.password"
            type="password"
            class="admin-input"
            :placeholder="editing ? '••••••••' : 'Mínimo 8 caracteres'"
            :required="!editing"
            minlength="8"
            :disabled="saving"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Rol</label>
          <select v-model="form.role_id" class="admin-select" required :disabled="saving">
            <option :value="0" disabled>Seleccionar rol</option>
            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="save" :disabled="saving || !form.name || !form.email || !form.role_id || (!editing && !form.password)">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Delete Confirmation -->
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
