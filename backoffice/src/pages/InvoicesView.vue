<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { invoiceService, type Invoice } from '../services/invoiceService'
import { paymentMethodService, type PaymentMethod } from '../services/paymentMethodService'
import { formatUSD, formatCord, formatRate, usdToCord, paidCurrencyLabel } from '../utils/format'
import { useToast } from '../composables/useToast'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import InvoicePrintModal from '../components/ui/InvoicePrintModal.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const router = useRouter()
const toast = useToast()

const invoices = ref<Invoice[]>([])
const loading = ref(true)
const paginating = ref(false)

const page = ref(1)
const limit = ref(20)
const total = ref(0)
const pages = ref(0)

const filters = reactive<{ status: string }>({ status: '' })

const summary = ref({ pending_total: 0, paid_today_usd: 0, paid_today_cord: 0 })
const exchangeRateUsd = ref(36)
const printing = ref<number | null>(null)

const methods = ref<PaymentMethod[]>([])

const paying = ref<Invoice | null>(null)
const payForm = reactive<{ payment_method_id: number; paid_currency: 'USD' | 'CORD'; payment_reference: string }>({
  payment_method_id: 0,
  paid_currency: 'USD',
  payment_reference: '',
})
const payingBusy = ref(false)

const cancelTarget = ref<Invoice | null>(null)
const cancelBusy = ref(false)

const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'pending', label: 'Pendientes' },
  { value: 'paid', label: 'Pagadas' },
  { value: 'cancelled', label: 'Anuladas' },
]

const statusInfo: Record<string, { label: string; cls: string }> = {
  pending: { label: 'Pendiente', cls: 'admin-badge-warning' },
  paid: { label: 'Pagada', cls: 'admin-badge-success' },
  cancelled: { label: 'Anulada', cls: 'admin-badge-danger' },
}

/** Preview del modal: importe en la moneda que el despachador eligio. */
const payPreview = computed(() => {
  const total = paying.value?.total_amount ?? 0
  return payForm.paid_currency === 'CORD'
    ? { amount: usdToCord(total, exchangeRateUsd.value), currency: 'CORD' as const }
    : { amount: total, currency: 'USD' as const }
})

