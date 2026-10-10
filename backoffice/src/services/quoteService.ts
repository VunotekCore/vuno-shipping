import api from './api'
import type {
  Quote,
  QuoteItem,
  QuotePackage,
  QuoteSummary,
  QuoteListResult,
  QuoteDetailResult,
  QuoteStatsResult,
  QuoteStatus,
} from '../types'

export type { Quote, QuoteItem, QuotePackage, QuoteSummary, QuoteListResult, QuoteDetailResult, QuoteStatsResult, QuoteStatus }

export interface QuoteItemInput {
  description?: string
  quantity?: number
  weight?: number | null
  length?: number | null
  width?: number | null
  height?: number | null
  declared_value?: number | null
}

export interface QuotePayload {
  id?: number
  client_id?: number | null
  agent_id?: number | null
  recipient_name: string
  recipient_phone?: string | null
  recipient_email?: string | null
  recipient_address?: string | null
  recipient_city?: string | null
  recipient_department?: string | null
  recipient_ruc?: string | null
  latitude?: number | null
  longitude?: number | null
  origin?: 'online' | 'local'
  zone_id?: number | null
  delivery_type?: string
  shipping_service?: string | null
  delivery_option?: string
  pickup_required?: boolean
  valid_until?: string | null
  notes?: string | null
  items: QuoteItemInput[]
}

export interface QuoteListFilters {
  page?: number
  limit?: number
  status?: QuoteStatus
  search?: string
  client_id?: number
  agent_id?: number
  from?: string
  to?: string
}

export const quoteService = {
  list(params?: QuoteListFilters) {
    const q = new URLSearchParams(
      Object.entries(params ?? {})
        .filter(([, v]) => v !== undefined && v !== null && v !== '')
        .map(([k, v]) => [k, String(v)])
    ).toString()
    return api.get<{ success: boolean; data: QuoteListResult }>(`/quote/list.php?${q}`)
  },

  get(id: number) {
    return api.get<{ success: boolean; data: QuoteDetailResult }>(`/quote/get.php?id=${id}`)
  },

  create(data: QuotePayload) {
    return api.post('/quote/create.php', data)
  },

  update(data: QuotePayload & { id: number }) {
    return api.put('/quote/update.php', data)
  },

  status(id: number, status: QuoteStatus) {
    return api.put('/quote/status.php', { id, status })
  },

  delete(id: number) {
    return api.delete('/quote/delete.php', { data: { id } })
  },

  convert(id: number) {
    return api.post('/quote/convert.php', { id })
  },

  stats(params?: { from?: string; to?: string; agent_id?: number }) {
    const q = new URLSearchParams(
      Object.entries(params ?? {})
        .filter(([, v]) => v !== undefined && v !== null && v !== '')
        .map(([k, v]) => [k, String(v)])
    ).toString()
    return api.get<{ success: boolean; data: QuoteStatsResult }>(`/quote/stats.php?${q}`)
  },
}
