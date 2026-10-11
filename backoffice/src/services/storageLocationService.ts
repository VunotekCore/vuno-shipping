import api from './api'
import type { StorageLocation } from '../types'

export type { StorageLocation }

export interface StorageLocationListParams {
  page?: number
  limit?: number
  warehouse_id?: number
}

export const storageLocationService = {
  list(params?: StorageLocationListParams) {
    const sp = new URLSearchParams()
    if (params?.page) sp.set('page', String(params.page))
    if (params?.limit) sp.set('limit', String(params.limit))
    if (params?.warehouse_id) sp.set('warehouse_id', String(params.warehouse_id))
    const qs = sp.toString()
    return api.get<{ success: boolean; data: { storage_locations: StorageLocation[]; pagination: import('./pagination').Pagination } }>(
      `/storage_location/list.php${qs ? '?' + qs : ''}`
    )
  },

  listByWarehouse(warehouseId: number) {
    return api.get<{ success: boolean; data: { storage_locations: StorageLocation[] } }>(
      `/storage_location/list.php?warehouse_id=${warehouseId}&all=1`
    )
  },

  available(warehouseId: number, categoryId?: number | null) {
    const sp = new URLSearchParams({ warehouse_id: String(warehouseId) })
    if (categoryId) sp.set('category_id', String(categoryId))
    return api.get<{ success: boolean; data: { locations: StorageLocation[]; suggested_location_id: number | null } }>(
      `/storage_location/available.php?${sp.toString()}`
    )
  },

  create(data: {
    warehouse_id: number; aisle?: string; rack?: string; level?: string | number; position?: string | number;
    code?: string; category_id?: number | null; capacity?: number; is_active?: number
  }) {
    return api.post<{ success: boolean; data: { storage_location: StorageLocation } }>('/storage_location/create.php', data)
  },

  update(data: {
    id: number; code?: string; aisle?: string; rack?: string; level?: string | number; position?: string | number;
    category_id?: number | null; capacity?: number; is_active?: number
  }) {
    return api.put<{ success: boolean; data: { storage_location: StorageLocation } }>('/storage_location/update.php', data)
  },

  delete(id: number) {
    return api.delete('/storage_location/delete.php', { data: { id } })
  },

  generate(data: {
    warehouse_id: number; aisle: string; rack: string; levels: number; positions: number;
    capacity?: number; category_id?: number | null
  }) {
    return api.post<{ success: boolean; data: { created: number } }>('/storage_location/generate.php', data)
  },
}
