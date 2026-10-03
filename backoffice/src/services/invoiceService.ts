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
  pagination: Pagination
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
    return api.get<{ success: boolean; data: { invoice: Invoice } }>(`/invoice/get.php?id=${id}`)
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

  printUrl(id: number) {
    return `${api.defaults.baseURL}/invoice/print.php?id=${id}`
  },
}