function fmtDate(s: string | null | undefined): string {
  if (!s) return '—'
  const d = new Date(s.replace(' ', 'T'))
  return d.toLocaleDateString('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString('es-NI', { hour: '2-digit', minute: '2-digit' })
}

const columns: TableColumn[] = [
  { key: 'invoice_number', label: 'Factura', width: '120px' },
  { key: 'tracking_number', label: 'Paquete', width: '120px' },
  { key: 'client', label: 'Cliente' },
  { key: 'total', label: 'Total', width: '100px', align: 'right' },
  { key: 'status', label: 'Estado', width: '110px' },
  { key: 'created_at', label: 'Fecha', width: '140px' },
  { key: 'actions', label: '', width: '130px', align: 'right' },
]

const invoiceName = computed(() => (iv: Invoice) => iv.client_name || iv.client_name_ref || '—')
const isPending = (iv: Invoice) => iv.status === 'pending'

async function load() {
  loading.value = true
  try {
    const { data } = await invoiceService.list({
      page: page.value,
      limit: limit.value,
      status: (filters.status || undefined) as never,
    })
    invoices.value = data.data.invoices ?? []
    summary.value = data.data.summary ?? { pending_total: 0, paid_today_usd: 0, paid_today_cord: 0 }
    const rate = Number(data.data.exchange_rate_usd)
    if (Number.isFinite(rate) && rate > 0) exchangeRateUsd.value = rate
    total.value = data.data.pagination?.total ?? 0
    pages.value = data.data.pagination?.pages ?? 0
  } catch {
    toast.error('Error al cargar facturas')
  } finally {
    loading.value = false
  }
}

function goToPage(n: number) {
  page.value = n
  paginating.value = true
  load().finally(() => (paginating.value = false))
}
function setLimit(l: number) {
  limit.value = l
  page.value = 1
  load()
}

watch(() => filters.status, () => { page.value = 1; load() })

function openDetail(id: number) {
  router.push({ name: 'invoice-detail', params: { id } })
}

function openPay(iv: Invoice) {
  paying.value = iv
  payForm.payment_method_id = methods.value[0]?.id ?? 0
  payForm.paid_currency = 'USD'
  payForm.payment_reference = ''
}

async function confirmPay() {
  if (!paying.value) return
  if (!payForm.payment_method_id) { toast.error('Seleccioná el método de pago'); return }
  payingBusy.value = true
  try {
    const { data } = await invoiceService.pay({
      id: paying.value.id,
      payment_method_id: payForm.payment_method_id,
      paid_currency: payForm.paid_currency,
      payment_reference: payForm.payment_reference,
    })
    toast.success(data.message || 'Cobro registrado')
    paying.value = null
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al registrar el cobro')
  } finally {
    payingBusy.value = false
  }
}

function openCancel(iv: Invoice) {
  cancelTarget.value = iv
}

async function confirmCancel() {
  if (!cancelTarget.value) return
  cancelBusy.value = true
  try {
    await invoiceService.cancel(cancelTarget.value.id)
    toast.success('Factura anulada')
    cancelTarget.value = null
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al anular la factura')
  } finally {
    cancelBusy.value = false
  }
}

function openPrint(id: number) {
  printing.value = id
}

onMounted(async () => {
  load()
  try {
    const res = await paymentMethodService.listAll()
    methods.value = res.data.data.methods ?? []
  } catch {
    methods.value = []
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- Page header -->
    <PageHeader title="Facturas" subtitle="Cobros y facturación de envíos" />

    <!-- Summary cards: el cobrado va separado por moneda porque `paid_amount`
         esta expresado en la moneda del cobro y sumarlos mezclaria USD con C$ -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <div class="admin-card p-4 flex items-center gap-4">
        <div class="h-10 w-10 rounded-xl bg-warning/20 flex items-center justify-center">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        </div>
        <div>
          <p class="text-xs text-subtle">Por cobrar (pendiente, USD)</p>
          <p class="text-lg font-bold text-warning">{{ formatUSD(summary.pending_total) }}</p>
        </div>
      </div>
      <div class="admin-card p-4 flex items-center gap-4">
        <div class="h-10 w-10 rounded-xl bg-success/20 flex items-center justify-center">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
        </div>
        <div>
          <p class="text-xs text-subtle">Cobrado hoy (USD)</p>
          <p class="text-lg font-bold text-success">{{ formatUSD(summary.paid_today_usd) }}</p>
        </div>
      </div>
      <div class="admin-card p-4 flex items-center gap-4">
        <div class="h-10 w-10 rounded-xl bg-success/20 flex items-center justify-center">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
        </div>
        <div>
          <p class="text-xs text-subtle">Cobrado hoy (Córdobas)</p>
          <p class="text-lg font-bold text-success">{{ formatCord(summary.paid_today_cord) }}</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="admin-card px-4 py-3">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="max-w-xs">
          <AppDropdown
            :model-value="filters.status"
            @update:model-value="(v: string | number | null) => filters.status = String(v ?? '')"
            :options="statusOptions"
            placeholder="Filtrar por estado"
          />
        </div>
        <p class="text-xs text-subtle">{{ total }} facturas</p>
      </div>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading || paginating" :is-empty="invoices.length === 0" empty-title="Sin facturas" empty-desc="Las facturas se generan automáticamente al admitir un paquete.">
          <tr v-for="iv in invoices" :key="iv.id">
            <td class="font-medium text-content">{{ iv.invoice_number }}</td>
            <td class="text-muted">{{ iv.tracking_number || '—' }}</td>
            <td class="text-muted">{{ invoiceName(iv) }}</td>
            <td class="text-right font-medium text-content">{{ formatUSD(iv.total_amount) }}</td>
            <td>
              <span class="admin-badge" :class="statusInfo[iv.status]?.cls || ''">{{ statusInfo[iv.status]?.label || iv.status }}</span>
            </td>
            <td class="text-muted">{{ fmtDate(iv.created_at) }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openDetail(iv.id)" title="Ver detalle">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button v-if="isPending(iv)" class="admin-btn admin-btn-ghost admin-btn-sm text-success" @click="openPay(iv)" title="Cobrar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                </button>
                <button v-if="isPending(iv)" class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openCancel(iv)" title="Anular">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openPrint(iv.id)" title="Imprimir">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
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
      <template v-if="loading || paginating">
        <div v-for="i in 3" :key="i" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between">
            <div class="h-4 w-1/3 animate-pulse rounded bg-surface-raised" />
            <div class="h-5 w-16 animate-pulse rounded-full bg-surface-raised" />
          </div>
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-surface-raised" />
          <div class="border-t border-line pt-3">
            <div class="flex gap-2">
              <div class="h-7 w-16 animate-pulse rounded bg-surface-raised" />
              <div class="h-7 w-16 animate-pulse rounded bg-surface-raised" />
            </div>
          </div>
        </div>
      </template>

      <template v-else-if="invoices.length > 0">
        <div v-for="iv in invoices" :key="iv.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-content truncate">{{ iv.invoice_number }}</p>
            <span class="admin-badge shrink-0" :class="statusInfo[iv.status]?.cls || ''">{{ statusInfo[iv.status]?.label || iv.status }}</span>
          </div>
          <p class="text-sm text-muted truncate">{{ invoiceName(iv) }} · {{ iv.tracking_number || '—' }}</p>
          <div class="flex items-center justify-between">
            <p class="text-base font-bold text-content">{{ formatUSD(iv.total_amount) }}</p>
            <p class="text-xs text-subtle">{{ fmtDate(iv.created_at) }}</p>
          </div>
          <div class="border-t border-line pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openDetail(iv.id)">Ver</button>
            <button v-if="isPending(iv)" class="admin-btn admin-btn-ghost admin-btn-sm text-success" @click="openPay(iv)">Cobrar</button>
            <button v-if="isPending(iv)" class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openCancel(iv)">Anular</button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openPrint(iv.id)">Imprimir</button>
          </div>
        </div>
      </template>

      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8" />
          </svg>
          <p class="admin-empty-state-title">Sin facturas</p>
          <p class="admin-empty-state-desc">Las facturas se generan automáticamente al admitir un paquete.</p>
        </div>
      </div>

      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Pay Modal -->
    <AppModal :visible="paying !== null" :title="`Cobrar ${paying?.invoice_number || ''}`" size="md" @close="paying = null">
      <div class="space-y-4">
        <div class="rounded-xl border border-line bg-surface-sunken p-4">
          <div class="flex items-center justify-between">
            <span class="text-sm text-muted">
              Total a cobrar ({{ paidCurrencyLabel(payForm.paid_currency) }})
            </span>
            <span class="text-xl font-bold text-content">
              {{ payPreview.currency === 'CORD' ? formatCord(payPreview.amount) : formatUSD(payPreview.amount) }}
            </span>
          </div>
          <p class="mt-1 text-right text-xs text-subtle">
            Equivalente facturado: {{ formatUSD(paying?.total_amount ?? 0) }}
            <template v-if="paying?.tax_amount"> · incluye IVA {{ formatUSD(paying.tax_amount) }}</template>
          </p>
          <p v-if="payPreview.currency === 'CORD'" class="mt-1 text-right text-xs text-subtle">
            Tasa vigente: C${{ formatRate(exchangeRateUsd) }} por USD
          </p>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-muted">Moneda del cobro</label>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              :class="['admin-btn', payForm.paid_currency === 'USD' ? 'admin-btn-primary' : 'admin-btn-ghost']"
              @click="payForm.paid_currency = 'USD'"
            >USD ($)</button>
            <button
              type="button"
              :class="['admin-btn', payForm.paid_currency === 'CORD' ? 'admin-btn-primary' : 'admin-btn-ghost']"
              @click="payForm.paid_currency = 'CORD'"
            >Córdobas (C$)</button>
          </div>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-muted">Método de pago</label>
          <AppDropdown
            :model-value="payForm.payment_method_id || ''"
            @update:model-value="(v: string | number | null) => payForm.payment_method_id = Number(v ?? 0)"
            :options="methods.map(m => ({ value: m.id, label: m.name }))"
            placeholder="Seleccionar método"
          />
          <p class="mt-2 text-xs text-subtle">
            Se registrará el cobro en
            <strong class="text-muted">
              {{ payPreview.currency === 'CORD' ? formatCord(payPreview.amount) : formatUSD(payPreview.amount) }}
            </strong>.
            La tasa queda congelada en la factura al confirmar.
          </p>
        </div>

        <AppFormField
          :model-value="payForm.payment_reference"
          @update:model-value="(v: string | number | null) => payForm.payment_reference = String(v ?? '')"
          label="Referencia (opcional)"
          placeholder="Nº de transferencia, comprobante, etc."
          :disabled="payingBusy"
        />
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="paying = null" :disabled="payingBusy">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="confirmPay" :disabled="payingBusy || !payForm.payment_method_id">
            {{ payingBusy ? 'Registrando...' : 'Registrar cobro' }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Cancel Confirmation -->
    <AppConfirmDialog
      :visible="cancelTarget !== null"
      title="Anular factura"
      :message="`¿Seguro que querés anular la factura ${cancelTarget?.invoice_number}? No se puede deshacer.`"
      confirm-text="Anular"
      variant="danger"
      @confirm="confirmCancel"
      @cancel="cancelTarget = null"
    />

    <!-- Print -->
    <InvoicePrintModal
      :visible="printing !== null"
      :invoice-id="printing"
      :invoice-number="printing ? invoices.find(i => i.id === printing)?.invoice_number : undefined"
      @close="printing = null"
    />
  </div>
</template>