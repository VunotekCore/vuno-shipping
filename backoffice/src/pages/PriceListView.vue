<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { pricingRuleService } from '../services/pricingRuleService'
import { zoneService, type Zone } from '../services/zoneService'
import { useAuthStore } from '../stores/auth'
import PageHeader from '../components/ui/PageHeader.vue'

const auth = useAuthStore()

const canManagePricing = computed(() => {
  return (
    auth.hasPermission('pricing_rule', 'create') ||
    auth.hasPermission('pricing_rule', 'edit') ||
    auth.hasPermission('pricing_rule', 'delete')
  )
})

interface PricingRule {
  id: number
  name: string
  type_id: number | null
  type_name: string | null
  delivery_type_id: number | null
  delivery_type_name: string | null
  base_price: number | null
  price_per_kg: number | null
  price_per_volume: number | null
  percentage_of_value: number | null
  min_price: number | null
  volumetric_divisor: number | null
  is_active: number | boolean
  zones?: Zone[]
}

interface DeliveryType {
  id: number
  name: string
  description: string | null
  delivery_frequency: string | null
  category?: string
}

interface PricingRuleType {
  id: number
  name: string
  description: string | null
}

interface DeliveryType {
  id: number
  name: string
  description: string | null
}

const loading = ref(true)
const rules = ref<PricingRule[]>([])
const ruleTypes = ref<PricingRuleType[]>([])
const deliveryTypes = ref<DeliveryType[]>([])
const zones = ref<Zone[]>([])

function getDeliveryFrequency(rule: PricingRule): string | null {
  if (!rule.delivery_type_name) return null
  const dt = deliveryTypes.value.find(d => d.name === rule.delivery_type_name)
  if (dt?.delivery_frequency) return dt.delivery_frequency
  return null
}

function formatCurrency(val: number | null | undefined): string {
  if (val === null || val === undefined || val === '') return '—'
  const num = Number(val)
  if (Number.isNaN(num)) return '—'
  return '$' + num.toFixed(2)
}

function isActive(val: number | boolean): boolean {
  if (typeof val === 'boolean') return val
  return Number(val) === 1
}

const groupedRules = computed(() => {
  const groups = new Map<string, { typeName: string; typeId: number | null; rules: PricingRule[] }>()
  
  for (const rule of rules.value) {
    const key = rule.type_id ? String(rule.type_id) : 'sin-tipo'
    if (!groups.has(key)) {
      groups.set(key, {
        typeName: rule.type_name || 'Sin tipo de regla',
        typeId: rule.type_id,
        rules: []
      })
    }
    groups.get(key)!.rules.push(rule)
  }
  
  // Sort groups by name
  const sorted = Array.from(groups.values()).sort((a, b) => 
    a.typeName.localeCompare(b.typeName)
  )
  
  // Sort rules within each group by name
  for (const group of sorted) {
    group.rules.sort((a, b) => a.name.localeCompare(b.name))
  }
  
  return sorted
})

function getZoneNames(rule: PricingRule): string {
  if (rule.zones && rule.zones.length > 0) {
    return rule.zones.map(z => z.name).join(', ')
  }
  return 'Todas las zonas'
}

function getWeightRange(rule: PricingRule): string {
  const min = rule.min_weight !== null && rule.min_weight !== undefined ? Number(rule.min_weight) : null
  const max = rule.max_weight !== null && rule.max_weight !== undefined ? Number(rule.max_weight) : null
  
  if (min !== null && max !== null) {
    return `${min} - ${max} lb`
  }
  if (min !== null && max === null) {
    return `>= ${min} lb`
  }
  if (min === null && max !== null) {
    return `<= ${max} lb`
  }
  return 'Sin rango'
}

