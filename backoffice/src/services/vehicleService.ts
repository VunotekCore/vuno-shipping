import api from './api'
import type { Vehicle, VehicleOdometerLog } from '../types'
import { buildPageParams } from './pagination'
import type { Pagination } from './pagination'

export type { Vehicle }

export const vehicleService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { vehicles: Vehicle[]; pagination: Pagination } }>(
      `/vehicle/list.php${buildPageParams(params)}`
    )
  },

  listAll() {
    return api.get<{ success: boolean; data: { vehicles: Vehicle[] } }>('/vehicle/listAll.php')
  },

  create(data: Partial<Vehicle>) {
    return api.post('/vehicle/create.php', data)
  },

  update(data: Partial<Vehicle> & { id: number }) {
    return api.put('/vehicle/update.php', data)
  },

  delete(id: number) {
    return api.delete('/vehicle/delete.php', { data: { id } })
  },

  kmRegister(data: {
    vehicle_id: number
    km_reading: number
    reading_type: string
    route_run_id?: number | null
    notes?: string | null
  }) {
    return api.post<{ success: boolean; data: { result: any; vehicle: Vehicle }; message?: string }>('/vehicle/km-register.php', data)
  },

  kmLog(vehicle_id: number, params?: { page?: number; limit?: number }) {
    const q = new URLSearchParams()
    q.set('vehicle_id', String(vehicle_id))
    if (params?.page) q.set('page', String(params.page))
    if (params?.limit) q.set('limit', String(params.limit))
    return api.get<{ success: boolean; data: { logs: VehicleOdometerLog[]; pagination: Pagination } }>(
      `/vehicle/km-log.php?${q.toString()}`
    )
  },
}
