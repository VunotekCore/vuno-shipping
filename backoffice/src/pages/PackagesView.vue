<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { packageService, type Package } from '../services/packageService'
import { formatCurrency, statusColor, statusLabel } from '../utils/format'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const router = useRouter()
const backofficeUrl = window.location.origin
const packages = ref<Package[]>([])
const loading = ref(false)
const showDelete = ref(false)
const deleting = ref<Package | null>(null)
const showQrModal = ref(false)
const qrPackage = ref<Package | null>(null)
const qrError = ref(false)
const statusFilter = ref('')
const page = ref(1)
function pushScan(pkg: Package) {
  router.push(`/scan?tracking=${pkg.tracking_number}`)
}
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

function openQr(pkg: Package) {
  qrPackage.value = pkg
  qrError.value = false
  showQrModal.value = true
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
  { key: 'invoice_status', label: 'Pago', width: '90px' },
  { key: 'actions', label: '', width: '130px', align: 'right' },
]

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Paquetes" subtitle="Consulta y gestión de envíos" />

    <!-- Status filter tabs -->
    <div class="flex flex-wrap gap-2">
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === '' && 'bg-primary-soft'"
        @click="filterByStatus('')"
      >Todos</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'pending' && 'bg-primary-soft'"
        @click="filterByStatus('pending')"
      >Pendientes</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'received' && 'bg-primary-soft'"
        @click="filterByStatus('received')"
      >En Miami</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'in_transit' && 'bg-primary-soft'"
        @click="filterByStatus('in_transit')"
      >En tránsito</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'in_nicaragua' && 'bg-primary-soft'"
        @click="filterByStatus('in_nicaragua')"
      >En Nicaragua</button>
      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        :class="statusFilter === 'delivered' && 'bg-primary-soft'"
        @click="filterByStatus('delivered')"
      >Entregados</button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="packages.length === 0" empty-title="Sin paquetes" empty-desc="Creá el primer paquete del sistema.">
          <tr v-for="p in packages" :key="p.id">
            <td class="font-mono font-medium text-content">{{ p.tracking_number }}</td>
            <td class="text-muted">{{ p.recipient_name }}</td>
            <td class="text-muted">{{ p.recipient_department ?? '—' }}</td>
            <td>
              <span class="admin-badge" :class="statusColor(p.status_name)">{{ statusLabel(p.status_name) }}</span>
            </td>
            <td class="text-muted text-xs" :title="'Costo del envío internacional Miami → Nicaragua'">{{ formatCurrency(p.flete_cost) }}</td>
            <td class="text-muted text-xs" :title="'Costo de entrega a domicilio en Nicaragua (última milla)'">{{ formatCurrency(p.delivery_cost) }}</td>
            <td class="text-muted font-medium" :title="'Total = flete + delivery. Monto final que paga el cliente'">{{ formatCurrency(p.shipping_cost) }}</td>
            <td class="text-muted">{{ p.shipping_service_name ?? '—' }}</td>
            <td class="text-muted">{{ p.delivery_option_name ?? '—' }}</td>
            <td>
              <button
                v-if="p.invoice_id"
                class="admin-badge"
                :class="p.invoice_status === 'paid' ? 'admin-badge-success' : p.invoice_status === 'cancelled' ? 'admin-badge-danger' : 'admin-badge-warning'"
                @click="router.push({ name: 'invoice-detail', params: { id: p.invoice_id } })"
                :title="`Ver factura ${p.invoice_number ?? ''}`"
              >
                {{ p.invoice_status === 'paid' ? 'Pagada' : p.invoice_status === 'cancelled' ? 'Anulada' : 'Por cobrar' }}
              </button>
              <span v-else class="text-subtle text-xs">Sin factura</span>
            </td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="viewDetail(p)" title="Ver detalle">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openQr(p)" title="Ver QR">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1" />
                    <rect x="14" y="3" width="7" height="7" rx="1" />
                    <rect x="3" y="14" width="7" height="7" rx="1" />
                    <rect x="14" y="14" width="7" height="7" rx="1" />
                  </svg>
                </button>
                <button
                  class="admin-btn admin-btn-ghost admin-btn-sm text-danger"
                  @click="openDelete(p)"
                  title="Eliminar"
                  :disabled="!['pending','received','in_nicaragua'].includes(p.status_name ?? '')"
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
            <div class="h-4 w-1/3 animate-pulse rounded bg-surface-raised" />
            <div class="h-5 w-16 animate-pulse rounded-full bg-surface-raised" />
          </div>
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-surface-raised" />
          <div class="h-3 w-1/4 animate-pulse rounded bg-surface-raised" />
          <div class="border-t border-line pt-3">
            <div class="flex gap-2">
              <div class="h-7 w-16 animate-pulse rounded bg-surface-raised" />
              <div class="h-7 w-16 animate-pulse rounded bg-surface-raised" />
              <div class="h-7 w-16 animate-pulse rounded bg-surface-raised" />
            </div>
          </div>
        </div>
      </template>
      <template v-else-if="packages.length > 0">
        <div v-for="p in packages" :key="p.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-mono font-medium text-content truncate">{{ p.tracking_number }}</p>
            <div class="flex shrink-0 items-center gap-1.5">
              <span
                v-if="p.invoice_id"
                class="admin-badge"
                :class="p.invoice_status === 'paid' ? 'admin-badge-success' : p.invoice_status === 'cancelled' ? 'admin-badge-danger' : 'admin-badge-warning'"
                @click="router.push({ name: 'invoice-detail', params: { id: p.invoice_id } })"
              >{{ p.invoice_status === 'paid' ? 'Pagada' : p.invoice_status === 'cancelled' ? 'Anulada' : 'Por cobrar' }}</span>
              <span class="admin-badge" :class="statusColor(p.status_name)">{{ statusLabel(p.status_name) }}</span>
            </div>
          </div>
          <p class="text-sm text-muted truncate">{{ p.recipient_name }}{{ p.recipient_department ? ' · ' + p.recipient_department : '' }}</p>
          <p class="text-xs text-subtle" title="Flete (envío internacional) + Delivery (última milla)">
            Flete: {{ formatCurrency(p.flete_cost) }} | Delivery: {{ formatCurrency(p.delivery_cost) }} | Total: {{ formatCurrency(p.shipping_cost) }}
            · {{ p.shipping_service_name ?? '—' }} · {{ p.delivery_option_name ?? '—' }}
          </p>
          <div class="border-t border-line pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="viewDetail(p)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              Ver
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openQr(p)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1" />
                <rect x="14" y="3" width="7" height="7" rx="1" />
                <rect x="3" y="14" width="7" height="7" rx="1" />
                <rect x="14" y="14" width="7" height="7" rx="1" />
              </svg>
              QR
            </button>
            <button
              class="admin-btn admin-btn-ghost admin-btn-sm text-danger"
              @click="openDelete(p)"
              :disabled="!['pending','received','in_nicaragua'].includes(p.status_name ?? '')"
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
      :message="`¿Seguro que querés eliminar el paquete ${deleting?.tracking_number}? Solo se pueden eliminar paquetes pendientes, en Miami o en Nicaragua sin despachar. Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
    <!-- QR Modal -->
    <AppModal :visible="showQrModal" :title="`QR — ${qrPackage?.tracking_number ?? ''}`" @close="showQrModal = false" size="sm">
      <div class="flex flex-col items-center py-4">
        <img
          v-if="qrPackage"
          :src="packageService.getQrUrl(qrPackage.id)"
          alt="QR del paquete"
          width="240"
          height="240"
          class="rounded-lg border border-line"
          @error="qrError = true"
        />
        <p v-if="qrError" class="text-sm text-danger mt-4">Error al cargar el código QR</p>
        <p class="mt-4 text-xs text-subtle text-center break-all">
          {{ backofficeUrl }}/scan?tracking={{ qrPackage?.tracking_number ?? '' }}
        </p>
        <button
          v-if="qrPackage"
          @click="pushScan(qrPackage)"
          class="admin-btn admin-btn-ghost admin-btn-sm mt-3 inline-flex items-center gap-1.5"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
          Abrir en scan
        </button>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showQrModal = false">Cerrar</button>
          <a
            v-if="qrPackage"
            :href="packageService.getQrUrl(qrPackage.id)"
            download
            class="admin-btn admin-btn-primary inline-flex items-center gap-1.5"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" />
            </svg>
            Descargar
          </a>
        </div>
      </template>
    </AppModal>
  </div>
</template>
