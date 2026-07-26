import api from './api'
import type { Company } from '../types'

export type { Company }

export const companyService = {
  get() {
    return api.get<{ success: boolean; data: { company: Company } }>('/company/settings.php')
  },

  update(data: Partial<Pick<Company, 'name' | 'logo' | 'ruc' | 'address' | 'phone' | 'email' | 'website'>>) {
    return api.put('/company/update.php', data)
  },
}
