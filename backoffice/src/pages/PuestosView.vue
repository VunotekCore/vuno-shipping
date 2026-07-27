<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { puestoService, type Puesto } from '../services/puestoService'
import { areaService, type Area } from '../services/areaService'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const areas = ref<Pick<Area, 'id' | 'name'>[]>([])

const {
  items: puestos, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load: loadCrud, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<Puesto>({
  service: puestoService,
  dataKey: 'puestos',
  entityName: 'Puesto',
  getDefaultForm: () => ({ name: '', description: '', area_id: areas.value[0]?.id ?? 0 }),
  populateForm: (p) => ({ name: p.name, description: p.description || '', area_id: p.area_id }),
  validateForm: (f) => {
    if (!f.name) return 'El nombre es requerido'
    if (!f.area_id || f.area_id === 0) return 'El área es requerida'
    return null
  },
  getEntityId: (p) => p.id,
})

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'area_name', label: 'Área', width: '180px' },
  { key: 'description', label: 'Descripción' },
  { key: 'empleado_count', label: 'Empleados', width: '100px', align: 'center' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

async function load() {
  const [, areaRes] = await Promise.all([
    loadCrud(),
    areaService.listAll().catch(() => ({ data: { data: { areas: [] as Pick<Area, 'id' | 'name'>[] } } })),
  ])
  areas.value = areaRes.data.data?.areas ?? []
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Puestos de Trabajo</h1>
        <p class="mt-1 text-sm text-[#64748b]">Puestos asociados a cada área</p>
      </div>
      <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear puesto
      </button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="puestos.length === 0" empty-title="Sin puestos" empty-desc="Creá el primer puesto de trabajo.">
          <tr v-for="p in puestos" :key="p.id">
            <td class="font-medium text-[#dae2fd]">{{ p.name }}</td>
            <td>
              <span class="admin-badge admin-badge-info">{{ p.area_name }}</span>
            </td>
            <td class="text-[#94a3b8]">{{ p.description || '—' }}</td>
            <td class="text-center text-[#94a3b8]">{{ p.empleado_count }}</td>
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
          <div class="border-t border-[#1e293b] pt-3">
            <div class="flex gap-2">
              <div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" />
              <div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" />
            </div>
          </div>
        </div>
      </template>
      <template v-else-if="puestos.length > 0">
        <div v-for="p in puestos" :key="p.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-[#dae2fd] truncate">{{ p.name }}</p>
            <span class="admin-badge admin-badge-info shrink-0">{{ p.area_name }}</span>
          </div>
          <p class="text-sm text-[#94a3b8] truncate">{{ p.description || '—' }} · {{ p.empleado_count }} empleados</p>
          <div class="border-t border-[#1e293b] pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(p)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(p)">
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
          <p class="admin-empty-state-title">Sin puestos</p>
          <p class="admin-empty-state-desc">Creá el primer puesto de trabajo.</p>
        </div>
      </div>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <AppModal :visible="showModal" :title="editing ? 'Editar puesto' : 'Crear puesto'" @close="showModal = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Nombre *</label>
          <input v-model="form.name" class="admin-input" placeholder="Nombre del puesto" required :disabled="saving" maxlength="100" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Área *</label>
          <select v-model="form.area_id" class="admin-select" required :disabled="saving">
            <option :value="0" disabled>Seleccionar área</option>
            <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Descripción</label>
          <textarea :value="String(form.description || '')" @input="form.description = ($event.target as HTMLTextAreaElement).value" class="admin-input" rows="3" placeholder="Descripción del puesto" :disabled="saving"></textarea>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="save" :disabled="saving || !form.name || !form.area_id">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar puesto"
      :message="`¿Seguro que querés eliminar ${deleting?.name}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
