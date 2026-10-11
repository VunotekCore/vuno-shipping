<script setup lang="ts">
import { ref, computed, watch, reactive, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { packageService, type Package } from '../services/packageService'
import { clientService, type Client } from '../services/clientService'
import { zoneService, type Zone } from '../services/zoneService'
import { subzoneService, type Subzone } from '../services/subzoneService'
import { pricingRuleService, type PricingRule } from '../services/pricingRuleService'
import { warehouseService, type Warehouse } from '../services/warehouseService'
import { storageCategoryService, type StorageCategory } from '../services/storageCategoryService'
import { storageLocationService, type StorageLocation } from '../services/storageLocationService'
import type { DeliveryOption, Invoice } from '../types'
import { useToast } from '../composables/useToast'
import { formatCurrency } from '../utils/format'
import QRCode from 'qrcode'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { NICARAGUA_MAP_OPTIONS, NICARAGUA_BOUNDS } from '../utils/map'
import AppFormField from '../components/ui/AppFormField.vue'
import AppPhoneInput from '../components/ui/AppPhoneInput.vue'
import AppDropdown from '../components/ui/AppDropdown.vue'
import AppSwitch from '../components/ui/AppSwitch.vue'
import AppModal from '../components/ui/AppModal.vue'

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
const createdInvoice = ref<Invoice | null>(null)
const qrDataUrl = ref<string | null>(null)

// Reference data
const clients = ref<Client[]>([])
const zones = ref<Zone[]>([])
const subzones = ref<Subzone[]>([])
const subzonesLoading = ref(false)
const pricingRules = ref<PricingRule[]>([])
const currentShippingService = ref<{ id: number; name: string; description: string | null } | null>(null)

// Almacenaje: clasificación obligatoria + ubicación auto/editable
const warehouses = ref<Warehouse[]>([])
const storageCategories = ref<StorageCategory[]>([])
const storageLocations = ref<StorageLocation[]>([])
const storageLoading = ref(false)

const filteredDeliveryOptions = ref<DeliveryOption[]>([])

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
  origin: 'online' as 'online' | 'local',
  sender_name: '',
  sender_phone: '',
  recipient_name: '',
  recipient_phone: '',
  // El destinatario es la misma persona que el remitente: se copian nombre y
  // teléfono. NO se copia la dirección (la del remitente es la de Amazon en
  // Miami y la entrega es en Nicaragua).
  use_client_data: true as boolean,
  recipient_address: '',
  recipient_city: '',
  recipient_department: '',
  latitude: null as number | null,
  longitude: null as number | null,
  zone_id: null as number | null,
  description: '',
  weight: null as number | null,
  length: null as number | null,
  width: null as number | null,
  height: null as number | null,
  declared_value: null as number | null,
  shipping_service_id: null as number | null,
  delivery_option: 'branch',
  delivery_option_id: null as number | null,
  pricing_rule_id: null as number | null,
  storage_category_id: null as number | null,
  storage_location_id: null as number | null,
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

// Almacenaje: bodega destino según origen (online→Miami, local→Nicaragua)
const originWarehouseId = computed(() => {
  return warehouses.value.find((w) => w.origin === form.origin)?.id ?? null
})

const storageLocationOptions = computed(() => {
  return [{ value: null as number | null, label: '— Sin ubicación asignada —' }, ...storageLocations.value.map((l) => ({ value: l.id, label: `${l.code}${l.category_name ? ' · ' + l.category_name : ''}` }))]
})

async function loadStorageLocations() {
  const warehouseId = originWarehouseId.value
  if (!warehouseId) {
    storageLocations.value = []
    form.storage_location_id = null
    return
  }
  storageLoading.value = true
  try {
    const res = await storageLocationService.available(warehouseId, form.storage_category_id)
    storageLocations.value = res.data.data?.locations ?? []
    form.storage_location_id = res.data.data?.suggested_location_id ?? null
  } catch {
    storageLocations.value = []
    form.storage_location_id = null
  } finally {
    storageLoading.value = false
  }
}

// Recargar ubicaciones al cambiar origen o clasificación (la ubicación sugerida
// prioriza la clasificación seleccionada).
watch(() => form.origin, () => { loadStorageLocations() })
watch(() => form.storage_category_id, () => { loadStorageLocations() })

// Geo modal: ubicar el punto de entrega en el mapa
const showGeoModal = ref(false)
const geoMapContainer = ref<HTMLElement | null>(null)
const geoSearch = ref('')
const geoSearching = ref(false)
const geoResults = ref<Array<{ display_name: string; lat: number; lon: number }>>([])
const geoError = ref('')
const geoPoint = ref<{ lat: number; lng: number } | null>(null)

let geoMap: L.Map | null = null
let geoMarker: L.CircleMarker | null = null

const geoQuery = computed(() => {
  return [form.recipient_address, form.recipient_city, form.recipient_department || 'Nicaragua']
    .filter(Boolean)
    .join(', ')
})

const hasGeoPoint = computed(() => form.latitude != null && form.longitude != null)

function openGeoModal() {
  showGeoModal.value = true
  geoError.value = ''
  geoResults.value = []
  geoSearch.value = geoQuery.value
  geoPoint.value = hasGeoPoint.value
    ? { lat: Number(form.latitude), lng: Number(form.longitude) }
    : null
  nextTick(() => {
    if (!geoMapContainer.value) return
    geoMap = L.map(geoMapContainer.value, {
      zoomControl: true,
      attributionControl: false,
      ...NICARAGUA_MAP_OPTIONS,
    })
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      bounds: NICARAGUA_BOUNDS,
    }).addTo(geoMap)
    if (geoPoint.value) {
      geoMap.setView([geoPoint.value.lat, geoPoint.value.lng], 16)
      setGeoMarker(geoPoint.value)
    } else {
      geoMap.setView([12.1364, -86.2514], 12)
    }
    // setView no respeta maxBounds: re-centrar dentro del pais si el punto cayo fuera
    geoMap.panInsideBounds(NICARAGUA_BOUNDS, { animate: false })
    geoMap.on('click', (e: L.LeafletMouseEvent) => {
      geoPoint.value = { lat: e.latlng.lat, lng: e.latlng.lng }
      setGeoMarker(geoPoint.value)
    })
    setTimeout(() => geoMap?.invalidateSize(), 60)
  })
}

