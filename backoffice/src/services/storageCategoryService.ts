import api from './api'
import type { StorageCategory } from '../types'
import { buildPageParams } from './pagination'

export type { StorageCategory }

export const storageCategoryService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { storage_categories: StorageCategory[]; pagination: import('./pagination').Pagination } }>(
      `/storage_category/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { storage_categories: StorageCategory[] } }>('/storage_category/list.php?all=1')
  },

  create(data: { name: string; color?: string | null; description?: string | null; is_active?: number }) {
    return api.post<{ success: boolean; data: { storage_category: StorageCategory } }>('/storage_category/create.php', data)
  },

  update(data: { id: number; name?: string; color?: string | null; description?: string | null; is_active?: number }) {
    return api.put<{ success: boolean; data: { storage_category: StorageCategory } }>('/storage_category/update.php', data)
  },

  delete(id: number) {
    return api.delete('/storage_category/delete.php', { data: { id } })
  },
}
