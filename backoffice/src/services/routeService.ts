import api from './api'
import type { DeliveryRoute, RouteRun, RouteStop, DriverLocation } from '../types'

export type { DeliveryRoute, RouteRun, RouteStop, DriverLocation }

export interface RouteListParams {
  page?: number
  limit?: number
}

export interface RunListParams {
  page?: number
  limit?: number
  status?: string
  driver_id?: number
  route_id?: number
  date?: string
}

export const routeService = {
  list(params?: RouteListParams) {
    const sp = new URLSearchParams()
    if (params?.page) sp.set('page', String(params.page))
    if (params?.limit) sp.set('limit', String(params.limit))
    const qs = sp.toString()
    return api.get<{ success: boolean; data: { routes: DeliveryRoute[]; pagination: import('./pagination').Pagination } }>(
      `/route/list.php${qs ? '?' + qs : ''}`
    )
  },

  get(id: number) {
    return api.get<{ success: boolean; data: { route: DeliveryRoute } }>('/route/get.php', { params: { id } })
  },

  create(data: {
    name: string; description?: string;
    trajectory?: unknown; color?: string;
    default_driver_id?: number | null; default_vehicle_id?: number | null;
    start_latitude?: number; start_longitude?: number;
    end_latitude?: number; end_longitude?: number;
    zone_ids?: number[]
  }) {
    return api.post('/route/create.php', data)
  },

  update(data: {
    id: number; name?: string; description?: string | null;
    trajectory?: unknown; color?: string;
    default_driver_id?: number | null; default_vehicle_id?: number | null;
    start_latitude?: number | null; start_longitude?: number | null;
    end_latitude?: number | null; end_longitude?: number | null;
    zone_ids?: number[]
  }) {
    return api.put('/route/update.php', data)
  },

  delete(id: number) {
    return api.delete('/route/delete.php', { data: { id } })
  },
}

export const routeRunService = {
  list(params?: RunListParams) {
    const sp = new URLSearchParams()
    if (params?.page) sp.set('page', String(params.page))
    if (params?.limit) sp.set('limit', String(params.limit))
    if (params?.status) sp.set('status', params.status)
    if (params?.driver_id) sp.set('driver_id', String(params.driver_id))
    if (params?.route_id) sp.set('route_id', String(params.route_id))
    if (params?.date) sp.set('date', params.date)
    const qs = sp.toString()
    return api.get<{ success: boolean; data: { runs: RouteRun[]; pagination: import('./pagination').Pagination } }>(
      `/route_run/list.php${qs ? '?' + qs : ''}`
    )
  },

  get(id: number) {
    return api.get<{ success: boolean; data: { run: RouteRun } }>('/route_run/get.php', { params: { id } })
  },

  create(data: {
    route_id: number; run_date?: string; assigned_to?: number; vehicle_id?: number; notes?: string
  }) {
    return api.post<{ success: boolean; data: { run: RouteRun; carried_over: number } }>('/route_run/create.php', data)
  },

  update(data: {
    id: number; run_date?: string; assigned_to?: number | null; vehicle_id?: number | null;
    start_time?: string | null; end_time?: string | null; notes?: string | null
  }) {
    return api.put('/route_run/update.php', data)
  },

  delete(id: number) {
    return api.delete('/route_run/delete.php', { data: { id } })
  },

  updateStatus(id: number, status: string) {
    return api.put('/route_run/status.php', { id, status })
  },

  getCandidatePackages(runId: number) {
    return api.get<{ success: boolean; data: { packages: import('./packageService').Package[] } }>('/route_run/packages.php', { params: { run_id: runId } })
  },

  generateStops(runId: number, packageIds: number[]) {
    return api.post('/route_run/generate-stops.php', { run_id: runId, package_ids: packageIds })
  },

  optimize(runId: number) {
    return api.post('/route_run/optimize.php', { run_id: runId })
  },

  getPickingUrl(runId: number): string {
    const base = import.meta.env.VITE_API_URL || '/api'
    return `${base}/route_run/picking.php?id=${runId}`
  },
}

export const routeStopService = {
  list(runId: number) {
    return api.get<{ success: boolean; data: { stops: RouteStop[] } }>('/route_stop/list.php', { params: { run_id: runId } })
  },

  updateStatus(id: number, status: string, notes?: string) {
    return api.put('/route_stop/status.php', { id, status, notes })
  },

  deliver(data: {
    id: number;
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
    return api.get<{ success: boolean; data: { runs: RouteRun[] } }>('/driver/routes.php')
  },
}
