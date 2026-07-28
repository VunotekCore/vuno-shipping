import api from './api'
import type { DashboardStats } from '../types'

export type { DashboardStats }

export const dashboardService = {
  getStats() {
    return api.get<{ success: boolean; data: { stats: DashboardStats } }>('/dashboard/stats.php')
  },
}
