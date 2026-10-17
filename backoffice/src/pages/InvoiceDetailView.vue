<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { invoiceService, type Invoice } from '../services/invoiceService'
import { paymentMethodService, type PaymentMethod } from '../services/paymentMethodService'
import { formatUSD, formatCord, formatRate, formatWeight, formatDimensions, usdToCord, paidCurrencyLabel } from '../utils/format'
import { useToast } from '../composables/useToast'
import AppModal from '../components/ui/AppModal.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import InvoicePrintModal from '../components/ui/InvoicePrintModal.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const invoice = ref<Invoice | null>(null)
const loading = ref(true)
const methods = ref<PaymentMethod[]>([])
// Tasa vigente (C$ por USD) que trae invoice/get.php. Se usa solo mientras la
// factura esta pendiente o en el preview del modal: una vez cobrada en CORD
// manda el snapshot `invoice.exchange_rate` (la tasa realmente aplicada).
const exchangeRateUsd = ref(36)
const showPrint = ref(false)

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

/** Factura cobrada en cordoba: el desglose lleva equivalente en C$. */
const isPaidCord = computed(
  () => invoice.value?.status === 'paid' && invoice.value?.paid_currency === 'CORD'
)

/** Snapshot aplicado si se cobro en C$, si no la tasa vigente. */
const appliedRate = computed(() =>
  isPaidCord.value ? invoice.value?.exchange_rate ?? exchangeRateUsd.value : exchangeRateUsd.value
)

