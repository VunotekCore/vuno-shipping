<script setup lang="ts">
import { onMounted, reactive, ref, computed, watch } from 'vue'
import { pricingRuleService, type PricingRule } from '../services/pricingRuleService'
import { zoneService, type Zone } from '../services/zoneService'
import { useCrudView } from '../composables/useCrudView'
import AppTable from '../components/ui/AppTable.vue'
import type { TableColumn } from '../components/ui/AppTable.vue'
import AppModal from '../components/ui/AppModal.vue'
import AppFormField from '../components/ui/AppFormField.vue'
import AppSwitch from '../components/ui/AppSwitch.vue'
import AppConfirmDialog from '../components/ui/AppConfirmDialog.vue'
import AppPagination from '../components/ui/AppPagination.vue'

interface DeliveryType { id: number; name: string; description: string | null }
interface PricingRuleType { id: number; name: string; description: string | null }

const deliveryTypes = ref<DeliveryType[]>([])
const ruleTypes = ref<PricingRuleType[]>([])
const zones = ref<Zone[]>([])

const {
  items: rules, loading, showModal, showDelete,
  editing, deleting, saving, form,
  page, limit, total, pages,
  load, openCreate, openEdit, openDelete, save, confirmDelete, goToPage, setLimit,
} = useCrudView<PricingRule>({
  service: pricingRuleService,
  dataKey: 'pricing_rules',
  entityName: 'Regla de pricing',
  getDefaultForm: () => ({
    name: '', base_price: 0, price_per_kg: null, price_per_volume: null,
    price_per_km: null, price_per_zone: null,
    percentage_of_value: 0, min_weight: null, max_weight: null,
    type_id: null, delivery_type_id: null, min_price: null, volumetric_divisor: 166, is_active: true,
    zone_ids: [] as number[],
  }),
  populateForm: (r) => ({
    name: r.name, base_price: r.base_price, price_per_kg: r.price_per_kg,
    price_per_volume: r.price_per_volume, price_per_km: r.price_per_km, price_per_zone: r.price_per_zone,
    percentage_of_value: r.percentage_of_value,
    min_weight: r.min_weight, max_weight: r.max_weight,
    type_id: r.type_id, delivery_type_id: r.delivery_type_id,
    min_price: r.min_price, volumetric_divisor: r.volumetric_divisor, is_active: r.is_active,
    zone_ids: r.zones?.map(z => z.id) ?? [],
  }),
  validateForm: (f) => {
    if (!f.name) return 'El nombre es requerido'
    return null
  },
  getEntityId: (r) => r.id,
})

const visibleTypeName = ref<string | null>(null)

watch(showModal, () => {
  if (!showModal.value) return
  const tid = form.value.type_id
  if (tid == null) { visibleTypeName.value = null; return }
  const t = ruleTypes.value.find(t => t.id === tid)
  visibleTypeName.value = t?.name ?? null
})

function onTypeChange(e: Event) {
  const raw = (e.target as HTMLSelectElement).value
  if (raw === '') {
    form.value.type_id = null
    visibleTypeName.value = null
    return
  }
  const id = Number(raw)
  form.value.type_id = id
  const t = ruleTypes.value.find(t => t.id === id)
  visibleTypeName.value = t?.name ?? null
}

const fieldErrors = reactive<Record<string, string>>({
  name: '', base_price: '', percentage_of_value: '',
  price_per_kg: '', price_per_volume: '', price_per_km: '', price_per_zone: '',
  min_weight: '', max_weight: '', min_price: '', volumetric_divisor: '',
})

function clearError(key: string) {
  fieldErrors[key] = ''
}

function validateField(key: string) {
  const v = form.value[key]
  switch (key) {
    case 'name':
      if (!String(v ?? '').trim()) { fieldErrors.name = 'El nombre es requerido'; return }
      if (String(v).length > 255) { fieldErrors.name = 'Máximo 255 caracteres'; return }
      fieldErrors.name = ''
      break
    case 'base_price':
    case 'percentage_of_value':
    case 'min_price':
      if (v !== null && v !== '' && Number(v) < 0) { fieldErrors[key] = 'No puede ser negativo'; return }
      fieldErrors[key] = ''
      break
    case 'volumetric_divisor':
      if (v !== null && v !== '' && Number(v) < 1) { fieldErrors.volumetric_divisor = 'Debe ser mayor a 0'; return }
      fieldErrors.volumetric_divisor = ''
      break
    case 'price_per_kg':
    case 'price_per_volume':
    case 'price_per_km':
    case 'price_per_zone':
      if (v !== null && v !== '' && Number(v) < 0) { fieldErrors[key] = 'No puede ser negativo'; return }
      fieldErrors[key] = ''
      break
    case 'min_weight':
      if (v !== null && v !== '' && Number(v) < 0) { fieldErrors.min_weight = 'No puede ser negativo'; return }
      fieldErrors.min_weight = ''
      break
    case 'max_weight':
      if (v !== null && v !== '' && Number(v) < 0) { fieldErrors.max_weight = 'No puede ser negativo'; return }
      if (v !== null && v !== '' && form.value.min_weight !== null && form.value.min_weight !== '' && Number(v) < Number(form.value.min_weight)) {
        fieldErrors.max_weight = 'Debe ser mayor al mínimo'; return
      }
      fieldErrors.max_weight = ''
      break
  }
}

