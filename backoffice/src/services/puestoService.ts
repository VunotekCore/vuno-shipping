import api from './api'
import type { Puesto } from '../types'
import { buildPageParams } from './pagination'

export type { Puesto }

export const puestoService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { puestos: Puesto[]; pagination: import('./pagination').Pagination } }>(
      `/puesto/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { puestos: Pick<Puesto, 'id' | 'name' | 'direccion_name'>[] } }>('/puesto/listAll.php')
  },

  create(data: { name: string; description?: string; direccion_id: number }) {
    return api.post('/puesto/create.php', data)
  },

  update(data: { id: number; name: string; description?: string | null; direccion_id: number }) {
    return api.put('/puesto/update.php', data)
  },

  delete(id: number) {
    return api.delete('/puesto/delete.php', { data: { id } })
  },
}
