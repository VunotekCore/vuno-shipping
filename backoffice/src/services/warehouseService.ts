import api from './api'
import type { Warehouse } from '../types'
import { buildPageParams } from './pagination'

export type { Warehouse }

export const warehouseService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { warehouses: Warehouse[]; pagination: import('./pagination').Pagination } }>(
      `/warehouse/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { warehouses: Warehouse[] } }>('/warehouse/list.php?all=1')
  },

  create(data: { name: string; code: string; origin: 'online' | 'local'; address?: string | null; is_active?: number }) {
    return api.post<{ success: boolean; data: { warehouse: Warehouse } }>('/warehouse/create.php', data)
  },

  update(data: { id: number; name?: string; code?: string; origin?: 'online' | 'local'; address?: string | null; is_active?: number }) {
    return api.put<{ success: boolean; data: { warehouse: Warehouse } }>('/warehouse/update.php', data)
  },

  delete(id: number) {
    return api.delete('/warehouse/delete.php', { data: { id } })
  },
}
