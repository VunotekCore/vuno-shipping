import api from './api'
import type { Package, TrackingEvent } from '../types'

export type { Package, TrackingEvent }

export interface PackageListParams {
  page?: number
  limit?: number
  status?: string
  client_id?: number
  driver_id?: number
}

function getApiBase(): string {
  return import.meta.env.VITE_API_URL || '/api'
}

export const packageService = {
  list(params?: PackageListParams) {
    const sp = new URLSearchParams()
    if (params?.page) sp.set('page', String(params.page))
    if (params?.limit) sp.set('limit', String(params.limit))
    if (params?.status) sp.set('status', params.status)
    if (params?.client_id) sp.set('client_id', String(params.client_id))
    if (params?.driver_id) sp.set('driver_id', String(params.driver_id))
    const qs = sp.toString()
    return api.get<{ success: boolean; data: { packages: Package[]; pagination: import('./pagination').Pagination } }>(
      `/package/list.php${qs ? '?' + qs : ''}`
    )
  },

  get(id: number) {
    return api.get<{ success: boolean; data: { package: Package; events: TrackingEvent[] } }>('/package/get.php', { params: { id } })
  },

  create(data: {
    client_id?: number; sender_name: string; sender_phone?: string;
    recipient_name: string; recipient_phone: string; recipient_address: string;
    recipient_city?: string; recipient_department?: string;
    description?: string; weight?: number; length?: number; width?: number; height?: number;
    declared_value?: number; delivery_type?: string; zone_id?: number; notes?: string
  }) {
    return api.post('/package/create.php', data)
  },

  update(data: { id: number; [key: string]: unknown }) {
    return api.put('/package/update.php', data)
  },

  delete(id: number) {
    return api.delete('/package/delete.php', { data: { id } })
  },

  assign(id: number, driverId: number | null) {
    return api.put('/package/assign.php', { id, assigned_to: driverId })
  },

  updateStatus(id: number, status: string, description?: string, latitude?: number, longitude?: number, location_name?: string, photo_url?: string) {
    return api.put('/package/updateStatus.php', { id, status, description, latitude, longitude, location_name, photo_url })
  },

  getEvents(packageId: number) {
    return api.get<{ success: boolean; data: { events: TrackingEvent[] } }>('/tracking_event/list.php', { params: { package_id: packageId } })
  },

  getQrUrl(id: number): string {
    return `${getApiBase()}/package/qrcode.php?id=${id}`
  },

  getLabelUrl(id: number): string {
    return `${getApiBase()}/package/label.php?id=${id}`
  },
}
