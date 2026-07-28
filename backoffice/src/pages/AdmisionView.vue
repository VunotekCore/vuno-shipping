<script setup lang="ts">
import { ref, computed, watch, reactive, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { packageService, type Package } from '../services/packageService'
import { clientService, type Client } from '../services/clientService'
import { zoneService, type Zone } from '../services/zoneService'
import { pricingRuleService, type PricingRule } from '../services/pricingRuleService'
import { useToast } from '../composables/useToast'
import { formatCurrency } from '../utils/format'
import QRCode from 'qrcode'
import AppFormField from '../components/ui/AppFormField.vue'
import AppPhoneInput from '../components/ui/AppPhoneInput.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'

const router = useRouter()
const toast = useToast()

const creating = ref(false)
const pricingLoading = ref(false)
const clienteRef = ref<HTMLElement | null>(null)
const pricingOffset = ref(96)
const pricingResult = ref<{
  volumetric_weight: number
  chargeable_weight: number
  flete_cost: number
  delivery_cost: number
  total_amount: number
  rule_name: string | null
} | null>(null)

const createdPackage = ref<Package | null>(null)
const qrDataUrl = ref<string | null>(null)

// Reference data
const clients = ref<Client[]>([])
const zones = ref<Zone[]>([])
const pricingRules = ref<PricingRule[]>([])
const deliveryTypes = ref<{ id: number; name: string; description: string | null }[]>([])
const loadingReferences = ref(true)
const showClientCreate = ref(false)
const clientSearch = ref('')
const clientCreating = ref(false)

const selectedPricingRule = computed(() =>
  pricingRules.value.find(r => r.id === form.pricing_rule_id) ?? null
)

function pricingRuleLabel(rule: PricingRule): string {
  switch (rule.type_name) {
    case 'weight':
      return `${rule.name} — $${rule.price_per_kg?.toFixed(2) ?? '0.00'}/lb`
    case 'volume':
      return `${rule.name} — $${rule.price_per_volume?.toFixed(2) ?? '0.00'}/vol`
    case 'percentage value':
      return `${rule.name} — $${rule.base_price.toFixed(2)} base + ${rule.percentage_of_value}% valor`
    default:
      return rule.name
  }
}

// Form data
const form = reactive({
  client_id: null as number | null,
  sender_name: '',
  sender_phone: '',
  recipient_name: '',
  recipient_phone: '',
  recipient_address: '',
  recipient_city: '',
  recipient_department: '',
  zone_id: null as number | null,
  description: '',
  weight: null as number | null,
  length: null as number | null,
  width: null as number | null,
  height: null as number | null,
  declared_value: null as number | null,
  delivery_type: 'door',
  pricing_rule_id: null as number | null,
  notes: '',
})

// New client form
const newClient = reactive({
  name: '',
  email: '',
  phone: '',
  address: '',
  showExtra: false,
})

const filteredClients = computed(() => {
  if (!clientSearch.value) return clients.value.slice(0, 20)
  const q = clientSearch.value.toLowerCase()
  return clients.value.filter(
    (c) => c.name.toLowerCase().includes(q) || (c.phone && c.phone.includes(q))
  ).slice(0, 20)
})

const selectedClient = computed(() => {
  if (!form.client_id) return null
  return clients.value.find((c) => c.id === form.client_id) || null
})

const deliveryTypeLabel = computed(() => {
  const dt = deliveryTypes.value.find((d) => d.name === form.delivery_type)
  return dt?.description || form.delivery_type
})

const visibleFields = computed(() => {
  const type = selectedPricingRule.value?.type_name
  if (type === 'weight') return { weight: true, dims: false, declared: false }
  if (type === 'volume') return { weight: false, dims: true, declared: false }
  if (type === 'percentage value') return { weight: false, dims: false, declared: true }
  return { weight: true, dims: true, declared: true }
})

// Normalize numeric fields from PHP (DECIMAL → string → number)
function normalizePricingRule(r: PricingRule): PricingRule {
  return {
    ...r,
    base_price: Number(r.base_price),
    price_per_kg: r.price_per_kg != null ? Number(r.price_per_kg) : null,
    price_per_volume: r.price_per_volume != null ? Number(r.price_per_volume) : null,
    min_price: r.min_price != null ? Number(r.min_price) : null,
    percentage_of_value: Number(r.percentage_of_value),
  }
}

async function loadReferenceData() {
  loadingReferences.value = true
  const load = async <T>(fn: () => Promise<{ data: { data?: { [key: string]: T[] } } }>, key: string): Promise<T[]> => {
    try {
      const res = await fn()
      return (res.data.data?.[key] as T[]) ?? []
    } catch {
      console.error(`Error loading ${key}`)
      return []
    }
  }
  const [clientsData, zonesData, rulesData, dtData] = await Promise.all([
    load(clientService.listAll, 'clients'),
    load(zoneService.listAll, 'zones'),
    load(pricingRuleService.listAll, 'pricing_rules'),
    load(pricingRuleService.listDeliveryTypes, 'delivery_types'),
  ])
  clients.value = clientsData
  zones.value = zonesData
  pricingRules.value = rulesData.map(normalizePricingRule)
  deliveryTypes.value = dtData
  loadingReferences.value = false
  await nextTick()
  if (clienteRef.value) {
    pricingOffset.value = clienteRef.value.getBoundingClientRect().top
  }
}

function selectClient(client: Client | null) {
  form.client_id = client?.id ?? null
  if (client) {
    form.sender_name = client.name
    form.sender_phone = client.phone || ''
  } else {
    form.sender_name = ''
    form.sender_phone = ''
  }
  clientSearch.value = ''
  showClientCreate.value = false
}

async function createClient() {
  if (!newClient.name) return
  clientCreating.value = true
  try {
    const res = await clientService.create({
      name: newClient.name,
      email: newClient.email || undefined,
      phone: newClient.phone || undefined,
      address: newClient.address || undefined,
    })
    const created = res.data.data?.client
    if (created) {
      clients.value.unshift(created)
      selectClient(created)
      newClient.name = ''
      newClient.email = ''
      newClient.phone = ''
      newClient.address = ''
      newClient.showExtra = false
      toast.success('Cliente creado')
    }
  } catch {
    toast.error('Error al crear cliente')
  } finally {
    clientCreating.value = false
  }
}

const canCreate = computed(() => {
  return !!(
    form.sender_name &&
    form.recipient_name &&
    form.recipient_phone &&
    form.recipient_address &&
    pricingResult.value
  )
})

// Pricing preview
async function calculatePricing() {
  const weight = form.weight ?? 0
  const length = form.length ?? 0
  const width = form.width ?? 0
  const height = form.height ?? 0
  const dt = deliveryTypes.value.find((d) => d.name === form.delivery_type)

  if (!dt || (weight <= 0 && length <= 0)) {
    return
  }
  if (!form.pricing_rule_id) {
    pricingResult.value = null
    return
  }

  pricingLoading.value = true
  try {
    const res = await api.post('/pricing/calculate.php', {
      weight,
      length,
      width,
      height,
      declared_value: form.declared_value ?? 0,
      delivery_type_id: dt.id,
      zone_id: form.zone_id,
      pricing_rule_id: form.pricing_rule_id,
    })
    const body = res.data
    if (body.success && body.data?.pricing) {
      pricingResult.value = body.data.pricing
    } else {
      pricingResult.value = null
    }
  } catch {
    pricingResult.value = null
  } finally {
    pricingLoading.value = false
  }
}

// Reset pricing rule when delivery type changes (B1)
watch(() => form.delivery_type, () => {
  form.pricing_rule_id = null
})

// Clear hidden fields when rule type changes
watch(selectedPricingRule, (rule) => {
  const type = rule?.type_name
  if (type === 'weight') {
    form.length = null
    form.width = null
    form.height = null
    form.declared_value = null
  } else if (type === 'volume') {
    form.weight = null
    form.declared_value = null
  } else if (type === 'percentage value') {
    form.weight = null
    form.length = null
    form.width = null
    form.height = null
  } else if (type && !['weight', 'volume', 'percentage value'].includes(type)) {
    form.weight = null
    form.length = null
    form.width = null
    form.height = null
    form.declared_value = null
  }
})

const pricingDebounce = ref<ReturnType<typeof setTimeout> | null>(null)
watch(
  () => [form.weight, form.length, form.width, form.height, form.delivery_type, form.zone_id, form.declared_value, form.pricing_rule_id],
  () => {
    if (pricingDebounce.value) clearTimeout(pricingDebounce.value)
    pricingDebounce.value = setTimeout(calculatePricing, 400)
  },
  { deep: true }
)

// Create package
async function handleCreate() {
  if (!form.sender_name || !form.recipient_name || !form.recipient_phone || !form.recipient_address) {
    toast.error('Completá los campos obligatorios')
    return
  }

  creating.value = true
  try {
    const payload: Record<string, unknown> = {
      sender_name: form.sender_name,
      sender_phone: form.sender_phone || undefined,
      recipient_name: form.recipient_name,
      recipient_phone: form.recipient_phone,
      recipient_address: form.recipient_address,
      delivery_type: form.delivery_type,
    }
    if (form.client_id) payload.client_id = form.client_id
    if (form.recipient_city) payload.recipient_city = form.recipient_city
    if (form.recipient_department) payload.recipient_department = form.recipient_department
    if (form.zone_id) payload.zone_id = form.zone_id
    if (form.description) payload.description = form.description
    if (form.weight) payload.weight = form.weight
    if (form.length) payload.length = form.length
    if (form.width) payload.width = form.width
    if (form.height) payload.height = form.height
    if (form.declared_value) payload.declared_value = form.declared_value
    if (form.pricing_rule_id) payload.pricing_rule_id = form.pricing_rule_id
    if (form.notes) payload.notes = form.notes

    const res = await packageService.create(payload as any)
    const pkg = res.data.data?.package
    if (pkg) {
      createdPackage.value = pkg
      // Generate QR
      const baseUrl = import.meta.env.VITE_SITE_URL || window.location.origin
      const trackingUrl = `${baseUrl}/rastreo?tracking=${pkg.tracking_number}${pkg.tracking_token ? '&token=' + pkg.tracking_token : ''}`
      qrDataUrl.value = await QRCode.toDataURL(trackingUrl, { width: 240, margin: 2 })
      toast.success(`Paquete ${pkg.tracking_number} creado`)
    }
  } catch {
    toast.error('Error al crear el paquete')
  } finally {
    creating.value = false
  }
}

function printLabel() {
  if (!createdPackage.value) return
  window.open(packageService.getLabelUrl(createdPackage.value.id), '_blank')
}

function goToDetail() {
  if (!createdPackage.value) return
  router.push(`/packages/${createdPackage.value.id}`)
}

function resetForm() {
  form.client_id = null
  form.sender_name = ''
  form.sender_phone = ''
  form.recipient_name = ''
  form.recipient_phone = ''
  form.recipient_address = ''
  form.recipient_city = ''
  form.recipient_department = ''
  form.zone_id = null
  form.description = ''
  form.weight = null
  form.length = null
  form.width = null
  form.height = null
  form.declared_value = null
  form.delivery_type = 'door'
  form.pricing_rule_id = null
  form.notes = ''
  clientSearch.value = ''
  createdPackage.value = null
  qrDataUrl.value = null
  pricingResult.value = null
}

onMounted(() => loadReferenceData())
</script>

<template>
  <div class="space-y-6">
    <!-- Page header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Admisión de Envíos</h1>
        <p class="mt-1 text-sm text-[#64748b]">Registrá un nuevo paquete en el sistema</p>
      </div>
    </div>

    <!-- Loading skeleton (B4) -->
    <template v-if="loadingReferences">
      <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
          <div v-for="i in 4" :key="i" class="admin-card p-5 space-y-4">
            <div class="h-4 w-24 animate-pulse rounded bg-[#1e293b]" />
            <div class="h-10 w-full animate-pulse rounded bg-[#1e293b]" />
            <div class="grid grid-cols-2 gap-3">
              <div class="h-10 animate-pulse rounded bg-[#1e293b]" />
              <div class="h-10 animate-pulse rounded bg-[#1e293b]" />
            </div>
          </div>
        </div>
        <div class="space-y-4">
          <div class="admin-card p-5 space-y-3">
            <div class="h-4 w-20 animate-pulse rounded bg-[#1e293b]" />
            <div v-for="i in 4" :key="i" class="h-4 animate-pulse rounded bg-[#1e293b]" />
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="!createdPackage">
      <!-- Form -->
      <div class="grid gap-6 lg:grid-cols-1 lg:mr-[380px]">
        <!-- Left: form fields -->
        <div class="space-y-6">
          <!-- Cliente -->
          <div ref="clienteRef" class="admin-card p-5">
            <p class="admin-form-section mb-4">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
              Cliente</p>

            <div class="space-y-3">
              <!-- Search existing -->
              <div class="relative" v-if="!showClientCreate">
                <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Buscar cliente existente</label>
                <input
                  v-model="clientSearch"
                  class="admin-input w-full"
                  placeholder="Nombre o teléfono..."
                  autocomplete="off"
                />
                <div
                  v-if="clientSearch && filteredClients.length > 0 && !selectedClient"
                  class="absolute left-0 right-0 top-full z-10 mt-1 max-h-48 overflow-y-auto rounded-lg border border-[#1e293b] bg-[#111d2e] shadow-xl"
                >
                  <button
                    v-for="c in filteredClients"
                    :key="c.id"
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-[#dae2fd] transition-colors hover:bg-white/[0.04]"
                    @click="selectClient(c)"
                  >
                    <span>{{ c.name }}</span>
                    <span class="text-xs text-[#64748b]">{{ c.phone || '—' }}</span>
                  </button>
                </div>
              </div>

              <div v-if="selectedClient" class="flex items-center justify-between rounded-lg bg-[#1e293b]/50 px-3 py-2">
                <div>
                  <span class="text-sm text-[#dae2fd]">{{ selectedClient.name }}</span>
                  <span class="ml-2 text-xs text-[#64748b]">{{ selectedClient.phone || '—' }}</span>
                </div>
                <button class="text-xs text-[#00A8FF] hover:underline" @click="selectClient(null)">Cambiar</button>
              </div>

              <div v-if="!showClientCreate && !selectedClient" class="text-sm text-[#64748b] italic">
                Sin cliente — envío ocasional
                <button class="ml-2 text-[#00A8FF] hover:underline" @click="showClientCreate = true">Crear cliente</button>
              </div>

              <!-- Quick-create client -->
              <div v-if="showClientCreate" class="rounded-lg border border-[#1e293b] p-3 space-y-3">
                <p class="text-sm font-medium text-[#dae2fd]">Nuevo cliente</p>
                <input v-model="newClient.name" class="admin-input w-full" placeholder="Nombre *" />
                <div class="grid grid-cols-2 gap-3">
                  <input v-model="newClient.email" class="admin-input w-full" placeholder="Email" type="email" />
                  <input v-model="newClient.phone" class="admin-input w-full" placeholder="Teléfono" />
                </div>
                <!-- Extra fields toggle -->
                <button
                  class="flex items-center gap-1 text-xs text-[#64748b] hover:text-[#94a3b8] transition-colors"
                  @click="newClient.showExtra = !newClient.showExtra"
                >
                  <svg
                    width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    class="transition-transform duration-150"
                    :class="newClient.showExtra ? 'rotate-90' : ''"
                  >
                    <polyline points="9 18 15 12 9 6" />
                  </svg>
                  Más datos
                </button>
                <div v-if="newClient.showExtra" class="space-y-3">
                  <textarea v-model="newClient.address" class="admin-input w-full" rows="2" placeholder="Dirección" />
                </div>
                <div class="flex gap-2 pt-1">
                  <button class="admin-btn admin-btn-primary admin-btn-sm" :disabled="!newClient.name || clientCreating" @click="createClient">
                    {{ clientCreating ? 'Creando...' : 'Guardar y seleccionar' }}
                  </button>
                  <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="showClientCreate = false">Cancelar</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Remitente -->
          <div class="admin-card p-5">
            <p class="admin-form-section mb-4">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Remitente
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
              <AppFormField
                :model-value="form.sender_name"
                @update:model-value="(v: string | number | null) => form.sender_name = String(v ?? '')"
                label="Nombre *"
                icon="user"
                placeholder="Nombre completo"
                :required="true"
                :disabled="creating"
              />
              <AppPhoneInput
                :model-value="form.sender_phone"
                @update:model-value="(v: string) => form.sender_phone = v"
                label="Teléfono"
                placeholder="1234-5678"
                :disabled="creating"
              />
            </div>
          </div>

          <!-- Destinatario -->
          <div class="admin-card p-5" style="overflow: visible">
            <p class="admin-form-section mb-4">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
              Destinatario
            </p>
            <div class="space-y-3">
              <div class="grid gap-3 sm:grid-cols-2">
                <AppFormField
                  :model-value="form.recipient_name"
                  @update:model-value="(v: string | number | null) => form.recipient_name = String(v ?? '')"
                  label="Nombre *"
                  icon="user"
                  placeholder="Nombre completo"
                  :required="true"
                  :disabled="creating"
                />
                <AppPhoneInput
                  :model-value="form.recipient_phone"
                  @update:model-value="(v: string) => form.recipient_phone = v"
                  label="Teléfono *"
                  placeholder="1234-5678"
                  :required="true"
                  :disabled="creating"
                />
              </div>
              <AppFormField
                :model-value="form.recipient_address"
                @update:model-value="(v: string | number | null) => form.recipient_address = String(v ?? '')"
                label="Dirección *"
                type="textarea"
                icon="map-pin"
                placeholder="Dirección completa"
                :required="true"
                :disabled="creating"
                :rows="2"
              />
              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <AppFormField
                  :model-value="form.recipient_city"
                  @update:model-value="(v: string | number | null) => form.recipient_city = String(v ?? '')"
                  label="Ciudad"
                  icon="map-pin"
                  placeholder="Ciudad"
                  :disabled="creating"
                />
                <AppFormField
                  :model-value="form.recipient_department"
                  @update:model-value="(v: string | number | null) => form.recipient_department = String(v ?? '')"
                  label="Departamento"
                  icon="map-pin"
                  placeholder="Depto."
                  :disabled="creating"
                />
                <div style="overflow: visible">
                  <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Zona de entrega</label>
                  <AppDropdown
                    :model-value="form.zone_id"
                    @update:model-value="(v: string | number | null) => form.zone_id = v as number | null"
                    :options="zones.map(z => ({ value: z.id, label: z.name }))"
                    placeholder="Seleccionar zona..."
                    :disabled="creating"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Paquete -->
          <div class="admin-card p-5">
            <p class="admin-form-section mb-4">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
              Paquete
            </p>
            <div class="space-y-3">
              <AppFormField
                :model-value="form.description"
                @update:model-value="(v: string | number | null) => form.description = String(v ?? '')"
                label="Descripción"
                type="textarea"
                icon="file-text"
                placeholder="Contenido del paquete..."
                :disabled="creating"
                :rows="2"
              />
              <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#64748b]">Regla de precio</label>
                <AppDropdown
                  :model-value="form.pricing_rule_id"
                  @update:model-value="(v: string | number | null) => form.pricing_rule_id = v as number | null"
                  :options="pricingRules.map(r => ({ value: r.id, label: pricingRuleLabel(r) }))"
                  :placeholder="pricingRules.length === 0 ? 'Sin reglas de precio' : 'Seleccionar regla de precio...'"
                />
              </div>
              <div v-if="visibleFields.weight" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <AppFormField
                  :model-value="form.weight"
                  @update:model-value="(v: string | number | null) => form.weight = v !== '' ? Number(v) : null"
                  label="Peso (lb)"
                  type="number"
                  placeholder="0.00"
                  :disabled="creating"
                  :min="0"
                  :step="0.01"
                />
              </div>
              <div v-if="visibleFields.dims" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <AppFormField
                  :model-value="form.length"
                  @update:model-value="(v: string | number | null) => form.length = v !== '' ? Number(v) : null"
                  label="Largo (in)"
                  type="number"
                  placeholder="0"
                  :disabled="creating"
                  :min="0"
                  :step="0.1"
                />
                <AppFormField
                  :model-value="form.width"
                  @update:model-value="(v: string | number | null) => form.width = v !== '' ? Number(v) : null"
                  label="Ancho (in)"
                  type="number"
                  placeholder="0"
                  :disabled="creating"
                  :min="0"
                  :step="0.1"
                />
                <AppFormField
                  :model-value="form.height"
                  @update:model-value="(v: string | number | null) => form.height = v !== '' ? Number(v) : null"
                  label="Alto (in)"
                  type="number"
                  placeholder="0"
                  :disabled="creating"
                  :min="0"
                  :step="0.1"
                />
              </div>
              <AppFormField
                v-if="visibleFields.declared"
                :model-value="form.declared_value"
                @update:model-value="(v: string | number | null) => form.declared_value = v !== '' ? Number(v) : null"
                label="Valor declarado ($)"
                type="number"
                icon="dollar-sign"
                placeholder="0.00"
                :disabled="creating"
                :min="0"
                :step="0.01"
                class="max-w-xs"
              />
            </div>
          </div>

          <!-- Tipo de entrega -->
          <div class="admin-card p-5">
            <p class="admin-form-section mb-4">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              Tipo de entrega</p>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="dt in deliveryTypes"
                :key="dt.id"
                :class="[
                  'rounded-lg border px-4 py-2 text-sm font-medium transition-all',
                  form.delivery_type === dt.name
                    ? 'border-[#69dca4] bg-[#69dca4]/10 text-[#69dca4]'
                    : 'border-[#1e293b] text-[#94a3b8] hover:border-[#404944] hover:text-[#dae2fd]'
                ]"
                @click="form.delivery_type = dt.name"
              >
                {{ dt.description || dt.name }}
              </button>
            </div>
          </div>

          <!-- Submit -->
          <div class="flex flex-col gap-3 sm:flex-row">
            <button
              class="admin-btn admin-btn-primary w-full sm:w-auto"
              :disabled="creating || !canCreate"
              :title="!pricingResult ? 'Seleccioná tipo de entrega, peso y regla de precio para calcular el costo' : ''"
              @click="handleCreate"
            >
              <template v-if="creating">Creando...</template>
              <template v-else>
                <svg class="-ml-1 mr-1.5 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Crear paquete
              </template>
            </button>
            <button class="admin-btn admin-btn-ghost w-full sm:w-auto" @click="resetForm">Limpiar</button>
          </div>
        </div>

      </div>

      <!-- Pricing panel: fixed on desktop, in-flow on mobile -->
      <div class="lg:fixed lg:right-8 lg:w-[340px] lg:z-10 space-y-4" :style="`top: ${pricingOffset}px`">
        <div class="admin-card p-5">
          <p class="admin-form-section mb-4">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            Cotización</p>

          <div v-if="pricingLoading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="h-4 animate-pulse rounded bg-[#1e293b]" />
          </div>

          <div v-else-if="pricingResult" class="space-y-3">
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#94a3b8]">Peso físico</span>
              <span class="text-[#dae2fd] font-medium">{{ form.weight ? form.weight.toFixed(2) : '0' }} lb</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#94a3b8]">Peso volumétrico</span>
              <span class="text-[#dae2fd] font-medium">{{ pricingResult.volumetric_weight.toFixed(2) }} lb</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-[#94a3b8]">Peso cobrable</span>
              <span class="text-[#dae2fd] font-medium">{{ pricingResult.chargeable_weight.toFixed(2) }} lb</span>
            </div>
            <div class="border-t border-[#1e293b] pt-2">
              <div class="flex items-center justify-between text-sm">
                <span class="text-[#94a3b8]">Flete</span>
                <span class="text-[#dae2fd] font-medium">{{ formatCurrency(pricingResult.flete_cost) }}</span>
              </div>
              <div class="flex items-center justify-between text-sm">
                <span class="text-[#94a3b8]">Delivery</span>
                <span class="text-[#dae2fd] font-medium">{{ formatCurrency(pricingResult.delivery_cost) }}</span>
              </div>
            </div>
            <div class="border-t border-[#1e293b] pt-2">
              <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-[#dae2fd]">Total</span>
                <span class="text-lg font-bold text-[#69dca4]">{{ formatCurrency(pricingResult.total_amount) }}</span>
              </div>
            </div>
            <div v-if="selectedPricingRule" class="rounded-lg bg-[#1a2744]/50 p-3 space-y-1">
              <p class="text-xs font-medium text-[#69dca4]">{{ selectedPricingRule.name }}</p>
              <p class="text-[11px] text-[#64748b]">
                {{ selectedPricingRule.type_name === 'weight' && selectedPricingRule.price_per_kg ? `$${selectedPricingRule.price_per_kg.toFixed(2)}/lb` : '' }}
                {{ selectedPricingRule.type_name === 'percentage value' ? `$${selectedPricingRule.base_price.toFixed(2)} base + ${selectedPricingRule.percentage_of_value}% del valor` : '' }}
                {{ selectedPricingRule.type_name === 'volume' && selectedPricingRule.price_per_volume ? `$${selectedPricingRule.price_per_volume.toFixed(2)}/vol` : '' }}
                {{ selectedPricingRule.min_price && selectedPricingRule.min_price > 0 ? `· Mín $${selectedPricingRule.min_price.toFixed(2)}` : '' }}
              </p>
            </div>
          </div>

          <div v-else class="py-6 text-center">
            <svg class="mx-auto mb-2 h-8 w-8 text-[#475569]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-[#64748b]">Seleccioná un tipo de entrega, peso y regla de precio para cotizar</p>
          </div>
        </div>

        <!-- Reference info -->
        <div class="admin-card p-4 text-xs text-[#64748b] space-y-1">
          <p><span class="font-medium text-[#94a3b8]">Tipo de entrega:</span> {{ deliveryTypeLabel }}</p>
          <p v-if="form.zone_id">
            <span class="font-medium text-[#94a3b8]">Zona:</span>
            {{ zones.find((z) => z.id === form.zone_id)?.name || '—' }}
          </p>
        </div>
      </div>
    </template>

    <!-- Success state -->
    <template v-else>
      <div class="admin-card p-6 sm:p-8 text-center max-w-lg mx-auto">
        <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-[#69dca4]/15">
          <svg class="h-7 w-7 text-[#69dca4]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
        </div>

        <h2 class="text-xl font-bold text-[#dae2fd]">Paquete registrado</h2>
        <p class="mt-1 text-sm text-[#64748b]">Tracking number asignado</p>

        <p class="mt-4 text-3xl font-bold tracking-wider text-[#69dca4] font-mono">{{ createdPackage.tracking_number }}</p>

        <div v-if="qrDataUrl" class="mt-6 flex justify-center">
          <img :src="qrDataUrl" alt="QR" class="rounded-lg border border-[#1e293b]" width="240" height="240" />
        </div>

        <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
          <button class="admin-btn admin-btn-primary" @click="printLabel">
            <svg class="-ml-1 mr-1.5 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v4H6v-4zM10 2v4h4V2"/></svg>
            Imprimir etiqueta
          </button>
          <button class="admin-btn admin-btn-ghost" @click="goToDetail">Ver detalle</button>
          <button class="admin-btn admin-btn-ghost" @click="resetForm">Nuevo paquete</button>
        </div>
      </div>
    </template>
  </div>
</template>
