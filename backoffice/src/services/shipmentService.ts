import api from './api'
import type { Shipment, ShipmentPackage } from '../types'

export type { Shipment, ShipmentPackage }

export interface ShipmentListParams {
  page?: number
  limit?: number
  status?: string
}

export const shipmentService = {
  list(params?: ShipmentListParams) {
    const sp = new URLSearchParams()
    if (params?.page) sp.set('page', String(params.page))
    if (params?.limit) sp.set('limit', String(params.limit))
    if (params?.status) sp.set('status', params.status)
    const qs = sp.toString()
    return api.get<{ success: boolean; data: { shipments: Shipment[]; pagination: import('./pagination').Pagination } }>(
      `/shipment/list.php${qs ? '?' + qs : ''}`
    )
  },

  get(id: number) {
    return api.get<{ success: boolean; data: { shipment: Shipment; packages: ShipmentPackage[] } }>('/shipment/get.php', { params: { id } })
  },

  getEligible(q?: string, mode?: 'air' | 'sea') {
    const params: Record<string, string> = {}
    if (q) params.q = q
    if (mode) params.mode = mode
    return api.get<{ success: boolean; data: { packages: ShipmentPackage[] } }>('/shipment/eligible-packages.php', { params })
  },

  create(data: { code?: string; mode: 'air' | 'sea'; departure_date?: string; arrival_date?: string; notes?: string }) {
    return api.post('/shipment/create.php', data)
  },

  update(data: { id: number; code?: string; mode?: string; departure_date?: string | null; arrival_date?: string | null; notes?: string | null }) {
    return api.put('/shipment/update.php', data)
  },

  delete(id: number) {
    return api.delete('/shipment/delete.php', { data: { id } })
  },

  addPackages(shipmentId: number, packageIds: number[]) {
    return api.post('/shipment/add-packages.php', { shipment_id: shipmentId, package_ids: packageIds })
  },

  removePackage(shipmentId: number, packageId: number) {
    return api.delete('/shipment/remove-package.php', { data: { shipment_id: shipmentId, package_id: packageId } })
  },

  dispatch(shipmentId: number, departure_date?: string) {
    return api.post('/shipment/dispatch.php', { shipment_id: shipmentId, departure_date })
  },

  arrive(shipmentId: number, arrival_date?: string) {
    return api.post('/shipment/arrive.php', { shipment_id: shipmentId, arrival_date })
  },
}