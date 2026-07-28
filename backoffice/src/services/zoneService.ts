import api from './api'
import type { Zone } from '../types'
import { buildPageParams } from './pagination'

export type { Zone }

export const zoneService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { zones: Zone[]; pagination: import('./pagination').Pagination } }>(
      `/zona/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { zones: Zone[] } }>('/zona/listAll.php')
  },

  create(data: { name: string; description?: string; lat?: number; lng?: number; surcharge?: number }) {
    return api.post('/zona/create.php', data)
  },

  update(data: { id: number; name?: string; description?: string; lat?: number; lng?: number; surcharge?: number; is_active?: boolean }) {
    return api.put('/zona/update.php', data)
  },

  delete(_id: number) {
    return Promise.resolve() as any
  },
}