function closeGeoModal() {
  showGeoModal.value = false
  geoResults.value = []
  if (geoMap) {
    geoMap.remove()
    geoMap = null
  }
  geoMarker = null
}

function setGeoMarker(p: { lat: number; lng: number }) {
  if (!geoMap) return
  if (geoMarker) geoMarker.remove()
  geoMarker = L.circleMarker([p.lat, p.lng], {
    radius: 9,
    fillColor: '#00A8FF',
    color: '#fff',
    weight: 2,
    fillOpacity: 1,
  }).addTo(geoMap)
}

async function geoSearchSubmit() {
  const q = geoSearch.value.trim()
  if (!q) return
  geoSearching.value = true
  geoError.value = ''
  geoResults.value = []
  try {
    const url = `https://nominatim.openstreetmap.org/search?format=json&limit=5&q=${encodeURIComponent(q)}`
    const res = await fetch(url, { headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error('bad status')
    const data = await res.json()
    geoResults.value = (Array.isArray(data) ? data : [])
      .map((r: any) => ({ display_name: r.display_name ?? '', lat: parseFloat(r.lat), lon: parseFloat(r.lon) }))
      .filter((r: any) => !Number.isNaN(r.lat) && !Number.isNaN(r.lon))
    if (geoResults.value.length === 0) {
      geoError.value = 'Sin resultados. Probá con otra búsqueda.'
    }
  } catch {
    geoError.value = 'No se pudo consultar el buscador. Intentá de nuevo.'
  } finally {
    geoSearching.value = false
  }
}

function pickGeoResult(r: { display_name: string; lat: number; lon: number }) {
  geoPoint.value = { lat: r.lat, lng: r.lon }
  if (geoMap) {
    geoMap.setView([r.lat, r.lon], 16)
    geoMap.panInsideBounds(NICARAGUA_BOUNDS, { animate: false })
    setGeoMarker(geoPoint.value)
  }
  geoResults.value = []
}

function confirmGeoPoint() {
  if (!geoPoint.value) {
    toast.error('Marcá el punto en el mapa')
    return
  }
  form.latitude = Number(geoPoint.value.lat.toFixed(8))
  form.longitude = Number(geoPoint.value.lng.toFixed(8))
  closeGeoModal()
  toast.success('Punto de entrega guardado')
}

const deliveryTypeLabel = computed(() => {
  const ssLabel = currentShippingService.value?.description || currentShippingService.value?.name || '—'
  const dopt = filteredDeliveryOptions.value.find((d) => d.name === form.delivery_option)
  const doptLabel = dopt?.description || form.delivery_option
  return `${ssLabel} + ${doptLabel}`
})

const visibleFields = computed(() => {
  const type = selectedPricingRule.value?.type_name
  if (type === 'weight') return { weight: true, dims: false, declared: false }
  if (type === 'volume') return { weight: false, dims: true, declared: false }
  if (type === 'percentage value') return { weight: false, dims: false, declared: true }
  return { weight: false, dims: false, declared: false }
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
  const [clientsData, zonesData, rulesData, warehousesData, categoriesData] = await Promise.all([
    load(clientService.listAll, 'clients'),
    load(zoneService.listAll, 'zones'),
    load(pricingRuleService.listAll, 'pricing_rules'),
    load(warehouseService.listAll, 'warehouses'),
    load(storageCategoryService.listAll, 'storage_categories'),
  ])
  clients.value = clientsData
  zones.value = zonesData
  pricingRules.value = rulesData.map(normalizePricingRule)
  warehouses.value = warehousesData
  storageCategories.value = categoriesData
  filteredDeliveryOptions.value = []
  loadingReferences.value = false
  await nextTick()
  await loadStorageLocations()
  if (clienteRef.value) {
    pricingOffset.value = clienteRef.value.getBoundingClientRect().top
  }
}

/**
 * Copia nombre y teléfono del remitente al destinatario. Solo cuando el switch
 * está activo: si el operador lo desactivó para mandar a un familiar, sus
 * ediciones no se pisan.
 *
 * La dirección, ciudad, departamento y el punto en el mapa NO se copian a
 * propósito: el remitente compra en Amazon y su dirección es en Miami, la
 * entrega es en Nicaragua. Copiarlas metería al conductor en la casa
 * equivocada y rompería el enrutado por zona.
 */
function syncRecipientFromSender() {
  if (!form.use_client_data) return
  form.recipient_name = form.sender_name
  form.recipient_phone = form.sender_phone
}

/** Solo aplica si hay cliente: sin él no hay de dónde copiar. */
const canReuseClient = computed(() => !!form.client_id)

function onToggleUseClientData(value: boolean) {
  form.use_client_data = value
  if (value) syncRecipientFromSender()
}

// El switch se posiciona solo según el origen, porque los dos casos por defecto
// son distintos: comprando en Amazon lo habitual es que el paquete sea para uno
// mismo, mientras que en mostrador el cliente suele traérselo a un familiar.
watch(() => form.origin, (val) => {
  form.use_client_data = val === 'online'
  syncRecipientFromSender()
})

// Si el remitente cambia (cliente distinto, quick-create, corrección manual),
// el destinario lo sigue mientras el switch esté activo.
watch(() => [form.sender_name, form.sender_phone], () => syncRecipientFromSender())

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
  syncRecipientFromSender()
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
    form.storage_category_id &&
    pricingResult.value
  )
})

