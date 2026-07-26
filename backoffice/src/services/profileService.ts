import api from './api'
import type { User } from '../types'

export type { User }

export const profileService = {
  get() {
    return api.get<{ success: boolean; data: { user: User } }>('/user/profile.php')
  },

  update(data: { name?: string; email?: string; current_password?: string; new_password?: string }) {
    return api.put<{ success: boolean; data: { user: User } }>('/user/profile.php', data)
  },
}
