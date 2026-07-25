<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { permissionService, type Permission } from '../services/permissionService'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const toast = useToast()

const permissions = ref<Permission[]>([])
const loading = ref(true)
const showModal = ref(false)
const showDelete = ref(false)
const editing = ref<Permission | null>(null)
const deleting = ref<Permission | null>(null)
const saving = ref(false)

const form = ref({ module: '', action: '', label: '' })

const columns: TableColumn[] = [
  { key: 'module', label: 'Módulo', width: '150px' },
  { key: 'action', label: 'Acción', width: '150px' },
  { key: 'label', label: 'Etiqueta' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

onMounted(() => load())

async function load() {
  loading.value = true
  try {
    const { data } = await permissionService.list()
    permissions.value = data.data?.permissions ?? []
  } catch {
    toast.error('Error al cargar permisos')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { module: '', action: '', label: '' }
  showModal.value = true
}

function openEdit(p: Permission) {
  editing.value = p
  form.value = { module: p.module, action: p.action, label: p.label }
  showModal.value = true
}

function openDelete(p: Permission) {
  deleting.value = p
  showDelete.value = true
}

async function save() {
  saving.value = true
  try {
    if (editing.value) {
      await permissionService.update({ id: editing.value.id, ...form.value })
      toast.success('Permiso actualizado')
    } else {
      await permissionService.create(form.value)
      toast.success('Permiso creado')
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
    await permissionService.delete(deleting.value.id)
    toast.success('Permiso eliminado')
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
        <h1 class="text-xl font-bold text-[#dae2fd]">Permisos</h1>
        <p class="mt-1 text-sm text-[#64748b]">Gestionar permisos del sistema RBAC</p>
      </div>
      <button class="admin-btn admin-btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear permiso
      </button>
    </div>

    <div class="admin-card">
      <AppTable :columns="columns" :loading="loading" empty-title="Sin permisos" empty-desc="Creá el primer permiso del sistema.">
        <tr v-for="p in permissions" :key="p.id" class="admin-table-row">
          <td>
            <span class="admin-badge admin-badge-info">{{ p.module }}</span>
          </td>
          <td>
            <span class="admin-badge admin-badge-success">{{ p.action }}</span>
          </td>
          <td class="text-[#94a3b8]">{{ p.label || '—' }}</td>
          <td class="text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(p)" title="Editar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(p)" title="Eliminar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </div>
          </td>
        </tr>
      </AppTable>
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar permiso' : 'Crear permiso'" @close="showModal = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Módulo</label>
          <input v-model="form.module" class="admin-input" placeholder="ej: user, role, shipment" required :disabled="saving" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Acción</label>
          <input v-model="form.action" class="admin-input" placeholder="ej: read, create, edit, delete" required :disabled="saving" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Etiqueta (opcional)</label>
          <input v-model="form.label" class="admin-input" placeholder="Descripción legible" :disabled="saving" />
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="save" :disabled="saving || !form.module || !form.action">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Delete Confirmation -->
    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar permiso"
      :message="`¿Seguro que querés eliminar el permiso ${deleting?.module}.${deleting?.action}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
