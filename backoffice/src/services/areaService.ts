import api from './api'
import type { Area } from '../types'
import { buildPageParams } from './pagination'

export type { Area }

export const areaService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { areas: Area[]; pagination: import('./pagination').Pagination } }>(
      `/area/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { areas: Pick<Area, 'id' | 'name'>[] } }>('/area/listAll.php')
  },

  create(data: { name: string; description?: string }) {
    return api.post('/area/create.php', data)
  },

  update(data: { id: number; name: string; description?: string | null }) {
    return api.put('/area/update.php', data)
  },

  delete(id: number) {
    return api.delete('/area/delete.php', { data: { id } })
  },
}
