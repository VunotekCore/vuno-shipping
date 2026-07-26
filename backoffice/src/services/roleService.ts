import api from './api'
import type { Role, Permission } from '../types'
import { buildPageParams } from './pagination'

export type { Role }
export type RolePermission = Pick<Permission, 'id' | 'module' | 'action' | 'label'>

export const roleService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { roles: Role[]; pagination: import('./pagination').Pagination } }>(
      `/role/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { roles: Pick<Role, 'id' | 'name' | 'slug'>[] } }>('/role/list.php?limit=500')
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
