import api from './api'
import type { User } from '../types'

export type { User }

export const userService = {
  list() {
    return api.get<{ success: boolean; data: { users: User[] } }>('/user/list.php')
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
