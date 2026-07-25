import api from './api'

export interface Role {
  id: number
  name: string
  slug: string
  permission_count: number
  user_count: number
  created_at: string
}

export interface RolePermission {
  id: number
  module: string
  action: string
  label: string
}

export const roleService = {
  list() {
    return api.get<{ success: boolean; data: { roles: Role[] } }>('/role/list.php')
  },

  create(data: { name: string; slug?: string }) {
    return api.post('/role/create.php', data)
  },

  update(data: { id: number; name: string; slug?: string }) {
    return api.put('/role/update.php', data)
  },

  delete(id: number) {
    return api.delete('/role/delete.php', { data: { id } })
  },

  getPermissions(roleId: number) {
    return api.get<{ success: boolean; data: { permissions: RolePermission[] } }>('/role/permissions.php', { params: { id: roleId } })
  },

  setPermissions(roleId: number, permissionIds: number[]) {
    return api.put('/role/permissions.php', { id: roleId, permission_ids: permissionIds })
  },
}
