import type { Pagination } from '../composables/useCrudView'

export type { Pagination }

export function buildPageParams(params?: { page?: number; limit?: number }): string {
  if (!params) return ''
  const sp = new URLSearchParams()
  if (params.page) sp.set('page', String(params.page))
  if (params.limit) sp.set('limit', String(params.limit))
  const qs = sp.toString()
  return qs ? '?' + qs : ''
}
