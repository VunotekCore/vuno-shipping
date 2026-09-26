import api from './api'

export interface PushSubscriptionPayload {
  endpoint: string
  p256dh: string
  auth: string
}

export const pushService = {
  async config(): Promise<{ vapid_public_key: string; subject: string }> {
    const res = await api.get<{
      success: boolean
      data: { vapid_public_key: string; subject: string }
    }>('/push/config.php')
    return res.data.data
  },
  subscribe(sub: PushSubscriptionPayload) {
    return api.post('/push/subscribe.php', sub)
  },
  unsubscribe(endpoint: string) {
    return api.post('/push/unsubscribe.php', { endpoint })
  },
  test(userId?: number) {
    return api.post('/push/test.php', userId ? { user_id: userId } : {})
  },
}