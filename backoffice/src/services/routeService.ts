import api from './api'
import type { DeliveryRoute, RouteStop, DriverLocation } from '../types'

export type { DeliveryRoute, RouteStop, DriverLocation }

export interface RouteListParams {
  page?: number
  limit?: number
  status?: string
  driver_id?: number
  date?: string
}

export const routeService = {
  list(params?: RouteListParams) {
    const sp = new URLSearchParams()
    if (params?.page) sp.set('page', String(params.page))
    if (params?.limit) sp.set('limit', String(params.limit))
    if (params?.status) sp.set('status', params.status)
    if (params?.driver_id) sp.set('driver_id', String(params.driver_id))
    if (params?.date) sp.set('date', params.date)
    const qs = sp.toString()
    return api.get<{ success: boolean; data: { routes: DeliveryRoute[]; pagination: import('./pagination').Pagination } }>(
      `/route/list.php${qs ? '?' + qs : ''}`
    )
  },

  get(id: number) {
    return api.get<{ success: boolean; data: { route: DeliveryRoute } }>('/route/get.php', { params: { id } })
  },

  create(data: {
    name: string; description?: string; assigned_to?: number; vehicle_id?: number;
    scheduled_date?: string; start_time?: string; end_time?: string;
    trajectory?: unknown; color?: string;
    start_latitude?: number; start_longitude?: number;
    end_latitude?: number; end_longitude?: number;
    zone_ids?: number[]
  }) {
    return api.post('/route/create.php', data)
  },

  update(data: {
    id: number; name?: string; description?: string | null;
    assigned_to?: number | null; vehicle_id?: number | null;
    scheduled_date?: string | null; start_time?: string | null; end_time?: string | null;
    trajectory?: unknown; color?: string;
    start_latitude?: number | null; start_longitude?: number | null;
    end_latitude?: number | null; end_longitude?: number | null;
    zone_ids?: number[]
  }) {
    return api.put('/route/update.php', data)
  },

  delete(id: number) {
    return api.delete('/route/delete.php', { data: { id } })
  },

  updateStatus(id: number, status: string) {
    return api.put('/route/status.php', { id, status })
  },

  getCandidatePackages(routeId: number) {
    return api.get<{ success: boolean; data: { packages: import('./packageService').Package[] } }>('/route/packages.php', { params: { route_id: routeId } })
  },

  generateStops(routeId: number, packageIds: number[]) {
    return api.post('/route/generate-stops.php', { route_id: routeId, package_ids: packageIds })
  },

  optimize(routeId: number) {
    return api.post('/route/optimize.php', { route_id: routeId })
  },
}

export const routeStopService = {
  list(routeId: number) {
    return api.get<{ success: boolean; data: { stops: RouteStop[] } }>('/route_stop/list.php', { params: { route_id: routeId } })
  },

  updateStatus(id: number, status: string) {
    return api.put('/route_stop/status.php', { id, status })
  },

  deliver(data: {
    id: number; signature_data?: string; photo_url?: string;
    notes?: string; latitude?: number; longitude?: number
  }) {
    return api.post('/route_stop/deliver.php', data)
  },

  uploadSignature(id: number, signatureData: string) {
    return api.post('/route_stop/signature.php', { id, signature_data: signatureData })
  },

  uploadPhoto(id: number, file: File) {
    const formData = new FormData()
    formData.append('id', String(id))
    formData.append('photo', file)
    return api.post('/route_stop/photo.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}

export const driverLocationService = {
  post(data: { latitude: number; longitude: number; accuracy?: number; speed?: number; heading?: number; battery_level?: number; is_active?: number }) {
    return api.post('/driver/location.php', data)
  },

  get(userId?: number) {
    const params: Record<string, string | number> = {}
    if (userId) params.user_id = userId
    return api.get<{ success: boolean; data: { location?: DriverLocation; locations?: DriverLocation[] } }>('/driver/location.php', { params })
  },

  history(userId: number, from?: string, to?: string) {
    const sp = new URLSearchParams({ user_id: String(userId) })
    if (from) sp.set('from', from)
    if (to) sp.set('to', to)
    return api.get<{ success: boolean; data: { history: Array<{ latitude: number; longitude: number; recorded_at: string }> } }>(
      `/driver/location-history.php?${sp.toString()}`
    )
  },

  driverRoutes() {
    return api.get<{ success: boolean; data: { routes: DeliveryRoute[] } }>('/driver/routes.php')
  },
}