onMounted(async () => {
  loading.value = true
  try {
    const [rulesRes, typesRes, deliveryRes, zonesRes] = await Promise.all([
      pricingRuleService.listAll().catch(() => null),
      pricingRuleService.listPricingRuleTypes().catch(() => null),
      pricingRuleService.listDeliveryTypes().catch(() => null),
      zoneService.listAll().catch(() => null),
    ])
    
    if (rulesRes?.data?.data?.pricing_rules) {
      rules.value = rulesRes.data.data.pricing_rules
    }
    if (typesRes?.data?.data?.pricing_rule_types) {
      ruleTypes.value = typesRes.data.data.pricing_rule_types.map(t => ({ ...t, id: Number(t.id) }))
    }
    if (deliveryRes?.data?.data?.delivery_types) {
      deliveryTypes.value = deliveryRes.data.data.delivery_types.map(t => ({ ...t, id: Number(t.id) }))
    }
    if (zonesRes?.data?.data?.zones) {
      zones.value = zonesRes.data.data.zones
    }
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Lista de precios" subtitle="Tarifario vigente para consulta comercial">
      <template #actions>
        <router-link v-if="canManagePricing" to="/pricing" class="admin-btn admin-btn-ghost sm:w-auto w-full">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Gestionar reglas
        </router-link>
      </template>
    </PageHeader>

    <!-- Loading -->
    <template v-if="loading">
      <div v-for="i in 2" :key="i" class="admin-card">
        <div class="px-6 py-4 border-b border-line">
          <div class="h-5 w-40 animate-pulse rounded bg-surface-raised" />
        </div>
        <div class="p-6 space-y-3">
          <div v-for="j in 3" :key="j" class="h-12 w-full animate-pulse rounded bg-surface-raised" />
        </div>
      </div>
    </template>

    <!-- Empty state -->
    <div v-else-if="groupedRules.length === 0" class="admin-card">
      <div class="admin-empty-state py-12">
        <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
          <path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
        </svg>
        <p class="admin-empty-state-title">Sin tarifas activas</p>
        <p class="admin-empty-state-desc">No hay reglas de pricing activas para mostrar.</p>
        <router-link v-if="canManagePricing" to="/pricing" class="admin-btn admin-btn-primary mt-4">
          Ir a Reglas de pricing
        </router-link>
      </div>
    </div>

    <!-- Desktop: Table view grouped (lg+) -->
    <template v-else>
      <div v-for="group in groupedRules" :key="group.typeId || group.typeName" class="hidden lg:block">
        <div class="admin-card overflow-hidden">
          <div class="px-6 py-4 border-b border-line bg-surface-subtle">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-base font-semibold text-content">{{ group.typeName }}</h2>
                <p class="text-sm text-muted mt-0.5">{{ group.rules.length }} {{ group.rules.length === 1 ? 'regla' : 'reglas' }}</p>
              </div>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Regla</th>
                  <th>Tipo entrega</th>
                  <th>Frecuencia de entrega</th>
                  <th>Zonas</th>
                  <th>Rango peso</th>
                  <th class="text-right">Precio base</th>
                  <th class="text-right">$/lb</th>
                  <th class="text-right">$/Vol</th>
                  <th class="text-right">% Valor</th>
                  <th class="text-right">Mínimo</th>
                  <th class="text-right">Divisor</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
            <tr v-for="rule in group.rules" :key="rule.id">
              <td class="font-medium text-content">{{ rule.name }}</td>
              <td class="text-muted">{{ rule.delivery_type_name || '—' }}</td>
              <td class="text-muted">{{ getDeliveryFrequency(rule) || '—' }}</td>
              <td class="text-muted max-w-xs truncate" :title="getZoneNames(rule)">{{ getZoneNames(rule) }}</td>
              <td class="text-muted">{{ getWeightRange(rule) }}</td>
              <td class="text-right text-muted">{{ formatCurrency(rule.base_price) }}</td>
              <td class="text-right text-muted">{{ formatCurrency(rule.price_per_kg) }}</td>
              <td class="text-right text-muted">{{ formatCurrency(rule.price_per_volume) }}</td>
              <td class="text-right text-muted">{{ rule.percentage_of_value !== null && rule.percentage_of_value !== undefined ? rule.percentage_of_value + '%' : '—' }}</td>
              <td class="text-right text-muted">{{ formatCurrency(rule.min_price) }}</td>
              <td class="text-right text-muted">{{ rule.volumetric_divisor || '166' }}</td>
              <td>
                <span :class="isActive(rule.is_active) ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-warning'">
                  {{ isActive(rule.is_active) ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
            </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Mobile/Tablet: Card view grouped (< lg) -->
      <div v-for="group in groupedRules" :key="group.typeId || group.typeName" class="lg:hidden space-y-3">
        <div class="px-1">
          <h2 class="text-base font-semibold text-content">{{ group.typeName }}</h2>
          <p class="text-sm text-muted">{{ group.rules.length }} {{ group.rules.length === 1 ? 'regla' : 'reglas' }}</p>
        </div>
        <div v-for="rule in group.rules" :key="rule.id" class="admin-card p-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <p class="font-medium text-content">{{ rule.name }}</p>
            <span :class="isActive(rule.is_active) ? 'admin-badge admin-badge-success shrink-0' : 'admin-badge admin-badge-warning shrink-0'">
              {{ isActive(rule.is_active) ? 'Activa' : 'Inactiva' }}
            </span>
          </div>
          <div class="flex flex-wrap items-center gap-2 text-sm">
            <span v-if="rule.delivery_type_name" class="admin-badge admin-badge-info">{{ rule.delivery_type_name }}</span>
            <span class="text-muted">{{ getWeightRange(rule) }}</span>
          </div>
          <div v-if="getDeliveryFrequency(rule)" class="text-sm text-muted">
            <span class="font-medium text-content">Frecuencia:</span> {{ getDeliveryFrequency(rule) }}
          </div>
          <div class="text-sm text-muted">
            <span class="font-medium text-content">Zonas:</span> {{ getZoneNames(rule) }}
          </div>
          <div class="grid grid-cols-2 gap-2 text-sm">
            <div>
              <p class="text-subtle text-xs uppercase tracking-wide">Precio base</p>
              <p class="text-content">{{ formatCurrency(rule.base_price) }}</p>
            </div>
            <div>
              <p class="text-subtle text-xs uppercase tracking-wide">$/lb</p>
              <p class="text-content">{{ formatCurrency(rule.price_per_kg) }}</p>
            </div>
            <div>
              <p class="text-subtle text-xs uppercase tracking-wide">$/Volumétrico</p>
              <p class="text-content">{{ formatCurrency(rule.price_per_volume) }}</p>
            </div>
            <div>
              <p class="text-subtle text-xs uppercase tracking-wide">% Valor</p>
              <p class="text-content">{{ rule.percentage_of_value !== null && rule.percentage_of_value !== undefined ? rule.percentage_of_value + '%' : '—' }}</p>
            </div>
            <div>
              <p class="text-subtle text-xs uppercase tracking-wide">Mínimo</p>
              <p class="text-content">{{ formatCurrency(rule.min_price) }}</p>
            </div>
            <div>
              <p class="text-subtle text-xs uppercase tracking-wide">Divisor vol.</p>
              <p class="text-content">{{ rule.volumetric_divisor || '166' }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
