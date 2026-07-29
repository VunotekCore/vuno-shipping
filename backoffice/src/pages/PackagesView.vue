<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { packageService, type Package } from '../services/packageService'
import { formatCurrency, statusColor } from '../utils/format'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'

const router = useRouter()
const packages = ref<Package[]>([])
const loading = ref(false)
const showDelete = ref(false)
const deleting = ref<Package | null>(null)
const statusFilter = ref('')
const page = ref(1)
const limit = ref(20)
const total = ref(0)
const pages = ref(0)

async function load() {
  loading.value = true
  try {
    const res = await packageService.list({ page: page.value, limit: limit.value, status: statusFilter.value || undefined })
    const d = res.data.data
    packages.value = d?.packages ?? []
    total.value = d?.pagination?.total ?? 0
    pages.value = d?.pagination?.pages ?? 0
  } finally {
    loading.value = false
  }
}

function goToPage(p: number) {
  page.value = p
  load()
}

function setLimit(l: number) {
  limit.value = l
  page.value = 1
  load()
}

function filterByStatus(status: string) {
  statusFilter.value = status
  page.value = 1
  load()
}

function viewDetail(pkg: Package) {
  router.push(`/packages/${pkg.id}`)
}

function openDelete(pkg: Package) {
  deleting.value = pkg
  showDelete.value = true
}

async function confirmDelete() {
  if (!deleting.value) return
  await packageService.delete(deleting.value.id)
  showDelete.value = false
  deleting.value = null
  load()
}

const columns: TableColumn[] = [
  { key: 'tracking_number', label: 'Tracking', width: '130px' },
  { key: 'recipient_name', label: 'Destinatario', width: '140px' },
  { key: 'recipient_department', label: 'Depto', width: '100px' },
  { key: 'status_name', label: 'Estado', width: '100px' },
  { key: 'flete_cost', label: 'Flete', width: '80px' },
  { key: 'delivery_cost', label: 'Delivery', width: '80px' },
  { key: 'shipping_cost', label: 'Total', width: '80px' },
  { key: 'shipping_service_name', label: 'Servicio', width: '80px' },
  { key: 'delivery_option_name', label: 'Entrega', width: '80px' },
  { key: 'actions', label: '', width: '130px', align: 'right' },
]

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-[#dae2fd]">Paquetes</h1>
      <p class="mt-1 text-sm text-[#64748b]">Consulta y gestión de envíos</p>
    </div>

    <!-- Status filter tabs -->
    <div class="flex flex-wrap gap-2">
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === '' && 'bg-white/[0.08]'"
        @click="filterByStatus('')"
      >Todos</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'pending' && 'bg-white/[0.08]'"
        @click="filterByStatus('pending')"
      >Pendientes</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'in_transit' && 'bg-white/[0.08]'"
        @click="filterByStatus('in_transit')"
      >En tránsito</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'delivered' && 'bg-white/[0.08]'"
        @click="filterByStatus('delivered')"
      >Entregados</button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="packages.length === 0" empty-title="Sin paquetes" empty-desc="Creá el primer paquete del sistema.">
          <tr v-for="p in packages" :key="p.id">
            <td class="font-mono font-medium text-[#dae2fd]">{{ p.tracking_number }}</td>
            <td class="text-[#94a3b8]">{{ p.recipient_name }}</td>
            <td class="text-[#94a3b8]">{{ p.recipient_department ?? '—' }}</td>
            <td>
              <span class="admin-badge" :class="statusColor(p.status_name)">{{ p.status_name ?? '—' }}</span>
            </td>
            <td class="text-[#94a3b8] text-xs" :title="'Costo del envío internacional Miami → Nicaragua'">{{ formatCurrency(p.flete_cost) }}</td>
            <td class="text-[#94a3b8] text-xs" :title="'Costo de entrega a domicilio en Nicaragua (última milla)'">{{ formatCurrency(p.delivery_cost) }}</td>
            <td class="text-[#94a3b8] font-medium" :title="'Total = flete + delivery. Monto final que paga el cliente'">{{ formatCurrency(p.shipping_cost) }}</td>
            <td class="text-[#94a3b8]">{{ p.shipping_service_name ?? '—' }}</td>
            <td class="text-[#94a3b8]">{{ p.delivery_option_name ?? '—' }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="viewDetail(p)" title="Ver detalle">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button
                  class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]"
                  @click="openDelete(p)"
                  title="Eliminar"
                  :disabled="p.status_name !== 'pending'"
                >
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
          <div class="h-3 w-1/4 animate-pulse rounded bg-[#1e293b]" />
          <div class="border-t border-[#1e293b] pt-3">
            <div class="flex gap-2">
              <div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" />
              <div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" />
              <div class="h-7 w-16 animate-pulse rounded bg-[#1e293b]" />
            </div>
          </div>
        </div>
      </template>
      <template v-else-if="packages.length > 0">
        <div v-for="p in packages" :key="p.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-mono font-medium text-[#dae2fd] truncate">{{ p.tracking_number }}</p>
            <span class="admin-badge shrink-0" :class="statusColor(p.status_name)">{{ p.status_name ?? '—' }}</span>
          </div>
          <p class="text-sm text-[#94a3b8] truncate">{{ p.recipient_name }}{{ p.recipient_department ? ' · ' + p.recipient_department : '' }}</p>
          <p class="text-xs text-[#64748b]" title="Flete (envío internacional) + Delivery (última milla)">
            Flete: {{ formatCurrency(p.flete_cost) }} | Delivery: {{ formatCurrency(p.delivery_cost) }} | Total: {{ formatCurrency(p.shipping_cost) }}
            · {{ p.shipping_service_name ?? '—' }} · {{ p.delivery_option_name ?? '—' }}
          </p>
          <div class="border-t border-[#1e293b] pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="viewDetail(p)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              Ver
            </button>
            <button
              class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]"
              @click="openDelete(p)"
              :disabled="p.status_name !== 'pending'"
            >
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
          <p class="admin-empty-state-title">Sin paquetes</p>
          <p class="admin-empty-state-desc">Creá el primer paquete del sistema.</p>
        </div>
      </div>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Delete Confirmation -->
    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar paquete"
      :message="`¿Seguro que querés eliminar el paquete ${deleting?.tracking_number}? Solo se pueden eliminar paquetes con estado pendiente. Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
