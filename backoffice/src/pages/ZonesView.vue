<script setup lang="ts">
import { onMounted } from 'vue'
import { zoneService, type Zone } from '../services/zoneService'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const {
  items: zones, loading, showModal,
  editing, saving, form,
  page, limit, total, pages,
  load, openCreate, openEdit, save, goToPage, setLimit,
} = useCrudView<Zone>({
  service: zoneService,
  dataKey: 'zones',
  entityName: 'Zona',
  getDefaultForm: () => ({ name: '', description: '', lat: null, lng: null, surcharge: 0 }),
  populateForm: (z) => ({ name: z.name, description: z.description ?? '', lat: z.lat, lng: z.lng, surcharge: z.surcharge ?? 0 }),
  validateForm: (f) => {
    if (!f.name) return 'El nombre es requerido'
    return null
  },
  getEntityId: (z) => z.id,
})

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'description', label: 'Descripción' },
  { key: 'surcharge', label: 'Sobrecosto', width: '110px' },
  { key: 'lat', label: 'Lat', width: '100px' },
  { key: 'lng', label: 'Lng', width: '100px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Zonas" subtitle="Gestionar zonas de envío">
      <template #actions>
        <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Crear zona
        </button>
      </template>
    </PageHeader>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="zones.length === 0" empty-title="Sin zonas" empty-desc="Creá la primera zona de envío.">
          <tr v-for="z in zones" :key="z.id">
            <td class="font-medium text-content">{{ z.name }}</td>
            <td class="text-muted">{{ z.description || '—' }}</td>
            <td class="text-muted">${{ Number(z.surcharge ?? 0).toFixed(2) }}</td>
            <td class="text-muted">{{ z.lat ?? '—' }}</td>
            <td class="text-muted">{{ z.lng ?? '—' }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(z)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
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
      <!-- Loading skeletons -->
      <template v-if="loading">
        <div v-for="i in 3" :key="i" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-4 w-1/3 animate-pulse rounded bg-surface-raised" />
          </div>
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-surface-raised" />
          <div class="h-3 w-1/4 animate-pulse rounded bg-surface-raised" />
          <div class="border-t border-line pt-3">
            <div class="flex gap-2">
              <div class="h-7 w-16 animate-pulse rounded bg-surface-raised" />
            </div>
          </div>
        </div>
      </template>

      <!-- Cards -->
      <template v-else-if="zones.length > 0">
        <div v-for="z in zones" :key="z.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-content truncate">{{ z.name }}</p>
          </div>
          <p v-if="z.description" class="text-sm text-muted truncate">{{ z.description }}</p>
          <p class="text-xs text-subtle">
            Sobrecosto: ${{ Number(z.surcharge ?? 0).toFixed(2) }}
            <template v-if="z.lat != null || z.lng != null"> · {{ z.lat ?? '—' }}, {{ z.lng ?? '—' }}</template>
          </p>
          <div class="border-t border-line pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(z)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
          </div>
        </div>
      </template>

      <!-- Empty state -->
      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          <p class="admin-empty-state-title">Sin zonas</p>
          <p class="admin-empty-state-desc">Creá la primera zona de envío.</p>
        </div>
      </div>

      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar zona' : 'Crear zona'" @close="showModal = false">
      <form class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">Nombre</label>
          <input v-model="form.name" class="admin-input" placeholder="Nombre de la zona" required :disabled="saving" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-content">Descripción</label>
          <textarea :value="String(form.description ?? '')" @input="form.description = ($event.target as HTMLTextAreaElement).value" class="admin-input" placeholder="Descripción de la zona" rows="3" :disabled="saving" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-content">Sobrecosto ($)</label>
            <input v-model.number="form.surcharge" type="number" step="0.01" min="0" class="admin-input" placeholder="0.00" :disabled="saving" />
            <p class="mt-1 text-xs text-subtle">Costo fijo adicional por destino</p>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-content">Latitud</label>
            <input v-model.number="form.lat" type="number" step="any" class="admin-input" placeholder="12.8654" :disabled="saving" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-content">Longitud</label>
            <input v-model.number="form.lng" type="number" step="any" class="admin-input" placeholder="-85.2072" :disabled="saving" />
          </div>
          <div />
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
  </div>
</template>
