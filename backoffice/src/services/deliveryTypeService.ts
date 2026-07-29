import api from './api'
import type { DeliveryType } from '../types'

export type { DeliveryType }

export const deliveryTypeService = {
  list() {
    return api.get<{ success: boolean; data: { delivery_types: DeliveryType[] } }>('/delivery_type/list.php')
  },
  create(data: { name: string; description?: string; category?: string }) {
    return api.post('/delivery_type/create.php', data)
  },
  update(data: { id: number; name?: string; description?: string | null; category?: string }) {
    return api.put('/delivery_type/update.php', data)
  },
  delete(id: number) {
    return api.delete('/delivery_type/delete.php', { data: { id } })
  },
}
