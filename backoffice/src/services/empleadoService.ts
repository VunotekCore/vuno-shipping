import api from './api'
import type { Empleado } from '../types'
import { buildPageParams } from './pagination'

export type { Empleado }

export const empleadoService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { empleados: Empleado[]; pagination: import('./pagination').Pagination } }>(
      `/empleado/list.php${buildPageParams(params)}`
    )
  },

  create(data: {
    name: string
    email?: string
    phone?: string
    cedula?: string
    fecha_ingreso?: string
    puesto_id: number
    user_id?: number | null
    is_active?: boolean
  }) {
    return api.post('/empleado/create.php', { ...data, is_active: data.is_active ? 1 : 0 })
  },

  update(data: {
    id: number
    name?: string
    email?: string | null
    phone?: string | null
    cedula?: string | null
    fecha_ingreso?: string | null
    puesto_id?: number
    user_id?: number | null
    is_active?: boolean
  }) {
    return api.put('/empleado/update.php', { ...data, is_active: data.is_active !== undefined ? (data.is_active ? 1 : 0) : undefined })
  },

  delete(id: number) {
    return api.delete('/empleado/delete.php', { data: { id } })
  },
}
