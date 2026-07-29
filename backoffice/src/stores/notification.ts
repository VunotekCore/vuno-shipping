import { defineStore } from 'pinia'
import { ref } from 'vue'
import { notificationService } from '../services/notificationService'
import type { Notification } from '../types'

export const useNotificationStore = defineStore('notification', () => {
  const notifications = ref<Notification[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  let pollingInterval: ReturnType<typeof setInterval> | null = null

  async function fetch() {
    try {
      loading.value = true
      const { data } = await notificationService.list(1, 5)
      if (data.success && data.data) {
        notifications.value = data.data.notifications
        unreadCount.value = data.data.unread_count
      }
    } catch {
      // silent
    } finally {
      loading.value = false
    }
  }

  async function markRead(notificationId: number) {
    try {
      await notificationService.markRead(notificationId)
      await fetch()
    } catch {
      // silent
    }
  }

  function startPolling() {
    fetch()
    pollingInterval = setInterval(fetch, 30000)
  }

  function stopPolling() {
    if (pollingInterval) {
      clearInterval(pollingInterval)
      pollingInterval = null
    }
  }

  return {
    notifications,
    unreadCount,
    loading,
    fetch,
    markRead,
    startPolling,
    stopPolling,
  }
})
