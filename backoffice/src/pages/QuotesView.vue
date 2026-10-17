<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { quoteService, type Quote, type QuoteSummary, type QuoteStatus, type QuoteItemInput } from '../services/quoteService'
import type { QuoteCommissionRow } from '../types'
import { userService, type User } from '../services/userService'
import { clientService, type Client } from '../services/clientService'
import { zoneService, type Zone } from '../services/zoneService'
import { deliveryTypeService, type DeliveryType } from '../services/deliveryTypeService'
import { shippingSettingsService, type ShippingService, type DeliveryOption } from '../services/shippingSettingsService'
import { vehicleService, type Vehicle } from '../services/vehicleService'
import { formatUSD } from '../utils/format'
import { useToast } from '../composables/useToast'
import { useAuthStore } from '../stores/auth'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import AppSwitch from '../components/ui/AppSwitch.vue'
import AppPagination from '../components/ui/AppPagination.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const isVendor = computed(() => auth.user?.role_slug === 'vendedor')

const quotes = ref<Quote[]>([])
const loading = ref(true)
const paginating = ref(false)

const page = ref(1)
const limit = ref(20)
const total = ref(0)
const pages = ref(0)

const filters = reactive<{ status: string; search: string; agent_id: string }>({ status: '', search: '', agent_id: '' })

const summary = ref<QuoteSummary>({ draft: 0, sent: 0, accepted: 0, rejected: 0, expired: 0, converted: 0, cancelled: 0 })