/** Preview del modal: el importe en la moneda que el despachador eligio. */
const payPreview = computed(() => {
  const total = invoice.value?.total_amount ?? 0
  return payForm.value.paid_currency === 'CORD'
    ? { amount: usdToCord(total, exchangeRateUsd.value), currency: 'CORD' as const }
    : { amount: total, currency: 'USD' as const }
})

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
    const rate = Number(data.data?.exchange_rate_usd)
    if (Number.isFinite(rate) && rate > 0) exchangeRateUsd.value = rate
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
        <div class="h-8 w-8 animate-pulse rounded-lg bg-surface-raised" />
        <div class="h-6 w-48 animate-pulse rounded bg-surface-raised" />
      </div>
      <div class="admin-card p-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div v-for="i in 6" :key="i" class="space-y-2">
            <div class="h-3 w-16 animate-pulse rounded bg-surface-raised" />
            <div class="h-4 w-full animate-pulse rounded bg-surface-raised" />
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="invoice">
      <!-- Page header -->
      <PageHeader back-to="/facturas" :subtitle="`Factura de envío — ${fmtDate(invoice.created_at)}`">
        <template #title>
          <span class="flex items-center gap-3">
            <span class="font-mono">{{ invoice.invoice_number }}</span>
            <span class="admin-badge" :class="statusInfo[invoice.status]?.cls || ''">
              {{ statusInfo[invoice.status]?.label || invoice.status }}
            </span>
          </span>
        </template>
        <template #actions>
          <button class="admin-btn admin-btn-ghost" @click="showPrint = true">
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
        </template>
      </PageHeader>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Invoice info -->
        <div class="lg:col-span-2">
          <div class="admin-card p-6">
            <h2 class="text-sm font-semibold text-content mb-4">Información de la factura</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <p class="text-xs text-subtle mb-1">Cliente</p>
                <p class="text-sm text-muted">{{ clientName }}</p>
              </div>
              <div>
                <p class="text-xs text-subtle mb-1">RUC</p>
                <p class="text-sm text-muted">{{ invoice.client_ruc || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-subtle mb-1">Paquete</p>
                <button
                  class="admin-btn admin-btn-ghost admin-btn-sm font-mono"
                  @click="router.push({ name: 'package-detail', params: { id: invoice.package_id } })"
                >
                  {{ invoice.tracking_number || `#${invoice.package_id}` }}
                </button>
              </div>
              <div>
                <p class="text-xs text-subtle mb-1">Moneda de referencia</p>
                <p class="text-sm text-muted">
                  Dólares (US$)
                  <span class="text-xs text-subtle">— precios del envío</span>
                </p>
              </div>
              <div>
                <p class="text-xs text-subtle mb-1">Moneda de cobro</p>
                <p v-if="invoice.status === 'paid'" class="text-sm text-muted">
                  {{ paidCurrencyLabel(invoice.paid_currency) }}
                </p>
                <p v-else class="text-sm text-muted">
                  Pendiente
                  <span class="text-xs text-subtle">— se define al registrar el cobro</span>
                </p>
              </div>
              <div>
                <p class="text-xs text-subtle mb-1">Creada</p>
                <p class="text-sm text-muted">{{ fmtDate(invoice.created_at) }}</p>
              </div>
              <div v-if="invoice.status === 'paid'">
                <p class="text-xs text-subtle mb-1">Pagada</p>
                <p class="text-sm text-success">{{ fmtDate(invoice.paid_at) }}</p>
              </div>
            </div>

            <!-- Cost breakdown: siempre en USD (base real del paquete) -->
            <div class="border-t border-line pt-4 mt-4">
              <p class="text-xs text-subtle mb-3">Desglose — montos en USD</p>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-muted">Envío (base)</span>
                  <span class="text-content">{{ formatUSD(invoice.base_amount) }}</span>
                </div>
                <div v-if="invoice.tax_rate > 0" class="flex justify-between text-sm">
                  <span class="text-muted">IVA {{ invoice.tax_rate }}%</span>
                  <span class="text-content">{{ formatUSD(invoice.tax_amount) }}</span>
                </div>
                <div class="flex justify-between text-base font-bold border-t border-line pt-2 mt-2">
                  <span class="text-content">Total</span>
                  <span class="text-content">{{ formatUSD(invoice.total_amount) }}</span>
                </div>

                <!-- Equivalente en cordoba -->
                <div v-if="isPaidCord" class="mt-4 rounded-xl border border-line bg-surface-sunken p-3 space-y-2">
                  <p class="text-xs text-subtle">
                    Equivalente en córdobas — tasa aplicada C${{ formatRate(appliedRate) }} por USD
                  </p>
                  <div class="flex justify-between text-sm">
                    <span class="text-muted">Envío (base)</span>
                    <span class="text-content">{{ formatCord(usdToCord(invoice.base_amount, appliedRate)) }}</span>
                  </div>
                  <div v-if="invoice.tax_rate > 0" class="flex justify-between text-sm">
                    <span class="text-muted">IVA {{ invoice.tax_rate }}%</span>
                    <span class="text-content">{{ formatCord(usdToCord(invoice.tax_amount, appliedRate)) }}</span>
                  </div>
                  <div class="flex justify-between text-sm font-bold border-t border-line pt-2">
                    <span class="text-content">Total</span>
                    <span class="text-content">{{ formatCord(usdToCord(invoice.total_amount, appliedRate)) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment info -->
            <div v-if="invoice.status === 'paid'" class="border-t border-line pt-4 mt-4">
              <p class="text-xs text-subtle mb-3">Datos del cobro</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-subtle mb-1">Monto cobrado</p>
                  <p class="text-sm font-medium text-success">
                    {{ invoice.paid_currency === 'CORD' ? formatCord(invoice.paid_amount) : formatUSD(invoice.paid_amount) }}
                  </p>
                </div>
                <div>
                  <p class="text-xs text-subtle mb-1">Método de pago</p>
                  <p class="text-sm text-muted">{{ invoice.payment_method_name || '—' }}</p>
                </div>
                <div v-if="invoice.paid_currency === 'CORD'">
                  <p class="text-xs text-subtle mb-1">Tasa de cambio aplicada (C$ por USD)</p>
                  <p class="text-sm text-muted">C${{ formatRate(invoice.exchange_rate) }}</p>
                </div>
                <div>
                  <p class="text-xs text-subtle mb-1">Equivalente facturado en USD</p>
                  <p class="text-sm text-muted">{{ formatUSD(invoice.total_amount) }}</p>
                </div>
                <div class="sm:col-span-2">
                  <p class="text-xs text-subtle mb-1">Referencia</p>
                  <p class="text-sm text-muted">{{ invoice.payment_reference || '—' }}</p>
                </div>
                <div v-if="invoice.notes" class="sm:col-span-2">
                  <p class="text-xs text-subtle mb-1">Notas</p>
                  <p class="text-sm text-muted">{{ invoice.notes }}</p>
                </div>
              </div>
            </div>

            <!-- Aviso de anulacion: se mantiene tal cual, es independiente del
                 bloque de moneda local de arriba (si no, se reemplaza por error) -->
            <div v-if="invoice.status === 'cancelled'" class="border-t border-line pt-4 mt-4">
              <p class="text-sm text-muted">Esta factura fue anulada.</p>
            </div>
          </div>
        </div>

        <!-- Right: Destinatario + datos del paquete -->
        <div class="lg:col-span-1 space-y-6">
          <div class="admin-card p-6">
            <h2 class="text-sm font-semibold text-content mb-4">Destinatario</h2>
            <div class="space-y-4">
              <div>
                <p class="text-xs text-subtle mb-1">Nombre</p>
                <p class="text-sm text-muted">{{ invoice.recipient_name || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-subtle mb-1">Teléfono</p>
                <p class="text-sm text-muted">{{ invoice.recipient_phone || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-subtle mb-1">Dirección</p>
                <p class="text-sm text-muted break-words">
                  {{ invoice.recipient_address || '—' }}
                </p>
                <p
                  v-if="invoice.recipient_city || invoice.recipient_department"
                  class="mt-1 text-xs text-subtle"
                >
                  {{ [invoice.recipient_city, invoice.recipient_department].filter(Boolean).join(' · ') }}
                </p>
              </div>
            </div>
          </div>

          <div class="admin-card p-6">
            <h2 class="text-sm font-semibold text-content mb-4">Paquete</h2>
            <div class="space-y-4">
              <div>
                <p class="text-xs text-subtle mb-1">Número de rastreo</p>
                <p class="text-sm font-mono text-muted">{{ invoice.tracking_number || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-subtle mb-1">Descripción</p>
                <p class="text-sm text-muted break-words">{{ invoice.description || '—' }}</p>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-subtle mb-1">Peso real</p>
                  <p class="text-sm text-muted">{{ formatWeight(invoice.weight) }}</p>
                </div>
                <div>
                  <p class="text-xs text-subtle mb-1">Peso volumétrico</p>
                  <p class="text-sm text-muted">{{ formatWeight(invoice.volumetric_weight) }}</p>
                </div>
                <div>
                  <p class="text-xs text-subtle mb-1">Dimensiones</p>
                  <p class="text-sm text-muted">
                    {{ formatDimensions(invoice.length, invoice.width, invoice.height) }}
                  </p>
                </div>
                <div>
                  <p class="text-xs text-subtle mb-1">Valor declarado</p>
                  <p class="text-sm text-muted">{{ formatUSD(invoice.declared_value) }}</p>
                </div>
              </div>

              <!-- El peso cobrable es el que se facturo: se resalta sobre los demas -->
              <div class="rounded-xl border border-line bg-surface-sunken p-3">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-muted">Peso cobrable</span>
                  <span class="text-sm font-semibold text-content">
                    {{ formatWeight(invoice.chargeable_weight) }}
                  </span>
                </div>
              </div>

              <div class="border-t border-line pt-4">
                <p class="text-xs text-subtle mb-3">Desglose del envío</p>
                <div class="space-y-2">
                  <div class="flex justify-between text-sm">
                    <span class="text-muted">Flete</span>
                    <span class="text-content">{{ formatUSD(invoice.flete_cost) }}</span>
                  </div>
                  <div class="flex justify-between text-sm">
                    <span class="text-muted">Delivery</span>
                    <span class="text-content">{{ formatUSD(invoice.delivery_cost) }}</span>
                  </div>
                  <div class="flex justify-between text-sm font-medium border-t border-line pt-2 mt-2">
                    <span class="text-muted">Total del envío</span>
                    <span class="text-content">{{ formatUSD(invoice.shipping_cost) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pay Modal -->
      <AppModal v-if="showPay" :visible="showPay" :title="`Cobrar ${invoice.invoice_number}`" size="md" @close="resetPayModal">
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
              Equivalente facturado: {{ formatUSD(invoice.total_amount) }}
              <template v-if="invoice.tax_amount"> · incluye IVA {{ formatUSD(invoice.tax_amount) }}</template>
            </p>
            <p v-if="payPreview.currency === 'CORD'" class="mt-1 text-right text-xs text-subtle">
              Tasa vigente: C${{ formatRate(exchangeRateUsd) }} por USD
            </p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-content">Moneda del cobro</label>
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
            <label class="mb-1.5 block text-sm font-medium text-content">Método de pago</label>
            <AppDropdown
              :model-value="payForm.payment_method_id"
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

          <div>
            <label class="mb-1.5 block text-sm font-medium text-content">Referencia (opcional)</label>
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

      <InvoicePrintModal
        :visible="showPrint"
        :invoice-id="invoice.id"
        :invoice-number="invoice.invoice_number"
        @close="showPrint = false"
      />
    </template>

    <!-- Not found -->
    <template v-else-if="!loading">
      <div class="admin-card p-12 text-center">
        <svg class="mx-auto mb-3 text-subtle" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8" />
        </svg>
        <p class="text-sm text-muted mb-1">Factura no encontrada</p>
        <button class="admin-btn admin-btn-ghost admin-btn-sm mt-3" @click="router.push('/facturas')">
          Volver a facturas
        </button>
      </div>
    </template>
  </div>
</template>