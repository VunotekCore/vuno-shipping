import api from './api'
import type { Direccion } from '../types'
import { buildPageParams } from './pagination'

export type { Direccion }

export const direccionService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { direcciones: Direccion[]; pagination: import('./pagination').Pagination } }>(
      `/direccion/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { direcciones: Pick<Direccion, 'id' | 'name'>[] } }>('/direccion/listAll.php')
  },

  create(data: { name: string; description?: string }) {
    return api.post('/direccion/create.php', data)
  },

  update(data: { id: number; name: string; description?: string | null }) {
    return api.put('/direccion/update.php', data)
  },

  delete(id: number) {
    return api.delete('/direccion/delete.php', { data: { id } })
  },
}
