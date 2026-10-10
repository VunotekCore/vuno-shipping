<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { quoteService, type Quote, type QuoteItem, type QuotePackage, type QuoteStatus } from '../services/quoteService'
import { formatUSD } from '../utils/format'
import { useToast } from '../composables/useToast'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const quote = ref<Quote | null>(null)
const items = ref<QuoteItem[]>([])
const packages = ref<QuotePackage[]>([])
const loading = ref(true)
const busy = ref(false)

const convertTarget = ref(false)
const deleteTarget = ref(false)

const statusInfo: Record<QuoteStatus, { label: string; cls: string }> = {
  draft: { label: 'Borrador', cls: 'admin-badge-info' },
  sent: { label: 'Enviada', cls: 'admin-badge-warning' },
  accepted: { label: 'Aceptada', cls: 'admin-badge-success' },
  rejected: { label: 'Rechazada', cls: 'admin-badge-danger' },
  expired: { label: 'Expirada', cls: 'admin-badge-danger' },
  converted: { label: 'Convertida', cls: 'admin-badge-success' },
  cancelled: { label: 'Anulada', cls: 'admin-badge-danger' },
}

const statusTransitions: { value: QuoteStatus; label: string }[] = [
  { value: 'sent', label: 'Marcar enviada' },
  { value: 'accepted', label: 'Aceptar' },
  { value: 'rejected', label: 'Rechazar' },
  { value: 'cancelled', label: 'Anular' },
]

const canConvert = computed(() => !!quote.value && ['draft', 'sent', 'accepted'].includes(quote.value.status))
const canEdit = computed(() => !!quote.value && !['converted', 'cancelled'].includes(quote.value.status))

const availableTransitions = computed(() =>
  statusTransitions.filter((t) => t.value !== quote.value?.status)
)

