import api from './api'
import type { BillingConfig } from '../types'

export type { BillingConfig }

export const billingConfigService = {
  get() {
    return api.get<{ success: boolean; data: { config: BillingConfig } }>('/billing_config/get.php')
  },

  update(data: Partial<BillingConfig>) {
    return api.put('/billing_config/update.php', data)
  },
}