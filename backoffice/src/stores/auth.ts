import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '../services/authService'

interface Permissions {
  [module: string]: string[]
}

interface User {
  id: number
  email: string
  name: string
  role_id: number
  role_name: string
  role_slug: string
  permissions: Permissions
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)

  const isAuthenticated = computed(() => !!user.value)

  const hasPermission = computed(() => {
    return (module: string, action: string): boolean => {
      if (!user.value?.permissions) return false
      const perms = user.value.permissions[module]
      if (!Array.isArray(perms)) return false
      return perms.includes(action)
    }
  })

  const isViewer = computed(() => user.value?.role_slug === 'viewer')

  async function login(email: string, password: string): Promise<boolean> {
    const { data } = await authService.login(email, password)

    if (data.success && data.data?.user) {
      user.value = data.data.user
      return true
    }

    return false
  }

  function logout() {
    user.value = null
    authService.logout().catch(() => {})
    window.location.href = '/login'
  }

  async function verify(): Promise<boolean> {
    try {
      const { data } = await authService.verify()

      if (!data.success) {
        user.value = null
        return false
      }

      user.value = data.data?.user ?? null
      return true
    } catch {
      user.value = null
      return false
    }
  }

  return { user, isAuthenticated, isViewer, hasPermission, login, logout, verify }
})