function fmtDate(s: string | null | undefined): string {
  if (!s) return '—'
  const d = new Date(s.replace(' ', 'T'))
  return d.toLocaleDateString('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

async function load() {
  loading.value = true
  try {
    const { data } = await quoteService.get(Number(route.params.id))
    quote.value = data.data.quote
    items.value = data.data.items ?? []
    packages.value = data.data.packages ?? []
  } catch {
    toast.error('Error al cargar la cotización')
    router.push({ name: 'quotes' })
  } finally {
    loading.value = false
  }
}

async function changeStatus(status: QuoteStatus) {
  if (!quote.value) return
  busy.value = true
  try {
    await quoteService.status(quote.value.id, status)
    toast.success('Estado actualizado')
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al cambiar el estado')
  } finally {
    busy.value = false
  }
}

async function confirmConvert() {
  if (!quote.value) return
  busy.value = true
  try {
    const { data } = await quoteService.convert(quote.value.id)
    toast.success(`Cotización convertida en ${data.data?.packages?.length ?? 0} paquete(s)`)
    convertTarget.value = false
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al convertir la cotización')
  } finally {
    busy.value = false
  }
}

async function confirmDelete() {
  if (!quote.value) return
  busy.value = true
  try {
    await quoteService.delete(quote.value.id)
    toast.success('Cotización eliminada')
    router.push({ name: 'quotes' })
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al eliminar la cotización')
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="router.push({ name: 'quotes' })" title="Volver">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <div>
          <h1 class="text-xl font-bold text-[#dae2fd] flex items-center gap-2">
            {{ quote?.quote_number || 'Cotización' }}
            <span v-if="quote" class="admin-badge" :class="statusInfo[quote.status]?.cls || ''">{{ statusInfo[quote.status]?.label }}</span>
          </h1>
          <p class="mt-1 text-sm text-[#64748b]">Creada {{ fmtDate(quote?.created_at) }}</p>
        </div>
      </div>
      <div v-if="quote" class="flex flex-wrap items-center gap-2">
        <button v-if="canConvert" class="admin-btn admin-btn-primary admin-btn-sm" @click="convertTarget = true" :disabled="busy">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
          Convertir a paquete
        </button>
        <button v-if="canEdit" class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="deleteTarget = true" :disabled="busy">Eliminar</button>
      </div>
    </div>

    <div v-if="loading" class="admin-card p-8 text-center text-[#64748b]">Cargando...</div>

    <template v-else-if="quote">
      <!-- Status transitions -->
      <div v-if="canEdit" class="admin-card px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">
          <span class="text-xs text-[#64748b] mr-1">Cambiar estado:</span>
          <button
            v-for="t in availableTransitions"
            :key="t.value"
            class="admin-btn admin-btn-ghost admin-btn-sm"
            :disabled="busy"
            @click="changeStatus(t.value)"
          >{{ t.label }}</button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Left: details -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Items -->
          <div class="admin-card">
            <div class="border-b border-[#1e293b] px-4 py-3">
              <h2 class="text-sm font-semibold text-[#dae2fd]">Ítems</h2>
            </div>
            <div class="overflow-x-auto">
              <table class="admin-table w-full">
                <thead>
                  <tr>
                    <th class="text-left">Descripción</th>
                    <th class="text-right">Cant.</th>
                    <th class="text-right">Peso (lb)</th>
                    <th class="text-right">Volumétrico</th>
                    <th class="text-right">Cobrable</th>
                    <th class="text-right">Flete</th>
                    <th class="text-right">Entrega</th>
                    <th class="text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(it, i) in items" :key="i">
                    <td class="text-[#dae2fd]">{{ it.description || `Ítem ${i + 1}` }}</td>
                    <td class="text-right text-[#94a3b8]">{{ it.quantity }}</td>
                    <td class="text-right text-[#94a3b8]">{{ it.weight ?? '—' }}</td>
                    <td class="text-right text-[#94a3b8]">{{ it.volumetric_weight ?? '—' }}</td>
                    <td class="text-right text-[#94a3b8]">{{ it.chargeable_weight ?? '—' }}</td>
                    <td class="text-right text-[#94a3b8]">{{ formatUSD(it.flete_cost ?? 0) }}</td>
                    <td class="text-right text-[#94a3b8]">{{ formatUSD(it.delivery_cost ?? 0) }}</td>
                    <td class="text-right font-medium text-[#dae2fd]">{{ formatUSD(it.total_amount ?? 0) }}</td>
                  </tr>
                  <tr v-if="!items.length">
                    <td colspan="8" class="text-center text-[#64748b] py-6">Sin ítems</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Packages -->
          <div v-if="packages.length" class="admin-card">
            <div class="border-b border-[#1e293b] px-4 py-3">
              <h2 class="text-sm font-semibold text-[#dae2fd]">Paquetes generados</h2>
            </div>
            <div class="divide-y divide-[#1e293b]">
              <div v-for="p in packages" :key="p.id" class="flex items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                  <p class="text-sm font-medium text-[#dae2fd]">{{ p.tracking_number }}</p>
                  <p class="text-xs text-[#64748b]">{{ p.invoice_number || 'Sin factura' }}</p>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm text-[#94a3b8]">{{ formatUSD(p.shipping_cost ?? 0) }}</span>
                  <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="router.push({ name: 'package-detail', params: { id: p.id } })">Ver paquete</button>
                  <button v-if="p.invoice_id" class="admin-btn admin-btn-ghost admin-btn-sm" @click="router.push({ name: 'invoice-detail', params: { id: p.invoice_id } })">Ver factura</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: summary -->
        <div class="space-y-6">
          <!-- Totals -->
          <div class="admin-card p-4 space-y-3">
            <h2 class="text-sm font-semibold text-[#dae2fd]">Resumen</h2>
            <div class="space-y-1.5 text-sm">
              <div class="flex justify-between"><span class="text-[#64748b]">Flete</span><span class="text-[#94a3b8]">{{ formatUSD(quote.subtotal) }}</span></div>
              <div class="flex justify-between"><span class="text-[#64748b]">Entrega</span><span class="text-[#94a3b8]">{{ formatUSD(quote.delivery_total) }}</span></div>
              <div class="flex justify-between"><span class="text-[#64748b]">Base</span><span class="text-[#94a3b8]">{{ formatUSD(quote.base_amount) }}</span></div>
              <div v-if="quote.tax_amount > 0" class="flex justify-between"><span class="text-[#64748b]">IVA ({{ quote.tax_rate }}%)</span><span class="text-[#94a3b8]">{{ formatUSD(quote.tax_amount) }}</span></div>
              <div class="flex justify-between border-t border-[#1e293b] pt-2"><span class="font-medium text-[#dae2fd]">Total</span><span class="text-lg font-bold text-[#dae2fd]">{{ formatUSD(quote.total_amount) }}</span></div>
            </div>
            <div v-if="quote.commission_amount != null" class="rounded-lg border border-[#1e293b] bg-[#0f172a] p-3 text-sm">
              <div class="flex justify-between"><span class="text-[#64748b]">Comisión ({{ quote.commission_type === 'percent' ? `${quote.commission_value}%` : 'fija' }})</span><span class="font-medium text-[#69dca4]">{{ formatUSD(quote.commission_amount) }}</span></div>
            </div>
          </div>

          <!-- Destinatario -->
          <div class="admin-card p-4 space-y-3">
            <h2 class="text-sm font-semibold text-[#dae2fd]">Destinatario</h2>
            <div class="space-y-1.5 text-sm">
              <p class="text-[#dae2fd] font-medium">{{ quote.recipient_name }}</p>
              <p class="text-[#94a3b8]">{{ quote.recipient_phone || '—' }}</p>
              <p class="text-[#94a3b8]">{{ quote.recipient_email || '—' }}</p>
              <p class="text-[#94a3b8]">{{ quote.recipient_address || '—' }}</p>
              <p class="text-[#94a3b8]">{{ [quote.recipient_city, quote.recipient_department].filter(Boolean).join(', ') || '—' }}</p>
              <p v-if="quote.recipient_ruc" class="text-[#94a3b8] font-mono text-xs">{{ quote.recipient_ruc }}</p>
            </div>
          </div>

          <!-- Envío -->
          <div class="admin-card p-4 space-y-2 text-sm">
            <h2 class="text-sm font-semibold text-[#dae2fd] mb-1">Envío</h2>
            <div class="flex justify-between"><span class="text-[#64748b]">Origen</span><span class="text-[#94a3b8]">{{ quote.origin === 'local' ? 'Directo Nicaragua' : 'Compra online (Miami)' }}</span></div>
            <div class="flex justify-between"><span class="text-[#64748b]">Opción</span><span class="text-[#94a3b8]">{{ quote.delivery_option || '—' }}</span></div>
            <div class="flex justify-between"><span class="text-[#64748b]">Recolección</span><span class="text-[#94a3b8]">{{ quote.pickup_required ? (quote.pickup_vehicle_plate ? `Sí · ${quote.pickup_vehicle_plate}` : 'Sí') : 'No' }}</span></div>
            <div class="flex justify-between"><span class="text-[#64748b]">Vendedor</span><span class="text-[#94a3b8]">{{ quote.agent_name || '—' }}</span></div>
            <div class="flex justify-between"><span class="text-[#64748b]">Válida hasta</span><span class="text-[#94a3b8]">{{ quote.valid_until ? fmtDate(quote.valid_until) : '—' }}</span></div>
          </div>

          <!-- Notes -->
          <div v-if="quote.notes" class="admin-card p-4 space-y-2">
            <h2 class="text-sm font-semibold text-[#dae2fd]">Notas</h2>
            <p class="text-sm text-[#94a3b8] whitespace-pre-line">{{ quote.notes }}</p>
          </div>
        </div>
      </div>
    </template>

    <AppConfirmDialog
      :visible="convertTarget"
      title="Convertir cotización"
      :message="`Se generará un paquete por cada ítem. ¿Continuar?`"
      confirm-text="Convertir"
      @confirm="confirmConvert"
      @cancel="convertTarget = false"
    />

    <AppConfirmDialog
      :visible="deleteTarget"
      title="Eliminar cotización"
      :message="`¿Seguro que querés eliminar ${quote?.quote_number}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="deleteTarget = false"
    />
  </div>
</template>