// Pricing preview
async function calculatePricing() {
  const weight = form.weight ?? 0
  const length = form.length ?? 0
  const width = form.width ?? 0
  const height = form.height ?? 0

  const type = selectedPricingRule.value?.type_name
  const needsWeightOrDims = !type || type === 'weight' || type === 'volume'
  if (needsWeightOrDims && weight <= 0 && length <= 0) {
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
      shipping_service_id: form.shipping_service_id,
      delivery_option: form.delivery_option,
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

// When pricing rule changes, resolve shipping_service + delivery_options via delivery_type_id
watch(selectedPricingRule, async (rule) => {
  if (!rule) {
    form.shipping_service_id = null
    currentShippingService.value = null
    filteredDeliveryOptions.value = []
    return
  }
  const res = await pricingRuleService.resolveFromRule(rule.id).catch(() => null)
  if (res?.data?.data) {
    const { shipping_service, delivery_options } = res.data.data
    currentShippingService.value = shipping_service
    form.shipping_service_id = shipping_service?.id ?? null
    filteredDeliveryOptions.value = (delivery_options || []).map(d => ({ ...d, id: Number(d.id) }))
    const defaultOpt = filteredDeliveryOptions.value.find(d => d.is_default) ?? filteredDeliveryOptions.value[0]
    if (defaultOpt) {
      form.delivery_option = defaultOpt.name
      form.delivery_option_id = defaultOpt.id
    }
  }
})

// Reset zone when switching to branch
watch(() => form.delivery_option, (val) => {
  if (val !== 'door') {
    form.zone_id = null
  }
})

const departmentZone = computed(() => zones.value.find(z => z.name === form.recipient_department) ?? null)

// Cargar municipalidades del departamento y limpiar ciudad al cambiar
watch(departmentZone, async (zone) => {
  if (!zone) {
    subzones.value = []
    form.recipient_city = ''
    return
  }
  subzonesLoading.value = true
  try {
    const res = await subzoneService.byZone(zone.id)
    subzones.value = res.data.data.subzones ?? []
  } catch {
    subzones.value = []
  } finally {
    subzonesLoading.value = false
  }
  if (!subzones.value.some(s => s.name === form.recipient_city)) {
    form.recipient_city = ''
  }
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
  () => [form.weight, form.length, form.width, form.height, form.shipping_service_id, form.delivery_option, form.zone_id, form.declared_value, form.pricing_rule_id],
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
  if (!form.storage_category_id) {
    toast.error('Seleccioná una clasificación de almacenaje')
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
      delivery_option: form.delivery_option,
      origin: form.origin,
    }
    if (form.shipping_service_id && currentShippingService.value) {
      payload.shipping_service = currentShippingService.value.name
    }
    if (form.client_id) payload.client_id = form.client_id
    if (form.recipient_city) payload.recipient_city = form.recipient_city
    if (form.recipient_department) payload.recipient_department = form.recipient_department
    if (form.latitude != null && form.longitude != null) {
      payload.latitude = form.latitude
      payload.longitude = form.longitude
    }
    if (form.zone_id) payload.zone_id = form.zone_id
    if (form.description) payload.description = form.description
    if (form.weight) payload.weight = form.weight
    if (form.length) payload.length = form.length
    if (form.width) payload.width = form.width
    if (form.height) payload.height = form.height
    if (form.declared_value) payload.declared_value = form.declared_value
    if (form.pricing_rule_id) payload.pricing_rule_id = form.pricing_rule_id
    if (form.storage_category_id) payload.storage_category_id = form.storage_category_id
    if (form.storage_location_id) payload.storage_location_id = form.storage_location_id
    if (form.notes) payload.notes = form.notes

    const res = await packageService.create(payload as any)
    const pkg = res.data.data?.package
    if (pkg) {
      createdPackage.value = pkg
      createdInvoice.value = res.data.data?.invoice ?? null
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
  form.origin = 'online'
  form.use_client_data = true
  form.sender_name = ''
  form.sender_phone = ''
  form.recipient_name = ''
  form.recipient_phone = ''
  form.recipient_address = ''
  form.recipient_city = ''
  form.recipient_department = ''
  form.latitude = null
  form.longitude = null
  form.zone_id = null
  form.description = ''
  form.weight = null
  form.length = null
  form.width = null
  form.height = null
  form.declared_value = null
  form.shipping_service_id = null
  currentShippingService.value = null
  form.delivery_option = 'branch'
  form.delivery_option_id = null
  filteredDeliveryOptions.value = []
  form.pricing_rule_id = null
  form.storage_category_id = null
  form.storage_location_id = null
  form.notes = ''
  clientSearch.value = ''
  createdPackage.value = null
  createdInvoice.value = null
  qrDataUrl.value = null
  pricingResult.value = null
  loadStorageLocations()
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
                  <AppPhoneInput
                    :model-value="newClient.phone"
                    @update:model-value="(v: string) => newClient.phone = v"
                    placeholder="1234-5678"
                  />
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

          <!-- Origen del envío -->
          <div class="admin-card p-5">
            <p class="admin-form-section mb-4">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 012-2h4l2 2h8a2 2 0 012 2v2M3 7v8a2 2 0 002 2h3m-9-2h3m0 0V9a2 2 0 012-2h10l3 3v3m-8 2v4h8v-4"/></svg>
              Origen del envío</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <button
                type="button"
                class="flex items-center gap-3 rounded-xl border p-4 text-left transition-colors"
                :class="form.origin === 'online'
                  ? 'border-[#00a8ff]/60 bg-[#00a8ff]/10'
                  : 'border-[#1e293b] bg-transparent hover:border-[#334155]'"
                @click="form.origin = 'online'"
              >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#00a8ff]/15 text-[#00a8ff]">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                </span>
                <span>
                  <span class="block text-sm font-semibold text-[#dae2fd]">Compra EEUU</span>
                  <span class="mt-0.5 block text-xs text-[#64748b]">Recibido en el almacén de Miami, viaja en embarque a Nicaragua</span>
                </span>
              </button>
              <button
                type="button"
                class="flex items-center gap-3 rounded-xl border p-4 text-left transition-colors"
                :class="form.origin === 'local'
                  ? 'border-[#00a8ff]/60 bg-[#00a8ff]/10'
                  : 'border-[#1e293b] bg-transparent hover:border-[#334155]'"
                @click="form.origin = 'local'"
              >
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#00a8ff]/15 text-[#00a8ff]">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 019.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10zM2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>
                </span>
                <span>
                  <span class="block text-sm font-semibold text-[#dae2fd]">Ingreso local</span>
                  <span class="mt-0.5 block text-xs text-[#64748b]">Cliente lo entrega en la oficina, listo para distribución</span>
                </span>
              </button>
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
              <!-- El destinatario es el mismo cliente? Solo aplica si hay cliente -->
              <div v-if="canReuseClient" class="rounded-lg border border-[#1e293b] bg-[#1e293b]/40 p-3">
                <div class="flex items-start gap-3">
                  <AppSwitch
                    :model-value="form.use_client_data"
                    :disabled="creating"
                    class="mt-0.5"
                    @update:model-value="onToggleUseClientData"
                  />
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-[#dae2fd]">El destinatario es la misma persona</p>
                    <p v-if="form.use_client_data" class="mt-0.5 truncate text-xs text-[#69dca4]">
                      Se usará: {{ form.sender_name || '—' }}<span v-if="form.sender_phone"> · {{ form.sender_phone }}</span>
                    </p>
                    <p v-else class="mt-0.5 text-xs text-[#64748b]">
                      Nombre y teléfono precargados del cliente. Edítalos si el paquete va a otra persona.
                    </p>
                  </div>
                </div>
                <!-- Sin teléfono no se puede admitir: el campo está oculto, así que
                     el aviso tiene que vivir aquí o el operador no ve qué falta. -->
                <p v-if="form.use_client_data && !form.sender_phone" class="mt-2 text-xs text-[#fbbf24]">
                  El cliente no tiene teléfono. Agregalo arriba en Remitente, o desactivá el switch para escribirlo acá.
                </p>
                <p v-if="form.use_client_data" class="mt-2 text-xs text-[#64748b]">
                  La dirección de entrega se escribe igual: es en Nicaragua, no la de Amazon en Miami.
                </p>
              </div>

              <!-- Los campos se ocultan SOLO si hay cliente y el switch está
                   activo: sin cliente no hay nada que copiar y el operador
                   tiene que poder escribirlos. -->
              <div v-if="!canReuseClient || !form.use_client_data" class="grid gap-3 sm:grid-cols-2">
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
              <div class="flex items-center justify-between gap-3">
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 text-sm text-[#00A8FF] hover:underline transition-colors"
                  :disabled="creating"
                  @click="openGeoModal"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  {{ hasGeoPoint ? 'Cambiar ubicación en mapa' : 'Ubicar en mapa' }}
                </button>
                <span v-if="hasGeoPoint" class="text-xs text-[#64748b]">
                  {{ Number(form.latitude).toFixed(4) }}, {{ Number(form.longitude).toFixed(4) }}
                </span>
              </div>
              <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="max-w-sm" style="overflow: visible">
                  <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#64748b]">Departamento</label>
                  <AppDropdown
                    :model-value="form.recipient_department"
                    @update:model-value="(v: string | number | null) => form.recipient_department = String(v ?? '')"
                    :options="zones.map(z => ({ value: z.name, label: z.name }))"
                    placeholder="Seleccionar departamento..."
                    :disabled="creating"
                  />
                </div>
                <div class="max-w-sm" style="overflow: visible">
                  <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#64748b]">Ciudad</label>
                  <AppDropdown
                    :model-value="form.recipient_city"
                    @update:model-value="(v: string | number | null) => form.recipient_city = String(v ?? '')"
                    :options="subzones.map(s => ({ value: s.name, label: s.name }))"
                    :placeholder="!departmentZone
                      ? 'Primero elegí el departamento'
                      : (subzonesLoading ? 'Cargando...' : (subzones.length === 0 ? 'Sin ciudades' : 'Seleccionar ciudad...'))"
                    :disabled="creating || !departmentZone || subzonesLoading"
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
              <div v-if="form.delivery_option === 'door'" class="max-w-md" style="overflow: visible">
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#64748b]">Zona de entrega</label>
                <AppDropdown
                  :model-value="form.zone_id"
                  @update:model-value="(v: string | number | null) => form.zone_id = v as number | null"
                  :options="zones.map(z => ({ value: z.id, label: z.name }))"
                  placeholder="Seleccionar zona..."
                  :disabled="creating"
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

          <!-- Almacenaje -->
          <div class="admin-card p-5 space-y-4">
            <p class="admin-form-section mb-1">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V8l9-5 9 5v13M9 21v-8h6v8M3 21h18"/></svg>
              Almacenaje
            </p>
            <div>
              <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#64748b]">Clasificación <span class="text-[#DC2626]">*</span></label>
              <AppDropdown
                :model-value="form.storage_category_id"
                @update:model-value="(v: string | number | null) => form.storage_category_id = v as number | null"
                :options="storageCategories.map(c => ({ value: c.id, label: c.name + (c.description ? ' — ' + c.description : '') }))"
                :placeholder="storageCategories.length === 0 ? 'Sin clasificaciones (creá una en Almacenaje)' : 'Seleccionar clasificación...'"
                :disabled="creating"
              />
            </div>
            <div>
              <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-[#64748b]">
                Ubicación · {{ form.origin === 'local' ? 'Bodega Nicaragua' : 'Bodega Miami' }}
              </label>
              <AppDropdown
                :model-value="form.storage_location_id"
                @update:model-value="(v: string | number | null) => form.storage_location_id = v as number | null"
                :options="storageLocationOptions"
                :placeholder="storageLoading ? 'Buscando ubicación...' : 'Sin ubicación'"
                :disabled="creating || storageLoading || storageLocations.length === 0"
              />
              <p class="mt-1 text-xs text-[#64748b]">Se sugiere automáticamente según la clasificación; podés cambiarla.</p>
            </div>
          </div>

          <!-- Servicio de envío + Opción de entrega -->
          <div class="admin-card p-5 space-y-4">
            <div>
              <p class="admin-form-section mb-3">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                Servicio de envío</p>
              <div v-if="currentShippingService" class="rounded-lg border border-[#1e293b] bg-[#1e293b]/30 px-4 py-3">
                <p class="text-sm font-medium text-[#69dca4]">
                  {{ currentShippingService.description || currentShippingService.name }}
                </p>
                <p class="text-xs text-[#64748b] mt-0.5">Definido por la regla de precio seleccionada</p>
              </div>
              <div v-else class="rounded-lg border border-[#1e293b] bg-[#1e293b]/20 px-4 py-3">
                <p class="text-sm text-[#64748b]">Seleccioná una regla de precio para definir el servicio</p>
              </div>
            </div>
            <div>
              <p class="admin-form-section mb-3">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Opción de entrega</p>
              <div v-if="filteredDeliveryOptions.length > 0" class="flex flex-wrap gap-2">
                <button
                  v-for="dopt in filteredDeliveryOptions"
                  :key="dopt.id"
                  :class="[
                    'rounded-lg border px-4 py-2 text-sm font-medium transition-all',
                    form.delivery_option === dopt.name
                      ? 'border-[#00A8FF] bg-[#00A8FF]/10 text-[#00A8FF]'
                      : 'border-[#1e293b] text-[#94a3b8] hover:border-[#404944] hover:text-[#dae2fd]'
                  ]"
                  @click="form.delivery_option = dopt.name; form.delivery_option_id = dopt.id"
                >
                  {{ dopt.description || dopt.name }}
                </button>
              </div>
              <div v-else class="rounded-lg border border-[#1e293b] bg-[#1e293b]/20 px-4 py-3">
                <p class="text-sm text-[#64748b]">Seleccioná una regla de precio para ver opciones de entrega</p>
              </div>
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
          <p><span class="font-medium text-[#94a3b8]">Servicio + Entrega:</span> {{ deliveryTypeLabel }}</p>
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

        <div v-if="createdInvoice" class="mt-5 rounded-xl border border-[#1e293b] bg-[#0f172a] px-4 py-3">
          <div class="flex items-center justify-between gap-3">
            <div class="text-left">
              <p class="text-xs text-[#64748b]">Factura generada</p>
              <p class="font-mono text-sm font-medium text-[#dae2fd]">{{ createdInvoice.invoice_number }}</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-[#64748b]">Total</p>
              <p class="text-sm font-semibold text-[#f59e0b]">${{ Number(createdInvoice.total_amount).toFixed(2) }}</p>
            </div>
          </div>
          <button
            class="admin-btn admin-btn-ghost admin-btn-sm mt-3 w-full"
            @click="router.push({ name: 'invoice-detail', params: { id: createdInvoice.id } })"
          >
            Ver factura
          </button>
        </div>

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

    <!-- Geo modal: ubicar punto de entrega -->
    <AppModal :visible="showGeoModal" title="Ubicar punto de entrega" size="lg" :close-on-backdrop="false" @close="closeGeoModal">
      <div class="space-y-3">
        <form class="flex gap-2" @submit.prevent="geoSearchSubmit">
          <input
            v-model="geoSearch"
            class="admin-input w-full"
            placeholder="Dirección, ciudad, referencia..."
            autocomplete="off"
            :disabled="geoSearching"
          />
          <button type="submit" class="admin-btn admin-btn-primary shrink-0" :disabled="geoSearching || !geoSearch.trim()">
            {{ geoSearching ? 'Buscando...' : 'Buscar' }}
          </button>
        </form>

        <div v-if="geoError" class="rounded-lg border border-[#7f1d1d] bg-[#7f1d1d]/20 px-3 py-2 text-sm text-[#fca5a5]">
          {{ geoError }}
        </div>

        <div v-if="geoResults.length > 0" class="max-h-40 overflow-y-auto rounded-lg border border-[#1e293b] divide-y divide-[#1e293b]">
          <button
            v-for="(r, idx) in geoResults"
            :key="idx"
            type="button"
            class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm text-[#dae2fd] transition-colors hover:bg-white/[0.04]"
            @click="pickGeoResult(r)"
          >
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-[#00A8FF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>{{ r.display_name }}</span>
          </button>
        </div>

        <div ref="geoMapContainer" class="h-72 w-full rounded-lg border border-[#1e293b] z-0" />

        <p class="text-xs text-[#64748b]">Hacé clic en el mapa para ajustar el punto exacto.</p>
      </div>
      <template #footer>
        <div class="flex items-center justify-between gap-3">
          <span v-if="geoPoint" class="text-xs text-[#94a3b8] font-mono">
            {{ geoPoint.lat.toFixed(6) }}, {{ geoPoint.lng.toFixed(6) }}
          </span>
          <span v-else />
          <div class="flex gap-2">
            <button class="admin-btn admin-btn-ghost" @click="closeGeoModal">Cancelar</button>
            <button class="admin-btn admin-btn-primary" @click="confirmGeoPoint">Guardar punto</button>
          </div>
        </div>
      </template>
    </AppModal>
  </div>
</template>
