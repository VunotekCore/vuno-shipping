import api from './api'
import type { Notification } from '../types'

export const notificationService = {
  list(page = 1, limit = 20) {
    return api.get<{
      success: boolean
      data: { notifications: Notification[]; unread_count: number }
    }>(`/notification/list.php?page=${page}&limit=${limit}`)
  },

  markRead(notificationId: number) {
    return api.post('/notification/read.php', { notification_id: notificationId })
  },
}