const hasErrors = computed(() => Object.values(fieldErrors).some(Boolean))

function fv(key: string): string | number | null {
  const v = form.value[key]
  if (v == null) return null
  if (typeof v === 'string' || typeof v === 'number') return v
  return null
}

function toggleZoneId(zoneId: number) {
  const ids: number[] = (form.value.zone_ids as number[]) ?? []
  if (ids.includes(zoneId)) {
    form.value.zone_ids = ids.filter(id => id !== zoneId)
  } else {
    form.value.zone_ids = [...ids, zoneId]
  }
}

function hasZoneId(zoneId: number): boolean {
  return ((form.value.zone_ids as number[]) ?? []).includes(zoneId)
}

function handleSave() {
  for (const key of Object.keys(fieldErrors)) validateField(key)
  if (hasErrors.value) return
  save()
}

const columns: TableColumn[] = [
  { key: 'name', label: 'Nombre', width: '180px' },
  { key: 'delivery_type_name', label: 'Tipo entrega', width: '120px' },
  { key: 'base_price', label: 'Precio base', width: '110px' },
  { key: 'price_per_kg', label: '$/lb', width: '90px' },
  { key: 'price_per_volume', label: '$/vol', width: '90px' },
  { key: 'percentage_of_value', label: '% Valor', width: '90px' },
  { key: 'min_price', label: 'Mínimo', width: '90px' },
  { key: 'is_active', label: 'Estado', width: '80px' },
  { key: 'actions', label: '', width: '100px', align: 'right' },
]

function formatCurrency(val: number | null | undefined): string {
  if (val === null || val === undefined) return '—'
  return '$' + Number(val).toFixed(2)
}

