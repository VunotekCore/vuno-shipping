<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { permissionService, type Permission } from '../services/permissionService'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const toast = useToast()

const permissions = ref<Permission[]>([])
const loading = ref(true)
const syncing = ref(false)
const showDelete = ref(false)
const deleting = ref<Permission | null>(null)
const saving = ref(false)

const page = ref(1)
const limit = ref(50)
const total = ref(0)
const pages = ref(0)

const grouped = computed(() => {
  const map = new Map<string, Permission[]>()
  for (const p of permissions.value) {
    if (!map.has(p.module)) map.set(p.module, [])
    map.get(p.module)!.push(p)
  }
  return map
})

const columns: TableColumn[] = [
  { key: 'module', label: 'Módulo', width: '150px' },
  { key: 'action', label: 'Acción', width: '150px' },
  { key: 'label', label: 'Etiqueta' },
  { key: 'actions', label: '', width: '60px', align: 'right' },
]

async function load() {
  loading.value = true
  try {
    const { data } = await permissionService.list({ page: page.value, limit: limit.value })
    permissions.value = data.data?.permissions ?? []
    const p = data.data?.pagination
    if (p) {
      total.value = p.total
      pages.value = p.pages
    }
  } catch {
    toast.error('Error al cargar permisos')
  } finally {
    loading.value = false
  }
}

function goToPage(n: number) {
  page.value = n
  load()
}

function setLimit(newLimit: number) {
  limit.value = newLimit
  page.value = 1
  load()
}

async function sync() {
  syncing.value = true
  try {
    const { data } = await permissionService.sync()
    const result = data.data
    if (result.created_count > 0) {
      toast.success(`${result.created_count} permisos nuevos creados y asignados al admin`)
    } else {
      toast.info('No hay permisos nuevos por sincronizar')
    }
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al sincronizar permisos')
  } finally {
    syncing.value = false
  }
}

function openDelete(item: Permission) {
  deleting.value = item
  showDelete.value = true
}

async function confirmDelete() {
  if (!deleting.value) return
  saving.value = true
  try {
    await permissionService.delete(deleting.value.id)
    toast.success('Permiso eliminado')
    showDelete.value = false
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al eliminar permiso')
  } finally {
    saving.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Permisos</h1>
        <p class="mt-1 text-sm text-[#64748b]">Permisos del sistema RBAC — generados desde la base de datos</p>
      </div>
      <button class="admin-btn admin-btn-primary" :disabled="syncing" @click="sync">
        <svg
          width="16" height="16" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
          :class="{ 'animate-spin': syncing }"
        >
          <path d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9" />
        </svg>
        {{ syncing ? 'Sincronizando...' : 'Sincronizar permisos' }}
      </button>
    </div>

    <div class="admin-card">
      <AppTable
        :columns="columns"
        :loading="loading"
        :is-empty="permissions.length === 0"
        empty-title="Sin permisos"
        empty-desc="Usá el botón sincronizar para generar permisos desde las tablas de la base de datos."
      >
        <template v-for="[module, perms] in grouped" :key="module">
          <tr class="bg-[#111d2e]/50">
            <td :colspan="4" class="px-4 py-2">
              <span class="text-xs font-semibold uppercase tracking-wider text-[#64748b]">{{ module }}</span>
            </td>
          </tr>
          <tr v-for="p in perms" :key="p.id">
            <td>
              <span class="admin-badge admin-badge-info">{{ p.module }}</span>
            </td>
            <td>
              <span class="admin-badge admin-badge-success">{{ p.action }}</span>
            </td>
            <td class="text-[#94a3b8]">{{ p.label || '—' }}</td>
            <td class="text-right">
              <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" title="Eliminar" @click="openDelete(p)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </td>
          </tr>
        </template>
      </AppTable>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar permiso"
      :message="`¿Seguro que querés eliminar el permiso ${deleting?.module}.${deleting?.action}? Podés regenerarlo con el botón sincronizar.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
