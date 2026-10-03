import api from './api'
import type { BillingConfig, InvoiceSequenceInfo } from '../types'

export type { BillingConfig }

export const billingConfigService = {
  get() {
    return api.get<{ success: boolean; data: { config: BillingConfig; invoice_sequence?: InvoiceSequenceInfo } }>('/billing_config/get.php')
  },

  update(data: Partial<BillingConfig>) {
    return api.put<{ success: boolean; data: { config: BillingConfig; invoice_sequence?: InvoiceSequenceInfo } }>('/billing_config/update.php', data)
  },
}