import api from './api'
import type { Invoice, InvoiceStatus, InvoiceSummary } from '../types'
import type { Pagination } from './pagination'

export type { Invoice, InvoiceStatus, InvoiceSummary }

export interface InvoiceListFilters {
  page?: number
  limit?: number
  status?: InvoiceStatus
  client_id?: number
  from?: string
  to?: string
}

export interface InvoiceListResult {
  invoices: Invoice[]
  summary: InvoiceSummary
  /** Tasa vigente de billing_config (C$ por USD) */
  exchange_rate_usd: number
  pagination: Pagination
}

export interface InvoiceDetailResult {
  invoice: Invoice
  /** Tasa vigente de billing_config (C$ por USD), la que aplicara pay() */
  exchange_rate_usd: number
}

export const invoiceService = {
  list(params?: InvoiceListFilters) {
    const q = new URLSearchParams(
      Object.entries(params ?? {})
        .filter(([, v]) => v !== undefined && v !== null && v !== '')
        .map(([k, v]) => [k, String(v)])
    ).toString()
    return api.get<{ success: boolean; data: InvoiceListResult }>(`/invoice/list.php?${q}`)
  },

  get(id: number) {
    return api.get<{ success: boolean; data: InvoiceDetailResult }>(`/invoice/get.php?id=${id}`)
  },

  pay(data: {
    id: number
    payment_method_id: number
    paid_currency: 'USD' | 'CORD'
    payment_reference?: string
    notes?: string
  }) {
    return api.post('/invoice/pay.php', data)
  },

  cancel(id: number) {
    return api.put('/invoice/cancel.php', { id })
  },

  /**
   * El backend emite en CORD por defecto: en Nicaragua la factura debe
   * ir en cordonbas aunque el servicio este dolarizado.
   *
   * @param format `letter` (Carta) o `ticket` (bobina de 80 mm, termica).
   */
  printUrl(id: number, currency: 'USD' | 'CORD' = 'CORD', format: 'letter' | 'ticket' = 'letter') {
    return `${api.defaults.baseURL}/invoice/print.php?id=${id}&currency=${currency}&format=${format}`
  },
}