onMounted(async () => {
  load()
  const [dtRes, rtRes, zoneRes] = await Promise.all([
    pricingRuleService.listDeliveryTypes().catch(() => null),
    pricingRuleService.listPricingRuleTypes().catch(() => null),
    zoneService.listAll().catch(() => null),
  ])
  if (dtRes?.data?.data?.delivery_types) deliveryTypes.value = dtRes.data.data.delivery_types.map(t => ({ ...t, id: Number(t.id) }))
  if (rtRes?.data?.data?.pricing_rule_types) ruleTypes.value = rtRes.data.data.pricing_rule_types.map(t => ({ ...t, id: Number(t.id) }))
  if (zoneRes?.data?.data?.zones) zones.value = zoneRes.data.data.zones
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Reglas de pricing</h1>
        <p class="mt-1 text-sm text-[#64748b]">Gestionar reglas de pricing y tarifas</p>
      </div>
      <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        Crear regla
      </button>
    </div>

    <!-- Desktop: Table view (lg+) -->
    <div class="hidden lg:block">
      <div class="admin-card">
        <AppTable :columns="columns" :loading="loading" :is-empty="rules.length === 0" empty-title="Sin reglas de pricing" empty-desc="Creá la primera regla de pricing.">
          <tr v-for="r in rules" :key="r.id">
            <td class="font-medium text-[#dae2fd]">{{ r.name }}</td>
            <td class="text-[#94a3b8]">{{ r.delivery_type_name || '—' }}</td>
            <td class="text-[#94a3b8]">{{ formatCurrency(r.base_price) }}</td>
            <td class="text-[#94a3b8]">{{ formatCurrency(r.price_per_kg) }}</td>
            <td class="text-[#94a3b8]">{{ formatCurrency(r.price_per_volume) }}</td>
            <td class="text-[#94a3b8]">{{ formatCurrency(r.percentage_of_value) }}</td>
            <td class="text-[#94a3b8]">{{ formatCurrency(r.min_price) }}</td>
            <td>
              <span :class="r.is_active ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-warning'">
                {{ r.is_active ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(r)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(r)" title="Eliminar">
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
            </div>
          </div>
        </div>
      </template>

      <template v-else-if="rules.length > 0">
        <div v-for="r in rules" :key="r.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-[#dae2fd] truncate">{{ r.name }}</p>
            <span :class="r.is_active ? 'admin-badge admin-badge-success shrink-0' : 'admin-badge admin-badge-warning shrink-0'">
              {{ r.is_active ? 'Activa' : 'Inactiva' }}
            </span>
          </div>
          <p v-if="r.delivery_type_name" class="text-sm text-[#69dca4]">{{ r.delivery_type_name }}</p>
          <p class="text-sm text-[#94a3b8]">Base: {{ formatCurrency(r.base_price) }}</p>
          <p class="text-xs text-[#64748b]">
            {{ formatCurrency(r.price_per_kg) }}/lb ·
            {{ formatCurrency(r.price_per_volume) }}/vol ·
            {{ formatCurrency(r.percentage_of_value) }}% valor
            <template v-if="r.min_price"> · Mín: {{ formatCurrency(r.min_price) }}</template>
          </p>
          <div class="border-t border-[#1e293b] pt-3 flex items-center gap-2">
            <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openEdit(r)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Editar
            </button>
            <button class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" @click="openDelete(r)">
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
          <p class="admin-empty-state-title">Sin reglas de pricing</p>
          <p class="admin-empty-state-desc">Creá la primera regla de pricing.</p>
        </div>
      </div>

      <AppPagination :page="page" :pages="pages" :total="total" :limit="limit" @update:page="goToPage" @update:limit="setLimit" />
    </div>

    <!-- Create / Edit Modal -->
    <AppModal :visible="showModal" :title="editing ? 'Editar regla' : 'Crear regla'" @close="showModal = false" size="md">
      <form class="space-y-5" @submit.prevent="handleSave" novalidate>
        <!-- Sección: Identificación -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Identificación
          </div>
          <div class="mt-3 space-y-4">
            <AppFormField
              :model-value="fv('name')"
              @update:model-value="(v: string | number | null) => form.name = v"
              label="Nombre"
              icon="tag"
              placeholder="Nombre de la regla"
              :required="true"
              :disabled="saving"
              :maxlength="255"
              :error="fieldErrors.name"
              @blur="validateField('name')"
              @input="clearError('name')"
            />
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Tipo de cálculo</label>
                <select
                  :value="form.type_id ?? ''"
                  @change="onTypeChange"
                  class="admin-input"
                  :disabled="saving"
                >
                  <option value="">Opcional</option>
                  <option v-for="t in ruleTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Tipo de entrega</label>
                <select
                  :value="form.delivery_type_id ?? ''"
                  @change="form.delivery_type_id = ($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null"
                  class="admin-input"
                  :disabled="saving"
                >
                  <option value="">Opcional</option>
                  <option v-for="dt in deliveryTypes" :key="dt.id" :value="dt.id">{{ dt.name }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Tarifa base -->
        <div>
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            Tarifa base
          </div>
          <div class="mt-3 grid grid-cols-2 gap-4">
            <AppFormField
              :model-value="fv('base_price')"
              @update:model-value="(v: string | number | null) => form.base_price = v"
              label="Precio base"
              type="number"
              icon="dollar-sign"
              placeholder="0.00"
              :disabled="saving"
              :min="0"
              :step="0.01"
              :error="fieldErrors.base_price"
              @blur="validateField('base_price')"
              @input="clearError('base_price')"
            />
            <AppFormField
              :model-value="fv('min_price')"
              @update:model-value="(v: string | number | null) => form.min_price = v"
              label="Precio mínimo"
              type="number"
              icon="dollar-sign"
              placeholder="Opcional"
              :disabled="saving"
              :min="0"
              :step="0.01"
              helper="Piso de cobro"
              :error="fieldErrors.min_price"
              @blur="validateField('min_price')"
              @input="clearError('min_price')"
            />
          </div>
        </div>
        <div v-if="visibleTypeName === 'percentage value'">

          <div class="admin-form-divider" />
          <!-- Sección: % del valor -->
          <div>
            <div class="admin-form-section">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="20" y2="4"/><circle cx="6" cy="6" r="2.5" fill="none"/><circle cx="18" cy="18" r="2.5" fill="none"/></svg>
              Del valor declarado
            </div>
            <div class="mt-3">
              <AppFormField
                :model-value="fv('percentage_of_value')"
                @update:model-value="(v: string | number | null) => form.percentage_of_value = v"
                label="Del valor"
                type="number"
                icon="percent"
                placeholder="0.00"
                :disabled="saving"
                :min="0"
                :step="0.01"
                helper="Porcentaje aplicado sobre el valor declarado del paquete"
                :error="fieldErrors.percentage_of_value"
                @blur="validateField('percentage_of_value')"
                @input="clearError('percentage_of_value')"
              />
            </div>
          </div>
        </div>

        <!-- Sección: Cálculo volumétrico -->
        <div v-if="visibleTypeName === null || visibleTypeName === 'weight' || visibleTypeName === 'volume'">
          <div class="admin-form-divider" />
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            Cálculo volumétrico
          </div>
          <div class="mt-3">
            <AppFormField
              :model-value="fv('volumetric_divisor')"
              @update:model-value="(v: string | number | null) => form.volumetric_divisor = v"
              label="Divisor volumétrico"
              type="number"
              icon="layers"
              placeholder="166"
              :disabled="saving"
              :min="1"
              helper="166 = aéreo (in³/lb), 5000 = marítimo (cm³/kg). Peso volumétrico = (largo × ancho × alto) ÷ divisor"
              :error="fieldErrors.volumetric_divisor"
              @blur="validateField('volumetric_divisor')"
              @input="clearError('volumetric_divisor')"
            />
          </div>
        </div>

        <div class="admin-form-divider" />

        <!-- Sección: Costo por unidad -->
        <template v-if="visibleTypeName !== null && visibleTypeName !== 'percentage value' && visibleTypeName !== 'fixed'">
          <div class="admin-form-divider" />
          <div>
            <div class="admin-form-section">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              Costo por unidad
            </div>
            <div v-if="visibleTypeName === 'weight'" class="mt-3">
              <AppFormField
                :model-value="fv('price_per_kg')"
                @update:model-value="(v: string | number | null) => form.price_per_kg = v"
                label="$/lb"
                type="number"
                placeholder="0.00"
                :disabled="saving"
                :min="0"
                :step="0.01"
                helper="Costo por libra"
                :error="fieldErrors.price_per_kg"
                @blur="validateField('price_per_kg')"
                @input="clearError('price_per_kg')"
              />
            </div>
            <div v-if="visibleTypeName === 'volume'" class="mt-3">
              <AppFormField
                :model-value="fv('price_per_volume')"
                @update:model-value="(v: string | number | null) => form.price_per_volume = v"
                label="$/vol"
                type="number"
                placeholder="0.00"
                :disabled="saving"
                :min="0"
                :step="0.01"
                helper="Costo por volumen"
                :error="fieldErrors.price_per_volume"
                @blur="validateField('price_per_volume')"
                @input="clearError('price_per_volume')"
              />
            </div>
            <div v-if="visibleTypeName === 'distance'" class="mt-3">
              <AppFormField
                :model-value="fv('price_per_km')"
                @update:model-value="(v: string | number | null) => form.price_per_km = v"
                label="$/km"
                type="number"
                placeholder="0.00"
                :disabled="saving"
                :min="0"
                :step="0.01"
                helper="Costo por kilómetro"
                :error="fieldErrors.price_per_km"
                @blur="validateField('price_per_km')"
                @input="clearError('price_per_km')"
              />
            </div>
            <div v-if="visibleTypeName === 'zone'" class="mt-3">
              <AppFormField
                :model-value="fv('price_per_zone')"
                @update:model-value="(v: string | number | null) => form.price_per_zone = v"
                label="$/zona"
                type="number"
                placeholder="0.00"
                :disabled="saving"
                :min="0"
                :step="0.01"
                helper="Cargo fijo por zona de entrega"
                :error="fieldErrors.price_per_zone"
                @blur="validateField('price_per_zone')"
                @input="clearError('price_per_zone')"
              />
            </div>
          </div>
        </template>

        <!-- Sin tipo seleccionado: mostrar todos los costos por unidad -->
        <template v-if="visibleTypeName === null">
          <div class="admin-form-divider" />
          <div>
            <div class="admin-form-section">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              Costo por unidad
            </div>
            <div class="mt-3 grid grid-cols-2 gap-4">
              <AppFormField
                :model-value="fv('price_per_kg')"
                @update:model-value="(v: string | number | null) => form.price_per_kg = v"
                label="$/lb"
                type="number"
                placeholder="0.00"
                :disabled="saving"
                :min="0"
                :step="0.01"
                helper="Costo por libra"
                :error="fieldErrors.price_per_kg"
                @blur="validateField('price_per_kg')"
                @input="clearError('price_per_kg')"
              />
              <AppFormField
                :model-value="fv('price_per_volume')"
                @update:model-value="(v: string | number | null) => form.price_per_volume = v"
                label="$/vol"
                type="number"
                placeholder="0.00"
                :disabled="saving"
                :min="0"
                :step="0.01"
                helper="Costo por volumen"
                :error="fieldErrors.price_per_volume"
                @blur="validateField('price_per_volume')"
                @input="clearError('price_per_volume')"
              />
            </div>
          </div>
        </template>

        <div class="admin-form-divider" />

        <!-- Sección: Límites de peso -->
        <div v-if="visibleTypeName === null || visibleTypeName === 'weight'">
          <div class="admin-form-divider" />
          <div>
            <div class="admin-form-section">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4zM3 6h18M16 10a4 4 0 01-8 0"/></svg>
              Límites de peso
            </div>
            <div class="mt-3 grid grid-cols-2 gap-4">
              <AppFormField
                :model-value="fv('min_weight')"
                @update:model-value="(v: string | number | null) => form.min_weight = v"
                label="Peso mínimo (lb)"
                type="number"
                placeholder="Opcional"
                :disabled="saving"
                :min="0"
                :step="0.01"
                helper="Sin límite inferior si vacío"
                :error="fieldErrors.min_weight"
                @blur="validateField('min_weight')"
                @input="clearError('min_weight')"
              />
              <AppFormField
                :model-value="fv('max_weight')"
                @update:model-value="(v: string | number | null) => form.max_weight = v"
                label="Peso máximo (lb)"
                type="number"
                placeholder="Opcional"
                :disabled="saving"
                :min="0"
                :step="0.01"
                helper="Sin límite superior si vacío"
                :error="fieldErrors.max_weight"
                @blur="validateField('max_weight')"
                @input="clearError('max_weight')"
              />
            </div>
          </div>
        </div>

        <!-- Sección: Zonas (solo para zone / fixed) -->
        <div v-if="visibleTypeName === 'zone' || visibleTypeName === 'fixed'">
          <div class="admin-form-divider" />
          <div>
            <div class="admin-form-section">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              Zonas
            </div>
            <div class="mt-3 space-y-3">
              <p class="text-xs text-[#64748b]">Seleccioná las zonas donde aplica esta regla</p>
              <div v-if="zones.length === 0" class="text-sm text-[#64748b] italic">No hay zonas disponibles</div>
              <div v-else class="grid grid-cols-2 gap-x-4 gap-y-2 max-h-48 overflow-y-auto">
                <label
                  v-for="z in zones.filter(z => z.is_active)"
                  :key="z.id"
                  class="flex items-center gap-2 cursor-pointer text-sm text-[#dae2fd] hover:text-white transition-colors"
                >
                  <input
                    type="checkbox"
                    :value="z.id"
                    :checked="hasZoneId(z.id)"
                    @change="toggleZoneId(z.id)"
                    class="h-4 w-4 rounded border-[#475569] bg-[#0a1022] text-[#69dca4] focus:ring-[#69dca4]"
                  />
                  {{ z.name }}
                  <span v-if="z.surcharge > 0" class="text-[#64748b]">(+${{ Number(z.surcharge).toFixed(2) }})</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Sección: Estado -->
        <div>
          <div class="admin-form-divider" />
          <div class="admin-form-section">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Estado
          </div>
          <div class="mt-3 flex items-center justify-between rounded-lg border border-[#1e293b] bg-[#0a1022]/50 px-4 py-3">
            <div>
              <p class="text-sm font-medium text-[#dae2fd]">Activa</p>
              <p class="text-xs text-[#64748b]">Las reglas inactivas no se aplican al calcular precios</p>
            </div>
            <AppSwitch :model-value="!!form.is_active" @update:model-value="(v: boolean) => { form.is_active = v }" :disabled="saving" />
          </div>
        </div>
      </form>

      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showModal = false" :disabled="saving">Cancelar</button>
          <button
            class="admin-btn admin-btn-primary"
            :disabled="saving || !form.name || hasErrors"
            @click="handleSave"
          >
            {{ saving ? 'Guardando...' : (editing ? 'Actualizar' : 'Crear') }}
          </button>
        </div>
      </template>
    </AppModal>

    <!-- Delete Confirmation -->
    <AppConfirmDialog
      :visible="showDelete"
      title="Eliminar regla de pricing"
      :message="`¿Seguro que querés eliminar la regla ${deleting?.name}? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      @confirm="confirmDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
