import api from './api'

export interface Permission {
  id: number
  module: string
  action: string
  label: string
  created_at: string
}

export const permissionService = {
  list() {
    return api.get<{ success: boolean; data: { permissions: Permission[] } }>('/permission/list.php')
  },

  create(data: { module: string; action: string; label: string }) {
    return api.post('/permission/create.php', data)
  },

  update(data: { id: number; module: string; action: string; label: string }) {
    return api.put('/permission/update.php', data)
  },

  delete(id: number) {
    return api.delete('/permission/delete.php', { data: { id } })
  },
}
