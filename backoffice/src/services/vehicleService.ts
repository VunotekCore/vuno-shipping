import api from './api'
import type { Vehicle } from '../types'
import { buildPageParams } from './pagination'

export type { Vehicle }

export const vehicleService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { vehicles: Vehicle[]; pagination: import('./pagination').Pagination } }>(
      `/vehicle/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { vehicles: Vehicle[] } }>('/vehicle/listAll.php')
  },

  create(data: { plate: string; brand?: string; model?: string; year?: number; color?: string; fuel_type?: string; capacity_kg?: number; capacity_m3?: number; current_driver_id?: number; status_id?: number; notes?: string }) {
    return api.post('/vehicle/create.php', data)
  },

  update(data: { id: number; plate?: string; brand?: string; model?: string; year?: number; color?: string; fuel_type?: string; capacity_kg?: number; capacity_m3?: number; current_driver_id?: number; status_id?: number; notes?: string }) {
    return api.put('/vehicle/update.php', data)
  },

  delete(id: number) {
    return api.delete('/vehicle/delete.php', { data: { id } })
  },
}
