import api from './api'
import type { Client } from '../types'
import { buildPageParams } from './pagination'

export type { Client }

export const clientService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { clients: Client[]; pagination: import('./pagination').Pagination } }>(
      `/client/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { clients: Client[] } }>('/client/listAll.php')
  },

  create(data: { name: string; email?: string; phone?: string; ruc?: string; address?: string; department?: string; notes?: string; agent_id?: number | null }) {
    return api.post('/client/create.php', data)
  },

  update(data: { id: number; name?: string; email?: string; phone?: string; ruc?: string; address?: string; department?: string; notes?: string; is_active?: boolean; agent_id?: number | null }) {
    return api.put('/client/update.php', data)
  },

  delete(id: number) {
    return api.delete('/client/delete.php', { data: { id } })
  },
}
