import api from './api'

export interface ShippingService {
  id: number
  name: string
  description: string | null
  is_active: number | boolean
  delivery_type_id: number | null
  delivery_type_name?: string | null
}

export interface DeliveryOption {
  id: number
  name: string
  description: string | null
  is_active: number | boolean
}

export interface ServiceOption {
  id: number
  name: string
  description: string | null
  is_default: number | boolean
}

export const shippingSettingsService = {
  // Shipping services
  listServices() {
    return api.get<{ success: boolean; data: { shipping_services: ShippingService[] } }>('/shipping_service/list.php')
  },
  createService(data: { name: string; description?: string; is_active?: boolean; delivery_type_id?: number }) {
    return api.post('/shipping_service/create.php', data)
  },
  updateService(data: { id: number; name?: string; description?: string | null; is_active?: boolean; delivery_type_id?: number | null }) {
    return api.put('/shipping_service/update.php', data)
  },
  deleteService(id: number) {
    return api.delete('/shipping_service/delete.php', { data: { id } })
  },

  // Delivery options
  listOptions() {
    return api.get<{ success: boolean; data: { delivery_options: DeliveryOption[] } }>('/delivery_option/list.php')
  },
  createOption(data: { name: string; description?: string; is_active?: boolean }) {
    return api.post('/delivery_option/create.php', data)
  },
  updateOption(data: { id: number; name?: string; description?: string | null; is_active?: boolean }) {
    return api.put('/delivery_option/update.php', data)
  },
  deleteOption(id: number) {
    return api.delete('/delivery_option/delete.php', { data: { id } })
  },

  // Service-Delivery mapping
  listServiceOptions(shippingServiceId: number) {
    return api.get<{ success: boolean; data: { service_options: ServiceOption[] } }>(`/service_delivery_option/list.php?shipping_service_id=${shippingServiceId}`)
  },
  updateServiceOptions(data: { shipping_service_id: number; delivery_option_ids: number[] | Array<{ delivery_option_id: number; is_default: boolean }> }) {
    return api.put('/service_delivery_option/update.php', data)
  },
}
