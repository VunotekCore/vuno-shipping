<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { invoiceService, type Invoice } from '../services/invoiceService'
import { paymentMethodService, type PaymentMethod } from '../services/paymentMethodService'
import { useToast } from '../composables/useToast'
import AppModal from '../components/ui/AppModal.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const invoice = ref<Invoice | null>(null)
const loading = ref(true)
const methods = ref<PaymentMethod[]>([])

const payForm = ref<{ payment_method_id: number; paid_currency: 'USD' | 'CORD'; payment_reference: string }>({
  payment_method_id: 0,
  paid_currency: 'USD',
  payment_reference: '',
})
const payingBusy = ref(false)
const showPay = ref(false)
const showCancel = ref(false)
const cancelBusy = ref(false)

const statusInfo: Record<string, { label: string; cls: string }> = {
  pending: { label: 'Pendiente', cls: 'admin-badge-warning' },
  paid: { label: 'Pagada', cls: 'admin-badge-success' },
  cancelled: { label: 'Anulada', cls: 'admin-badge-danger' },
}

const clientName = computed(() => invoice.value?.client_name || invoice.value?.client_name_ref || '—')

function fmtUSD(n: number | null | undefined): string {
  return '$' + Number(n ?? 0).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function fmtCord(n: number | null | undefined): string {
  return 'C$' + Number(n ?? 0).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function fmtDate(s: string | null | undefined): string {
  if (!s) return '—'
  const d = new Date(s.replace(' ', 'T'))
  return d.toLocaleDateString('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString('es-NI', { hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  try {
    const id = Number(route.params.id)
    const { data } = await invoiceService.get(id)
    invoice.value = data.data?.invoice ?? null
  } catch {
    toast.error('Error al cargar la factura')
  } finally {
    loading.value = false
  }
}

function openPay() {
  if (!invoice.value) return
  payForm.value = {
    payment_method_id: methods.value[0]?.id ?? 0,
    paid_currency: 'USD',
    payment_reference: '',
  }
  showPay.value = true
}

async function confirmPay() {
  if (!invoice.value) return
  if (!payForm.value.payment_method_id) { toast.error('Seleccioná el método de pago'); return }
  payingBusy.value = true
  try {
    const { data } = await invoiceService.pay({
      id: invoice.value.id,
      payment_method_id: payForm.value.payment_method_id,
      paid_currency: payForm.value.paid_currency,
      payment_reference: payForm.value.payment_reference,
    })
    toast.success(data.message || 'Cobro registrado')
    resetPayModal()
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al registrar el cobro')
  } finally {
    payingBusy.value = false
  }
}

function resetPayModal() {
  showPay.value = false
  payForm.value = { payment_method_id: 0, paid_currency: 'USD', payment_reference: '' }
}

async function confirmCancel() {
  if (!invoice.value) return
  cancelBusy.value = true
  try {
    await invoiceService.cancel(invoice.value.id)
    toast.success('Factura anulada')
    showCancel.value = false
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al anular la factura')
  } finally {
    cancelBusy.value = false
  }
}

function printInvoice() {
  if (invoice.value) window.open(invoiceService.printUrl(invoice.value.id), '_blank')
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
    <template v-if="loading">
      <div class="flex items-center gap-3">
        <div class="h-8 w-8 animate-pulse rounded-lg bg-[#1e293b]" />
        <div class="h-6 w-48 animate-pulse rounded bg-[#1e293b]" />
      </div>
      <div class="admin-card p-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div v-for="i in 6" :key="i" class="space-y-2">
            <div class="h-3 w-16 animate-pulse rounded bg-[#1e293b]" />
            <div class="h-4 w-full animate-pulse rounded bg-[#1e293b]" />
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="invoice">
      <!-- Page header -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
          <button
            class="admin-btn admin-btn-ghost admin-btn-sm"
            @click="router.push('/facturas')"
            title="Volver"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 12H5M12 19l-7-7 7-7" />
            </svg>
          </button>
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-xl font-bold text-[#dae2fd] font-mono">{{ invoice.invoice_number }}</h1>
              <span class="admin-badge" :class="statusInfo[invoice.status]?.cls || ''">
                {{ statusInfo[invoice.status]?.label || invoice.status }}
              </span>
            </div>
            <p class="mt-1 text-sm text-[#64748b]">Factura de envío — {{ fmtDate(invoice.created_at) }}</p>
          </div>
        </div>
        <div class="flex gap-2 sm:w-auto w-full sm:justify-end flex-wrap">
          <button class="admin-btn admin-btn-ghost" @click="printInvoice">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
            Imprimir
          </button>
          <button v-if="invoice.status === 'pending'" class="admin-btn admin-btn-primary" @click="openPay">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            Registrar cobro
          </button>
          <button v-if="invoice.status === 'pending'" class="admin-btn admin-btn-danger" @click="showCancel = true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            Anular
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Invoice info -->
        <div class="lg:col-span-2">
          <div class="admin-card p-6">
            <h2 class="text-sm font-semibold text-[#dae2fd] mb-4">Información de la factura</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <p class="text-xs text-[#64748b] mb-1">Cliente</p>
                <p class="text-sm text-[#94a3b8]">{{ clientName }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">RUC</p>
                <p class="text-sm text-[#94a3b8]">{{ invoice.client_ruc || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Paquete</p>
                <button
                  class="admin-btn admin-btn-ghost admin-btn-sm font-mono"
                  @click="router.push({ name: 'package-detail', params: { id: invoice.package_id } })"
                >
                  {{ invoice.tracking_number || `#${invoice.package_id}` }}
                </button>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Moneda</p>
                <p class="text-sm text-[#94a3b8]">{{ invoice.currency }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Creada</p>
                <p class="text-sm text-[#94a3b8]">{{ fmtDate(invoice.created_at) }}</p>
              </div>
              <div v-if="invoice.status === 'paid'">
                <p class="text-xs text-[#64748b] mb-1">Pagada</p>
                <p class="text-sm text-[#22c55e]">{{ fmtDate(invoice.paid_at) }}</p>
              </div>
            </div>

            <!-- Cost breakdown -->
            <div class="border-t border-[#1e293b] pt-4 mt-4">
              <p class="text-xs text-[#64748b] mb-3">Desglose</p>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-[#94a3b8]">Envío (base)</span>
                  <span class="text-[#dae2fd]">{{ fmtUSD(invoice.base_amount) }}</span>
                </div>
                <div v-if="invoice.tax_rate > 0" class="flex justify-between text-sm">
                  <span class="text-[#94a3b8]">IVA {{ invoice.tax_rate }}%</span>
                  <span class="text-[#dae2fd]">{{ fmtUSD(invoice.tax_amount) }}</span>
                </div>
                <div class="flex justify-between text-base font-bold border-t border-[#1e293b] pt-2 mt-2">
                  <span class="text-[#dae2fd]">Total</span>
                  <span class="text-[#dae2fd]">{{ fmtUSD(invoice.total_amount) }}</span>
                </div>
              </div>
            </div>

            <!-- Payment info -->
            <div v-if="invoice.status === 'paid'" class="border-t border-[#1e293b] pt-4 mt-4">
              <p class="text-xs text-[#64748b] mb-3">Datos del cobro</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-[#64748b] mb-1">Monto cobrado</p>
                  <p class="text-sm font-medium text-[#22c55e]">
                    {{ invoice.paid_currency === 'CORD' ? fmtCord(invoice.paid_amount) : fmtUSD(invoice.paid_amount) }}
                  </p>
                </div>
                <div>
                  <p class="text-xs text-[#64748b] mb-1">Método</p>
                  <p class="text-sm text-[#94a3b8]">{{ invoice.payment_method_name || '—' }}</p>
                </div>
                <div v-if="invoice.exchange_rate">
                  <p class="text-xs text-[#64748b] mb-1">Tasa de cambio</p>
                  <p class="text-sm text-[#94a3b8]">C${{ invoice.exchange_rate }} por USD</p>
                </div>
                <div>
                  <p class="text-xs text-[#64748b] mb-1">Referencia</p>
                  <p class="text-sm text-[#94a3b8]">{{ invoice.payment_reference || '—' }}</p>
                </div>
                <div v-if="invoice.notes" class="sm:col-span-2">
                  <p class="text-xs text-[#64748b] mb-1">Notas</p>
                  <p class="text-sm text-[#94a3b8]">{{ invoice.notes }}</p>
                </div>
              </div>
            </div>

            <div v-if="invoice.status === 'cancelled'" class="border-t border-[#1e293b] pt-4 mt-4">
              <p class="text-sm text-[#94a3b8]">Esta factura fue anulada.</p>
            </div>
          </div>
        </div>

        <!-- Right: Delivery info -->
        <div class="lg:col-span-1 space-y-6">
          <div class="admin-card p-6">
            <h2 class="text-sm font-semibold text-[#dae2fd] mb-4">Destinatario</h2>
            <div class="space-y-4">
              <div>
                <p class="text-xs text-[#64748b] mb-1">Nombre</p>
                <p class="text-sm text-[#94a3b8]">{{ invoice.recipient_name || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Dirección</p>
                <p class="text-sm text-[#94a3b8]">{{ invoice.recipient_city || '—' }}{{ invoice.recipient_department ? ' / ' + invoice.recipient_department : '' }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Envío</p>
                <p class="text-sm text-[#94a3b8]">{{ fmtUSD(invoice.shipping_cost) }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Flete</p>
                <p class="text-sm text-[#94a3b8]">{{ fmtUSD(invoice.flete_cost) }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Delivery</p>
                <p class="text-sm text-[#94a3b8]">{{ fmtUSD(invoice.delivery_cost) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pay Modal -->
      <AppModal v-if="showPay" :visible="showPay" :title="`Cobrar ${invoice.invoice_number}`" size="md" @close="resetPayModal">
        <div class="space-y-4">
          <div class="rounded-xl border border-[#1e293b] bg-[#0f172a] p-4">
            <div class="flex items-center justify-between">
              <span class="text-sm text-[#94a3b8]">Total a cobrar</span>
              <span class="text-xl font-bold text-[#dae2fd]">{{ fmtUSD(invoice.total_amount) }}</span>
            </div>
            <p v-if="invoice.tax_amount" class="mt-1 text-right text-xs text-[#64748b]">Incluye IVA {{ fmtUSD(invoice.tax_amount) }}</p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Moneda</label>
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
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Método de pago</label>
            <AppDropdown
              :model-value="payForm.payment_method_id"
              @update:model-value="(v: string | number | null) => payForm.payment_method_id = Number(v ?? 0)"
              :options="methods.map(m => ({ value: m.id, label: m.name }))"
              placeholder="Seleccionar método"
            />
            <p class="mt-2 text-xs text-[#64748b]">
              Total a pagar: {{ fmtUSD(invoice.total_amount) }}
              ({{ payForm.paid_currency === 'CORD' ? 'córdobas' : 'dólares' }}).
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Referencia (opcional)</label>
            <input
              v-model="payForm.payment_reference"
              class="admin-input"
              placeholder="Nº de transferencia, comprobante, etc."
              :disabled="payingBusy"
            />
          </div>
        </div>
        <template #footer>
          <div class="flex justify-end gap-3">
            <button class="admin-btn admin-btn-ghost" @click="resetPayModal" :disabled="payingBusy">Cancelar</button>
            <button class="admin-btn admin-btn-primary" @click="confirmPay" :disabled="payingBusy || !payForm.payment_method_id">
              {{ payingBusy ? 'Registrando...' : 'Registrar cobro' }}
            </button>
          </div>
        </template>
      </AppModal>

      <!-- Cancel Confirmation -->
      <AppConfirmDialog
        :visible="showCancel"
        title="Anular factura"
        :message="`¿Seguro que querés anular la factura ${invoice.invoice_number}? No se puede deshacer.`"
        confirm-text="Anular"
        variant="danger"
        @confirm="confirmCancel"
        @cancel="showCancel = false"
      />
    </template>

    <!-- Not found -->
    <template v-else-if="!loading">
      <div class="admin-card p-12 text-center">
        <svg class="mx-auto mb-3 text-[#64748b]" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8" />
        </svg>
        <p class="text-sm text-[#94a3b8] mb-1">Factura no encontrada</p>
        <button class="admin-btn admin-btn-ghost admin-btn-sm mt-3" @click="router.push('/facturas')">
          Volver a facturas
        </button>
      </div>
    </template>
  </div>
</template>