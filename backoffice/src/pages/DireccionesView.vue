<script setup lang="ts">
import { onMounted } from 'vue'
import { direccionService, type Direccion } from '../services/direccionService'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const {
  items: direcciones, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<Direccion>({
  service: direccionService,
  dataKey: 'direcciones',
  entityName: 'Dirección',
  getDefaultForm: () => ({ name: '', description: '' }),
  populateForm: (d) => ({ name: d.name, description: d.description || '' }),
  validateForm: (f) => {
    if (!f.name) return 'El nombre es requerido'
    return null
  },
  getEntityId: (d) => d.id,
})

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '200px' },
  { key: 'description', label: 'Descripción' },
  { key: 'puesto_count', label: 'Puestos', width: '100px', align: 'center' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Direcciones</h1>
        <p class="mt-1 text-sm text-[#64748b]">Estructura organizacional — Direcciones</p>
      </div>
      <button class="admin-btn admin-btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear dirección
      </button>
    </div>

    <div class="admin-card">
      <AppTable :columns="columns" :loading="loading" :is-empty="direcciones.length === 0" empty-title="Sin direcciones" empty-desc="Creá la primera dirección de la empresa.">
        <tr v-for="d in direcciones" :key="d.id">
          <td class="font-medium text-[#dae2fd]">{{ d.name }}</td>
          <td class="text-[#94a3b8]">{{ d.description || '—' }}</td>
          <td class="text-center text-[#94a3b8]">{{ d.puesto_count }}</td>
          <td class="text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(d)" title="Editar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(d)" title="Eliminar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </div>
          </td>
        </tr>
      </AppTable>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <AppModal :visible="showModal" :title="editing ? 'Editar dirección' : 'Crear dirección'" @close="showModal = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Nombre *</label>
          <input v-model="form.name" class="admin-input" placeholder="Nombre de la dirección" required :disabled="saving" maxlength="100" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Descripción</label>
          <textarea :value="String(form.description || '')" @input="form.description = ($event.target as HTMLTextAreaElement).value" class="admin-input" rows="3" placeholder="Descripción de la dirección" :disabled="saving"></textarea>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="save" :disabled="saving || !form.name">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar dirección"
      :message="`¿Seguro que querés eliminar ${deleting?.name}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
