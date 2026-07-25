<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { userService, type User } from '../services/userService'
import { roleService, type Role } from '../services/roleService'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const toast = useToast()

const users = ref<User[]>([])
const roles = ref<Role[]>([])
const loading = ref(true)
const showModal = ref(false)
const showDelete = ref(false)
const editing = ref<User | null>(null)
const deleting = ref<User | null>(null)
const saving = ref(false)

const form = ref({ email: '', password: '', name: '', role_id: 0 })

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'email', label: 'Email' },
  { key: 'role_name', label: 'Rol', width: '150px' },
  { key: 'created_at', label: 'Creado', width: '140px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

onMounted(() => load())

async function load() {
  loading.value = true
  try {
    const [usersRes, rolesRes] = await Promise.all([
      userService.list(),
      roleService.list(),
    ])
    users.value = usersRes.data.data?.users ?? []
    roles.value = rolesRes.data.data?.roles ?? []
  } catch {
    toast.error('Error al cargar datos')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { email: '', password: '', name: '', role_id: roles.value[0]?.id ?? 0 }
  showModal.value = true
}

function openEdit(u: User) {
  editing.value = u
  form.value = { email: u.email, password: '', name: u.name, role_id: u.role_id }
  showModal.value = true
}

function openDelete(u: User) {
  deleting.value = u
  showDelete.value = true
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('es-NI', { year: 'numeric', month: 'short', day: 'numeric' })
}

async function save() {
  saving.value = true
  try {
    if (editing.value) {
      const data: Record<string, unknown> = {
        id: editing.value.id,
        email: form.value.email,
        name: form.value.name,
        role_id: form.value.role_id,
      }
      if (form.value.password) {
        data.password = form.value.password
      }
      await userService.update(data as { id: number; email: string; name: string; role_id: number; password?: string })
      toast.success('Usuario actualizado')
    } else {
      await userService.create(form.value)
      toast.success('Usuario creado')
    }
    showModal.value = false
    await load()
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!deleting.value) return
  try {
    await userService.delete(deleting.value.id)
    toast.success('Usuario eliminado')
    showDelete.value = false
    await load()
  } catch (e: any) {
    toast.error(e?.response?.data?.message || 'Error al eliminar')
  }
}
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
          <td class="text-[#64748b]">{{ formatDate(u.created_at) }}</td>
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
