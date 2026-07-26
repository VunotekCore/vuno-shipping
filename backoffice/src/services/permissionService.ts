import api from './api'
import type { Permission } from '../types'
import { buildPageParams } from './pagination'

export type { Permission }

export interface SyncResult {
  created: Array<{ module: string; action: string; label: string }>
  skipped: Array<{ module: string; reason: string; count?: number }>
  created_count: number
  skipped_count: number
}

export const permissionService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { permissions: Permission[]; pagination: import('./pagination').Pagination } }>(
      `/permission/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { permissions: Permission[] } }>('/permission/list.php?limit=500')
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

  sync() {
    return api.post<{ success: boolean; data: SyncResult; message: string }>('/permission/sync.php')
  },
}
