import api from './api'
import type { User } from '../types'
import { buildPageParams } from './pagination'

export type { User }

export const userService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { users: User[]; pagination: import('./pagination').Pagination } }>(
      `/user/list.php${buildPageParams(params)}`
    )
  },

  create(data: { email: string; password: string; name: string; role_id: number }) {
    return api.post('/user/create.php', data)
  },

  update(data: { id: number; email?: string; name?: string; role_id?: number; password?: string }) {
    return api.put('/user/update.php', data)
  },

  delete(id: number) {
    return api.delete('/user/delete.php', { data: { id } })
  },
}