// Commission KPIs (admin only)
function isoLocal(d: Date): string {
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${m}-${day}`
}
const commissions = ref<QuoteCommissionRow[]>([])
const commissionsLoading = ref(false)
const commissionsFrom = ref(isoLocal(new Date(new Date().getFullYear(), new Date().getMonth(), 1)))
const commissionsTo = ref(isoLocal(new Date()))

const commissionTotals = computed(() =>
  commissions.value.reduce(
    (acc, r) => {
      acc.count += Number(r.converted_count) || 0
      acc.converted += Number(r.converted_total) || 0
      acc.commission += Number(r.commission_total) || 0
      return acc
    },
    { count: 0, converted: 0, commission: 0 },
  ),
)

async function loadCommissions() {
  commissionsLoading.value = true
  try {
    const { data } = await quoteService.stats({ from: commissionsFrom.value, to: commissionsTo.value })
    commissions.value = data.data.commissions ?? []
  } catch {
    toast.error('Error al cargar comisiones')
  } finally {
    commissionsLoading.value = false
  }
}

// Catalog data
const agents = ref<User[]>([])
const clients = ref<Client[]>([])
const zones = ref<Zone[]>([])
const deliveryTypes = ref<DeliveryType[]>([])
const shippingServices = ref<ShippingService[]>([])
const deliveryOptions = ref<DeliveryOption[]>([])
const vehicles = ref<Vehicle[]>([])

// Create / edit
const showModal = ref(false)
const editing = ref<Quote | null>(null)
const saving = ref(false)

interface QuoteForm {
  client_id: number | null
  agent_id: number | null
  recipient_name: string
  recipient_phone: string
  recipient_email: string
  recipient_address: string
  recipient_city: string
  recipient_department: string
  recipient_ruc: string
  origin: 'online' | 'local'
  zone_id: number | null
  delivery_type: string
  shipping_service: string
  delivery_option: string
  pickup_required: boolean
  pickup_vehicle_id: number | null
  valid_until: string
  notes: string
  items: QuoteItemInput[]
}

function emptyItem(): QuoteItemInput {
  return { description: '', quantity: 1, weight: null, length: null, width: null, height: null, declared_value: null }
}

function defaultForm(): QuoteForm {
  return {
    client_id: null,
    agent_id: isVendor.value ? (auth.user?.id ?? null) : null,
    recipient_name: '',
    recipient_phone: '',
    recipient_email: '',
    recipient_address: '',
    recipient_city: '',
    recipient_department: '',
    recipient_ruc: '',
    origin: 'online',
    zone_id: null,
    delivery_type: '',
    shipping_service: '',
    delivery_option: '',
    pickup_required: false,
    pickup_vehicle_id: null,
    valid_until: '',
    notes: '',
    items: [emptyItem()],
  }
}

const form = reactive<QuoteForm>(defaultForm())

// confirm states
const converting = ref<Quote | null>(null)
const convertBusy = ref(false)
const deleting = ref<Quote | null>(null)
const deleteBusy = ref(false)

const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'draft', label: 'Borradores' },
  { value: 'sent', label: 'Enviadas' },
  { value: 'accepted', label: 'Aceptadas' },
  { value: 'rejected', label: 'Rechazadas' },
  { value: 'expired', label: 'Expiradas' },
  { value: 'converted', label: 'Convertidas' },
  { value: 'cancelled', label: 'Anuladas' },
]

const statusInfo: Record<QuoteStatus, { label: string; cls: string }> = {
  draft: { label: 'Borrador', cls: 'admin-badge-info' },
  sent: { label: 'Enviada', cls: 'admin-badge-warning' },
  accepted: { label: 'Aceptada', cls: 'admin-badge-success' },
  rejected: { label: 'Rechazada', cls: 'admin-badge-danger' },
  expired: { label: 'Expirada', cls: 'admin-badge-danger' },
  converted: { label: 'Convertida', cls: 'admin-badge-success' },
  cancelled: { label: 'Anulada', cls: 'admin-badge-danger' },
}

const originOptions = [
  { value: 'online', label: 'Compra online (Miami)' },
  { value: 'local', label: 'Directo Nicaragua' },
]

const columns: TableColumn[] = [
  { key: 'quote_number', label: 'Cotización', width: '120px' },
  { key: 'recipient_name', label: 'Destinatario' },
  { key: 'agent_name', label: 'Vendedor', width: '150px' },
  { key: 'total_amount', label: 'Total', width: '110px', align: 'right' },
  { key: 'status', label: 'Estado', width: '120px' },
  { key: 'created_at', label: 'Fecha', width: '120px' },
  { key: 'actions', label: '', width: '130px', align: 'right' },
]

const zoneOptions = computed(() => zones.value.map((z) => ({ value: z.id, label: z.name })))
const vehicleOptions = computed(() =>
  vehicles.value.map((v) => {
    const desc = [v.brand, v.model].filter(Boolean).join(' ')
    return { value: v.id, label: desc ? `${v.plate} — ${desc}` : v.plate }
  }),
)
const clientOptions = computed(() => clients.value.map((c) => ({ value: c.id, label: c.name })))
const agentOptions = computed(() => agents.value.map((a) => ({ value: a.id, label: a.name })))
const deliveryTypeOpts = computed(() => deliveryTypes.value.map((d) => ({ value: d.name, label: d.name })))
const serviceOpts = computed(() => shippingServices.value.map((s) => ({ value: s.name, label: s.name })))
const optionOpts = computed(() => deliveryOptions.value.map((o) => ({ value: o.name, label: o.name })))

function fmtDate(s: string | null | undefined): string {
  if (!s) return '—'
  const d = new Date(s.replace(' ', 'T'))
  return d.toLocaleDateString('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function fv(key: keyof QuoteForm): string | number | null {
  const v = form[key]
  if (v == null) return null
  if (typeof v === 'string' || typeof v === 'number') return v
  return null
}

function canConvert(q: Quote): boolean {
  return ['draft', 'sent', 'accepted'].includes(q.status)
}
function canEdit(q: Quote): boolean {
  return !['converted', 'cancelled'].includes(q.status)
}

async function load() {
  loading.value = true
  try {
    const { data } = await quoteService.list({
      page: page.value,
      limit: limit.value,
      status: (filters.status || undefined) as QuoteStatus | undefined,
      search: filters.search || undefined,
      agent_id: filters.agent_id ? Number(filters.agent_id) : undefined,
    })
    quotes.value = data.data.quotes ?? []
    summary.value = data.data.summary ?? summary.value
    total.value = data.data.pagination?.total ?? 0
    pages.value = data.data.pagination?.pages ?? 0
  } catch {
    toast.error('Error al cargar cotizaciones')
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

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => filters.search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 350)
})
watch(() => filters.status, () => { page.value = 1; load() })
watch(() => filters.agent_id, () => { page.value = 1; load() })
watch([commissionsFrom, commissionsTo], () => loadCommissions())

function openDetail(id: number) {
  router.push({ name: 'quote-detail', params: { id } })
}

function openCreate() {
  editing.value = null
  Object.assign(form, defaultForm())
  showModal.value = true
}

async function openEdit(q: Quote) {
  editing.value = q
  try {
    const { data } = await quoteService.get(q.id)
    const quote = data.data.quote
    Object.assign(form, {
      client_id: quote.client_id,
      agent_id: quote.agent_id,
      recipient_name: quote.recipient_name ?? '',
      recipient_phone: quote.recipient_phone ?? '',
      recipient_email: quote.recipient_email ?? '',
      recipient_address: quote.recipient_address ?? '',
      recipient_city: quote.recipient_city ?? '',
      recipient_department: quote.recipient_department ?? '',
      recipient_ruc: quote.recipient_ruc ?? '',
      origin: quote.origin,
      zone_id: quote.zone_id,
      delivery_type: quote.delivery_type_id ? (deliveryTypeName(quote.delivery_type_id) ?? '') : '',
      shipping_service: quote.shipping_service_id ? (serviceName(quote.shipping_service_id) ?? '') : '',
      delivery_option: quote.delivery_option ?? '',
      pickup_required: !!quote.pickup_required,
      pickup_vehicle_id: quote.pickup_vehicle_id ?? null,
      valid_until: quote.valid_until ?? '',
      notes: quote.notes ?? '',
      items: (data.data.items ?? []).map((it) => ({
        description: it.description ?? '',
        quantity: it.quantity ?? 1,
        weight: it.weight,
        length: it.length,
        width: it.width,
        height: it.height,
        declared_value: it.declared_value,
      })),
    })
    if (!form.items.length) form.items = [emptyItem()]
    showModal.value = true
  } catch {
    toast.error('Error al cargar la cotización')
  }
}

function deliveryTypeName(id: number): string | null {
  return deliveryTypes.value.find((d) => d.id === id)?.name ?? null
}
function serviceName(id: number): string | null {
  return shippingServices.value.find((s) => s.id === id)?.name ?? null
}

function addItem() {
  form.items.push(emptyItem())
}
function removeItem(index: number) {
  if (form.items.length <= 1) return
  form.items.splice(index, 1)
}

function buildPayload() {
  const num = (v: unknown): number | null => (v === '' || v == null ? null : Number(v))
  return {
    id: editing.value?.id,
    client_id: form.client_id ?? null,
    agent_id: isVendor.value ? null : (form.agent_id ?? null),
    recipient_name: form.recipient_name.trim(),
    recipient_phone: form.recipient_phone || null,
    recipient_email: form.recipient_email || null,
    recipient_address: form.recipient_address || null,
    recipient_city: form.recipient_city || null,
    recipient_department: form.recipient_department || null,
    recipient_ruc: form.recipient_ruc || null,
    origin: form.origin,
    zone_id: form.zone_id ?? null,
    delivery_type: form.delivery_type || undefined,
    shipping_service: form.shipping_service || null,
    delivery_option: form.delivery_option || undefined,
    pickup_required: form.pickup_required,
    pickup_vehicle_id: form.pickup_required ? (form.pickup_vehicle_id ?? null) : null,
    valid_until: form.valid_until || null,
    notes: form.notes || null,
    items: form.items.map((it) => ({
      description: it.description || '',
      quantity: Number(it.quantity ?? 1),
      weight: num(it.weight),
      length: num(it.length),
      width: num(it.width),
      height: num(it.height),
      declared_value: num(it.declared_value),
    })),
  }
}

async function save() {
  if (!form.recipient_name.trim()) { toast.error('El nombre del destinatario es requerido'); return }
  if (!form.items.length) { toast.error('Agregá al menos un ítem'); return }
  saving.value = true
  try {
    const payload = buildPayload()
    if (editing.value) {
      await quoteService.update(payload as never)
      toast.success('Cotización actualizada')
    } else {
      await quoteService.create(payload as never)
      toast.success('Cotización creada')
    }
    showModal.value = false
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al guardar la cotización')
  } finally {
    saving.value = false
  }
}

function openConvert(q: Quote) {
  converting.value = q
}
async function confirmConvert() {
  if (!converting.value) return
  convertBusy.value = true
  try {
    const { data } = await quoteService.convert(converting.value.id)
    const count = data.data?.packages?.length ?? 0
    toast.success(`Cotización convertida en ${count} paquete(s)`)
    converting.value = null
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al convertir la cotización')
  } finally {
    convertBusy.value = false
  }
}

function openDelete(q: Quote) {
  deleting.value = q
}
async function confirmDelete() {
  if (!deleting.value) return
  deleteBusy.value = true
  try {
    await quoteService.delete(deleting.value.id)
    toast.success('Cotización eliminada')
    deleting.value = null
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al eliminar la cotización')
  } finally {
    deleteBusy.value = false
  }
}

onMounted(async () => {
  load()
  const tasks: Promise<unknown>[] = [
    clientService.listAll().then((r) => { clients.value = r.data.data?.clients ?? [] }).catch(() => {}),
    zoneService.listAll().then((r) => { zones.value = r.data.data?.zones ?? [] }).catch(() => {}),
    deliveryTypeService.list().then((r) => { deliveryTypes.value = r.data.data?.delivery_types ?? [] }).catch(() => {}),
    shippingSettingsService.listServices().then((r) => { shippingServices.value = r.data.data?.shipping_services ?? [] }).catch(() => {}),
    shippingSettingsService.listOptions().then((r) => { deliveryOptions.value = r.data.data?.delivery_options ?? [] }).catch(() => {}),
    vehicleService.listAll().then((r) => { vehicles.value = r.data.data?.vehicles ?? [] }).catch(() => {}),
  ]
  if (!isVendor.value) {
    tasks.push(userService.listByRole('vendedor').then((r) => { agents.value = r.data.data?.users ?? [] }).catch(() => {}))
  }
  tasks.push(loadCommissions())
  await Promise.all(tasks)
})
</script>

<template>
  <div class="space-y-6">
    <!-- Page header -->
    <PageHeader title="Cotizaciones" subtitle="Cotiza envíos, asigná vendedores y convertí a paquetes">
      <template #actions>
        <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Nueva cotización
        </button>
      </template>
    </PageHeader>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <div class="admin-card p-4">
        <p class="text-xs text-subtle">Borradores</p>
        <p class="mt-1 text-lg font-bold text-content">{{ summary.draft }}</p>
      </div>
      <div class="admin-card p-4">
        <p class="text-xs text-subtle">Enviadas</p>
        <p class="mt-1 text-lg font-bold text-warning">{{ summary.sent }}</p>
      </div>
      <div class="admin-card p-4">
        <p class="text-xs text-subtle">Aceptadas</p>
        <p class="mt-1 text-lg font-bold text-primary">{{ summary.accepted }}</p>
      </div>
      <div class="admin-card p-4">
        <p class="text-xs text-subtle">Convertidas</p>
        <p class="mt-1 text-lg font-bold text-primary">{{ summary.converted }}</p>
      </div>
    </div>

    <!-- Commission KPIs -->
    <div class="admin-card p-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-sm font-semibold text-content">{{ isVendor ? 'Mis comisiones' : 'Comisiones por vendedor' }}</h2>
          <p class="mt-0.5 text-xs text-subtle">
            {{ commissionTotals.count }} conversiones · {{ formatUSD(commissionTotals.converted) }} convertidos · {{ formatUSD(commissionTotals.commission) }} en comisiones
          </p>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-36"><input v-model="commissionsFrom" type="date" class="admin-input" /></div>
          <span class="text-xs text-subtle">a</span>
          <div class="w-36"><input v-model="commissionsTo" type="date" class="admin-input" /></div>
        </div>
      </div>

      <div v-if="commissionsLoading" class="mt-4 space-y-2">
        <div v-for="i in 3" :key="i" class="h-9 animate-pulse rounded bg-surface-raised" />
      </div>
      <div v-else-if="commissions.length" class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wider text-subtle">
              <th class="py-2 pr-3 font-medium">Vendedor</th>
              <th class="px-3 py-2 text-right font-medium">Conversiones</th>
              <th class="px-3 py-2 text-right font-medium">Monto convertido</th>
              <th class="py-2 pl-3 text-right font-medium">Comisión</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in commissions" :key="row.agent_id ?? 'none'" class="border-t border-line">
              <td class="py-2 pr-3 text-content">{{ row.agent_name || 'Sin vendedor' }}</td>
              <td class="px-3 py-2 text-right text-muted">{{ row.converted_count }}</td>
              <td class="px-3 py-2 text-right text-muted">{{ formatUSD(row.converted_total) }}</td>
              <td class="py-2 pl-3 text-right font-medium text-primary">{{ formatUSD(row.commission_total) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t border-line-strong">
              <td class="py-2 pr-3 font-medium text-content">Total</td>
              <td class="px-3 py-2 text-right text-content">{{ commissionTotals.count }}</td>
              <td class="px-3 py-2 text-right text-content">{{ formatUSD(commissionTotals.converted) }}</td>
              <td class="py-2 pl-3 text-right font-semibold text-primary">{{ formatUSD(commissionTotals.commission) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <p v-else class="mt-4 text-sm text-subtle">Sin conversiones en el rango seleccionado.</p>
    </div>

    <!-- Filters -->
    <div class="admin-card px-4 py-3">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
          <input
            v-model="filters.search"
            class="admin-input"
            placeholder="Buscar por número o destinatario..."
          />
        </div>
        <div class="sm:w-52">
          <AppDropdown
            :model-value="filters.status"
            @update:model-value="(v: string | number | null) => filters.status = String(v ?? '')"
            :options="statusOptions"
            placeholder="Filtrar por estado"
          />
        </div>
        <div v-if="!isVendor" class="sm:w-52">
          <AppDropdown
            :model-value="filters.agent_id || ''"
            @update:model-value="(v: string | number | null) => filters.agent_id = String(v ?? '')"
            :options="[{ value: '', label: 'Todos los vendedores' }, ...agentOptions]"
            placeholder="Vendedor"
          />
        </div>
        <p class="text-xs text-subtle whitespace-nowrap">{{ total }} cotizaciones</p>
      </div>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading || paginating" :is-empty="quotes.length === 0" empty-title="Sin cotizaciones" empty-desc="Creá la primera cotización.">
          <tr v-for="q in quotes" :key="q.id">
            <td class="font-medium text-content">{{ q.quote_number }}</td>
            <td class="text-muted">{{ q.recipient_name }}</td>
            <td class="text-muted">{{ q.agent_name || '—' }}</td>
            <td class="text-right font-medium text-content">{{ formatUSD(q.total_amount) }}</td>
            <td><span class="admin-badge" :class="statusInfo[q.status]?.cls || ''">{{ statusInfo[q.status]?.label || q.status }}</span></td>
            <td class="text-muted">{{ fmtDate(q.created_at) }}</td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openDetail(q.id)" title="Ver detalle">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button v-if="canConvert(q)" class="admin-btn admin-btn-ghost admin-btn-sm text-primary" @click="openConvert(q)" title="Convertir a paquete">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </button>
                <button v-if="canEdit(q)" class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(q)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button v-if="canEdit(q)" class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(q)" title="Eliminar">
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
      <template v-if="loading || paginating">
        <div v-for="i in 3" :key="i" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between"><div class="h-4 w-1/3 animate-pulse rounded bg-surface-raised" /><div class="h-5 w-16 animate-pulse rounded-full bg-surface-raised" /></div>
          <div class="h-3.5 w-1/2 animate-pulse rounded bg-surface-raised" />
          <div class="border-t border-line pt-3"><div class="flex gap-2"><div class="h-7 w-16 animate-pulse rounded bg-surface-raised" /><div class="h-7 w-16 animate-pulse rounded bg-surface-raised" /></div></div>
        </div>
      </template>
      <template v-else-if="quotes.length > 0">
        <div v-for="q in quotes" :key="q.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-content truncate">{{ q.quote_number }}</p>
            <span class="admin-badge shrink-0" :class="statusInfo[q.status]?.cls || ''">{{ statusInfo[q.status]?.label || q.status }}</span>
          </div>
          <p class="text-sm text-muted truncate">{{ q.recipient_name }}</p>
          <div class="flex items-center justify-between">
            <p class="text-base font-bold text-content">{{ formatUSD(q.total_amount) }}</p>
            <p class="text-xs text-subtle">{{ q.agent_name || '—' }} · {{ fmtDate(q.created_at) }}</p>
          </div>
          <div class="border-t border-line pt-3 flex flex-wrap items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openDetail(q.id)">Ver</button>
            <button v-if="canConvert(q)" class="admin-btn admin-btn-ghost admin-btn-sm text-primary" @click="openConvert(q)">Convertir</button>
            <button v-if="canEdit(q)" class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(q)">Editar</button>
            <button v-if="canEdit(q)" class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="openDelete(q)">Eliminar</button>
          </div>
        </div>
      </template>
      <div v-else class="admin-card">
        <div class="admin-empty-state py-12">
          <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <p class="admin-empty-state-title">Sin cotizaciones</p>
          <p class="admin-empty-state-desc">Creá la primera cotización.</p>
        </div>
      </div>
      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? `Editar ${editing.quote_number}` : 'Nueva cotización'" size="lg" @close="showModal = false">
      <form class="space-y-5" @submit.prevent="save">
        <!-- Destinatario -->
        <div>
          <div class="admin-form-section">Destinatario</div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AppFormField :model-value="fv('recipient_name')" @update:model-value="(v: string | number | null) => form.recipient_name = String(v ?? '')" label="Nombre" icon="user" required :disabled="saving" :maxlength="255" />
            <AppFormField :model-value="fv('recipient_phone')" @update:model-value="(v: string | number | null) => form.recipient_phone = String(v ?? '')" label="Teléfono" icon="phone" :disabled="saving" :maxlength="50" />
            <AppFormField :model-value="fv('recipient_email')" @update:model-value="(v: string | number | null) => form.recipient_email = String(v ?? '')" label="Email" type="email" icon="mail" :disabled="saving" />
            <AppFormField :model-value="fv('recipient_ruc')" @update:model-value="(v: string | number | null) => form.recipient_ruc = String(v ?? '')" label="RUC o cédula" :disabled="saving" :maxlength="30" />
            <div class="sm:col-span-2">
              <AppFormField :model-value="fv('recipient_address')" @update:model-value="(v: string | number | null) => form.recipient_address = String(v ?? '')" label="Dirección" type="textarea" icon="map-pin" :rows="2" :disabled="saving" :maxlength="500" />
            </div>
            <AppFormField :model-value="fv('recipient_city')" @update:model-value="(v: string | number | null) => form.recipient_city = String(v ?? '')" label="Ciudad" :disabled="saving" :maxlength="100" />
            <AppFormField :model-value="fv('recipient_department')" @update:model-value="(v: string | number | null) => form.recipient_department = String(v ?? '')" label="Departamento" :disabled="saving" :maxlength="100" />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Configuración de envío -->
        <div>
          <div class="admin-form-section">Configuración de envío</div>
          <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-content">Origen</label>
              <AppDropdown :model-value="form.origin" @update:model-value="(v: string | number | null) => form.origin = (v === 'local' ? 'local' : 'online')" :options="originOptions" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-content">Cliente (opcional)</label>
              <AppDropdown :model-value="form.client_id" @update:model-value="(v: string | number | null) => form.client_id = v == null || v === '' ? null : Number(v)" :options="clientOptions" placeholder="Sin cliente asociado" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-content">Zona</label>
              <AppDropdown :model-value="form.zone_id" @update:model-value="(v: string | number | null) => form.zone_id = v == null || v === '' ? null : Number(v)" :options="zoneOptions" placeholder="Seleccionar zona" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-content">Tipo de entrega</label>
              <AppDropdown :model-value="form.delivery_type || null" @update:model-value="(v: string | number | null) => form.delivery_type = String(v ?? '')" :options="deliveryTypeOpts" placeholder="Seleccionar tipo" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-content">Servicio</label>
              <AppDropdown :model-value="form.shipping_service || null" @update:model-value="(v: string | number | null) => form.shipping_service = String(v ?? '')" :options="serviceOpts" placeholder="Seleccionar servicio" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-content">Opción de entrega</label>
              <AppDropdown :model-value="form.delivery_option || null" @update:model-value="(v: string | number | null) => form.delivery_option = String(v ?? '')" :options="optionOpts" placeholder="Seleccionar opción" :disabled="saving" />
            </div>
            <div>
              <AppFormField :model-value="fv('valid_until')" @update:model-value="(v: string | number | null) => form.valid_until = String(v ?? '')" label="Válida hasta" type="date" icon="calendar" :disabled="saving" />
            </div>
            <div v-if="!isVendor">
              <label class="mb-1.5 block text-sm font-medium text-content">Vendedor asignado</label>
              <AppDropdown :model-value="form.agent_id" @update:model-value="(v: string | number | null) => form.agent_id = v == null || v === '' ? null : Number(v)" :options="agentOptions" placeholder="Sin asignar" :disabled="saving" />
            </div>
            <div class="flex items-end">
              <label class="flex items-center gap-3 rounded-lg border border-line px-3 py-2 w-full">
                <AppSwitch :model-value="form.pickup_required" @update:model-value="(v: boolean) => form.pickup_required = v" />
                <span class="text-sm text-muted">Requiere recolección</span>
              </label>
            </div>
            <div v-if="form.pickup_required">
              <label class="mb-1.5 block text-sm font-medium text-content">Vehículo de recolección</label>
              <AppDropdown
                :model-value="form.pickup_vehicle_id"
                @update:model-value="(v: string | number | null) => form.pickup_vehicle_id = v == null || v === '' ? null : Number(v)"
                :options="vehicleOptions"
                placeholder="Sin asignar"
                :disabled="saving"
              />
            </div>
          </div>
          <div class="mt-4">
            <AppFormField :model-value="fv('notes')" @update:model-value="(v: string | number | null) => form.notes = String(v ?? '')" label="Notas" type="textarea" icon="file-text" :rows="2" :disabled="saving" :maxlength="1000" />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Ítems -->
        <div>
          <div class="flex items-center justify-between">
            <div class="admin-form-section">Ítems (1 ítem = 1 paquete al convertir)</div>
            <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" @click="addItem" :disabled="saving">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
              Agregar ítem
            </button>
          </div>
          <div class="mt-3 space-y-3">
            <div v-for="(item, idx) in form.items" :key="idx" class="rounded-xl border border-line bg-surface-sunken p-3 space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-subtle">Ítem {{ idx + 1 }}</span>
                <button v-if="form.items.length > 1" type="button" class="admin-btn admin-btn-ghost admin-btn-sm text-danger" @click="removeItem(idx)" :disabled="saving">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
              </div>
              <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="col-span-2">
                  <AppFormField :model-value="item.description ?? ''" @update:model-value="(v: string | number | null) => item.description = String(v ?? '')" label="Descripción" :disabled="saving" :maxlength="500" />
                </div>
                <AppFormField :model-value="item.quantity ?? 1" @update:model-value="(v: string | number | null) => item.quantity = Number(v ?? 1)" label="Cantidad" type="number" :min="1" :disabled="saving" />
                <AppFormField :model-value="item.weight ?? null" @update:model-value="(v: string | number | null) => item.weight = v == null ? null : Number(v)" label="Peso (lb)" type="number" :step="0.01" :min="0" :disabled="saving" />
                <AppFormField :model-value="item.length ?? null" @update:model-value="(v: string | number | null) => item.length = v == null ? null : Number(v)" label="Largo" type="number" :step="0.01" :min="0" :disabled="saving" />
                <AppFormField :model-value="item.width ?? null" @update:model-value="(v: string | number | null) => item.width = v == null ? null : Number(v)" label="Ancho" type="number" :step="0.01" :min="0" :disabled="saving" />
                <AppFormField :model-value="item.height ?? null" @update:model-value="(v: string | number | null) => item.height = v == null ? null : Number(v)" label="Alto" type="number" :step="0.01" :min="0" :disabled="saving" />
                <AppFormField :model-value="item.declared_value ?? null" @update:model-value="(v: string | number | null) => item.declared_value = v == null ? null : Number(v)" label="Valor declarado ($)" type="number" :step="0.01" :min="0" :disabled="saving" />
              </div>
            </div>
          </div>
          <p class="mt-2 text-xs text-subtle">Los costos de flete y entrega se calculan automáticamente con las reglas de pricing al guardar.</p>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button class="admin-btn admin-btn-primary" @click="save" :disabled="saving || !form.recipient_name.trim()">
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Convert Confirmation -->
    <AppConfirmDialog
      :visible="converting !== null"
      title="Convertir cotización"
      :message="`Se generará un paquete por cada ítem de ${converting?.quote_number}. ¿Continuar?`"
      confirm-text="Convertir"
      @confirm="confirmConvert"
      @cancel="converting = null"
    />

    <!-- Delete Confirmation -->
    <AppConfirmDialog
      :visible="deleting !== null"
      title="Eliminar cotización"
      :message="`¿Seguro que querés eliminar ${deleting?.quote_number}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="deleting = null"
    />
  </div>
</template>
