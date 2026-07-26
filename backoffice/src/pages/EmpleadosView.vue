<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { empleadoService, type Empleado } from '../services/empleadoService'
import { puestoService, type Puesto } from '../services/puestoService'
import { userService, type User } from '../services/userService'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const puestos = ref<Pick<Puesto, 'id' | 'name' | 'direccion_name'>[]>([])
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

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'cedula', label: 'Cédula', width: '120px' },
  { key: 'puesto_name', label: 'Puesto', width: '150px' },
  { key: 'phone', label: 'Teléfono', width: '130px' },
  { key: 'user_name', label: 'Usuario', width: '140px' },
  { key: 'is_active', label: 'Estado', width: '80px', align: 'center' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

function availableUsers() {
  const assignedIds = empleados.value
    .filter((e) => e.user_id && (!editing.value || e.id !== editing.value.id))
    .map((e) => e.user_id)
  return usuarios.value.filter((u) => !assignedIds.includes(u.id))
}

async function load() {
  const [, puestoRes, userRes] = await Promise.all([
    loadCrud(),
    puestoService.listAll().catch(() => ({ data: { data: { puestos: [] as Pick<Puesto, 'id' | 'name' | 'direccion_name'>[] } } })),
    userService.list().catch(() => ({ data: { data: { users: [] as Pick<User, 'id' | 'name' | 'email'>[] } } })),
  ])
  puestos.value = puestoRes.data.data?.puestos ?? []
  usuarios.value = userRes.data.data?.users ?? []
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Empleados</h1>
        <p class="mt-1 text-sm text-[#64748b]">Gestionar empleados y su acceso al sistema</p>
      </div>
      <button class="admin-btn admin-btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear empleado
      </button>
    </div>

    <div class="admin-card">
      <AppTable :columns="columns" :loading="loading" :is-empty="empleados.length === 0" empty-title="Sin empleados" empty-desc="Creá el primer empleado.">
        <tr v-for="e in empleados" :key="e.id">
          <td>
            <div>
              <p class="font-medium text-[#dae2fd]">{{ e.name }}</p>
              <p v-if="e.email" class="text-xs text-[#64748b]">{{ e.email }}</p>
            </div>
          </td>
          <td class="text-[#94a3b8]">{{ e.cedula || '—' }}</td>
          <td>
            <span class="admin-badge admin-badge-info">{{ e.puesto_name }}</span>
          </td>
          <td class="text-[#94a3b8]">{{ e.phone || '—' }}</td>
          <td class="text-[#94a3b8]">{{ e.user_name || '—' }}</td>
          <td class="text-center">
            <span
              :class="e.is_active ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-danger'"
            >
              {{ e.is_active ? 'Activo' : 'Inactivo' }}
            </span>
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

    <AppModal :visible="showModal" :title="editing ? 'Editar empleado' : 'Crear empleado'" @close="showModal = false" size="md">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Nombre *</label>
          <input v-model="form.name" class="admin-input" placeholder="Nombre completo" required :disabled="saving" maxlength="100" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Cédula</label>
            <input v-model="form.cedula" class="admin-input" placeholder="000-000000-0000A" :disabled="saving" maxlength="30" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Teléfono</label>
            <input v-model="form.phone" class="admin-input" placeholder="+505 8888-8888" :disabled="saving" maxlength="30" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Email</label>
            <input v-model="form.email" type="email" class="admin-input" placeholder="correo@ejemplo.com" :disabled="saving" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Fecha de ingreso</label>
            <input v-model="form.fecha_ingreso" type="date" class="admin-input" :disabled="saving" />
          </div>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Puesto *</label>
          <select v-model="form.puesto_id" class="admin-select" required :disabled="saving">
            <option :value="0" disabled>Seleccionar puesto</option>
            <option v-for="p in puestos" :key="p.id" :value="p.id">{{ p.name }} ({{ p.direccion_name }})</option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Usuario de acceso</label>
          <select v-model="form.user_id" class="admin-select" :disabled="saving">
            <option :value="null">Sin usuario asignado</option>
            <option v-for="u in availableUsers()" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
          </select>
          <p class="mt-1 text-xs text-[#64748b]">Opcional — Asigná un usuario para que pueda acceder al sistema</p>
        </div>
        <div class="flex items-center gap-3">
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" v-model="form.is_active" class="peer sr-only" :disabled="saving" />
            <div class="peer h-6 w-11 rounded-full bg-[#1e293b] after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-600 after:bg-[#64748b] after:transition-all peer-checked:bg-[#69dca4] peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
          </label>
          <span class="text-sm text-[#94a3b8]">{{ form.is_active ? 'Activo' : 'Inactivo' }}</span>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="save" :disabled="saving || !form.name || !form.puesto_id">
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
