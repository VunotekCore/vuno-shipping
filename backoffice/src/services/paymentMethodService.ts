import api from './api'
import type { PaymentMethod } from '../types'
import { buildPageParams } from './pagination'

export type { PaymentMethod }

export const paymentMethodService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { methods: PaymentMethod[]; pagination: import('./pagination').Pagination } }>(
      `/payment_method/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { methods: PaymentMethod[] } }>('/payment_method/listAll.php')
  },

  create(data: { name: string; description?: string; is_active?: number }) {
    return api.post('/payment_method/create.php', data)
  },

  update(data: { id: number; name?: string; description?: string; is_active?: number }) {
    return api.put('/payment_method/update.php', data)
  },

  delete(id: number) {
    return api.delete('/payment_method/delete.php', { data: { id } })
  },
}