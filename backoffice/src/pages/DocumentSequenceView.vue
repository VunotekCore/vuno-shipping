<script setup lang="ts">
import { onMounted, reactive, computed } from 'vue'
import { documentSequenceService, type DocumentSequence } from '../services/documentSequenceService'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const {
  items: sequences, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<DocumentSequence>({
  service: documentSequenceService,
  dataKey: 'document_sequences',
  entityName: 'Secuencia',
  getDefaultForm: () => ({ document_code: '', description: '', prefix: '', current_number: 1 }),
  populateForm: (s) => ({
    document_code: s.document_code,
    description: s.description || '',
    prefix: s.prefix || '',
    current_number: s.current_number,
  }),
  validateForm: (f) => {
    if (!f.document_code) return 'El código del documento es requerido'
    return null
  },
  getEntityId: (s) => s.id,
})

const fieldErrors = reactive<Record<string, string>>({
  document_code: '', description: '', prefix: '', current_number: '',
})

function clearError(key: string) { fieldErrors[key] = '' }

function validateField(key: string) {
  const v = form.value[key]
  switch (key) {
    case 'document_code':
      if (!String(v ?? '').trim()) { fieldErrors.document_code = 'El código es requerido'; return }
      if (String(v).length > 20) { fieldErrors.document_code = 'Máximo 20 caracteres'; return }
      fieldErrors.document_code = ''
      break
    case 'description':
      if (String(v ?? '').length > 100) { fieldErrors.description = 'Máximo 100 caracteres'; return }
      fieldErrors.description = ''
      break
    case 'prefix':
      if (String(v ?? '').length > 10) { fieldErrors.prefix = 'Máximo 10 caracteres'; return }
      fieldErrors.prefix = ''
      break
    case 'current_number':
      if (v !== null && v !== '' && Number(v) < 1) { fieldErrors.current_number = 'Mínimo 1'; return }
      fieldErrors.current_number = ''
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
  { key: 'document_code', label: 'Código', width: '140px' },
  { key: 'description', label: 'Descripción' },
  { key: 'prefix', label: 'Prefijo', width: '100px' },
  { key: 'current_number', label: 'Próximo N°', width: '120px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Secuencias de Documentos</h1>
        <p class="mt-1 text-sm text-[#64748b]">Control de numeración de documentos</p>
      </div>
      <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear secuencia
      </button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="sequences.length === 0" empty-title="Sin secuencias" empty-desc="Creá la primera secuencia de documentos.">
          <tr v-for="s in sequences" :key="s.id">
            <td class="font-medium text-[#dae2fd]">{{ s.document_code }}</td>
            <td class="text-[#94a3b8]">{{ s.description || '—' }}</td>
            <td class="text-[#94a3b8]">{{ s.prefix || '—' }}</td>
            <td class="text-[#94a3b8]">{{ s.current_number }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(s)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(s)" title="Eliminar">
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
          <div class="h-4 w-1/4 animate-pulse rounded bg-[#1e293b]" />
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-[#1e293b]" />
          <div class="border-t border-[#1e293b] pt-3"><div class="flex gap-2"><div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" /><div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" /></div></div>
        </div>
      </template>
      <template v-else-if="sequences.length > 0">
        <div v-for="s in sequences" :key="s.id" class="admin-card p-4 space-y-3">
          <p class="font-medium text-[#dae2fd] truncate">{{ s.document_code }}</p>
          <p class="text-sm text-[#94a3b8]">{{ s.description || '—' }} · Prefijo: {{ s.prefix || '—' }} · Próximo: {{ s.current_number }}</p>
          <div class="border-t border-[#1e293b] pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(s)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(s)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              Eliminar
            </button>
          </div>
        </div>
      </template>
      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" /><polyline points="10 9 9 9 8 9" /></svg>
          <p class="admin-empty-state-title">Sin secuencias</p>
          <p class="admin-empty-state-desc">Creá la primera secuencia de documentos.</p>
        </div>
      </div>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar secuencia' : 'Crear secuencia'" @close="showModal = false">
      <form class="space-y-5" @submit.prevent="handleSave" novalidate>
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Documento
          </div>
          <div class="mt-3 space-y-4">
            <AppFormField
              :model-value="fv('document_code')"
              @update:model-value="(v: string | number | null) => form.document_code = v"
              label="Código del documento"
              icon="hash"
              placeholder="FAC, NCR, NDB..."
              :required="true"
              :disabled="saving || !!editing"
              :maxlength="20"
              helper="Solo letras mayúsculas, números y guiones bajos"
              :error="fieldErrors.document_code"
              @blur="validateField('document_code')"
              @input="clearError('document_code')"
            />
            <AppFormField
              :model-value="fv('description')"
              @update:model-value="(v: string | number | null) => form.description = v"
              label="Descripción"
              icon="file-text"
              placeholder="Factura, Nota de crédito..."
              :disabled="saving"
              :maxlength="100"
              :error="fieldErrors.description"
              @blur="validateField('description')"
              @input="clearError('description')"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
            Numeración
          </div>
          <div class="mt-3 grid grid-cols-2 gap-4">
            <AppFormField
              :model-value="fv('prefix')"
              @update:model-value="(v: string | number | null) => form.prefix = v"
              label="Prefijo"
              icon="type"
              placeholder="FAC"
              :disabled="saving"
              :maxlength="10"
              :error="fieldErrors.prefix"
              @blur="validateField('prefix')"
              @input="clearError('prefix')"
            />
            <AppFormField
              :model-value="fv('current_number')"
              @update:model-value="(v: string | number | null) => form.current_number = v"
              label="Próximo número"
              type="number"
              icon="hash"
              placeholder="1"
              :disabled="saving"
              :min="1"
              :error="fieldErrors.current_number"
              @blur="validateField('current_number')"
              @input="clearError('current_number')"
            />
          </div>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="saving || !form.document_code || hasErrors" @click="handleSave">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar secuencia"
      :message="`¿Seguro que querés eliminar la secuencia ${deleting?.document_code}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
