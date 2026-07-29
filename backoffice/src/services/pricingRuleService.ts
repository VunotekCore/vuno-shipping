import api from './api'
import type { PricingRule, ShippingService, DeliveryOption } from '../types'
import { buildPageParams } from './pagination'

export type { PricingRule, ShippingService, DeliveryOption }

interface DeliveryType {
  id: number
  name: string
  description: string | null
  category?: string
}

interface PricingRuleType {
  id: number
  name: string
  description: string | null
}

export const pricingRuleService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { pricing_rules: PricingRule[]; pagination: import('./pagination').Pagination } }>(
      `/pricing_rule/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { pricing_rules: PricingRule[] } }>('/pricing_rule/listAll.php')
  },

  listDeliveryTypes() {
    return api.get<{ success: boolean; data: { delivery_types: DeliveryType[] } }>('/pricing_rule/listDeliveryTypes.php')
  },

  listPricingRuleTypes() {
    return api.get<{ success: boolean; data: { pricing_rule_types: PricingRuleType[] } }>('/pricing_rule/listPricingRuleTypes.php')
  },

  create(data: { name: string; type_id?: number; delivery_type_id?: number; base_price?: number; min_weight?: number; max_weight?: number; price_per_kg?: number; price_per_volume?: number; price_per_km?: number; price_per_zone?: number; percentage_of_value?: number; min_price?: number; zone_ids?: number[] }) {
    return api.post('/pricing_rule/create.php', data)
  },

  update(data: { id: number; name?: string; type_id?: number; delivery_type_id?: number; base_price?: number; min_weight?: number; max_weight?: number; price_per_kg?: number; price_per_volume?: number; price_per_km?: number; price_per_zone?: number; percentage_of_value?: number; min_price?: number; is_active?: boolean; zone_ids?: number[] }) {
    return api.put('/pricing_rule/update.php', data)
  },

  delete(id: number) {
    return api.delete('/pricing_rule/delete.php', { data: { id } })
  },

  calculate(data: { packages: Array<{ weight: number; length: number; width: number; height: number; quantity?: number }>; zone_id?: number; delivery_type?: string; shipping_service_id?: number; delivery_option?: string; declared_value?: number; type?: string }) {
    return api.post('/pricing_rule/calculate.php', data)
  },

  resolveFromRule(pricingRuleId: number) {
    return api.get<{ success: boolean; data: { shipping_service: ShippingService | null; delivery_options: DeliveryOption[] } }>(`/pricing_rule/resolve.php?pricing_rule_id=${pricingRuleId}`)
  },
}
