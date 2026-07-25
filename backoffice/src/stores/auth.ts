import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '../services/authService'
import type { User } from '../types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const refreshToken = ref<string | null>(localStorage.getItem('refresh_token'))

  const isAuthenticated = computed(() => !!user.value)

  function hasPermission(module: string, action: string): boolean {
    if (!user.value?.permissions) return false
    const perms = user.value.permissions[module]
    if (!Array.isArray(perms)) return false
    return perms.includes(action)
  }

  async function login(email: string, password: string): Promise<boolean> {
    const { data } = await authService.login(email, password)

    if (data.success && data.data?.user) {
      user.value = data.data.user
      if (data.data.refresh_token) {
        refreshToken.value = data.data.refresh_token
        localStorage.setItem('refresh_token', data.data.refresh_token)
      }
      return true
    }

    return false
  }

  function logout() {
    user.value = null
    refreshToken.value = null
    localStorage.removeItem('refresh_token')
    authService.logout().catch(() => {})
    window.location.href = '/login'
  }

  async function refresh(): Promise<boolean> {
    if (!refreshToken.value) return false

    try {
      const { data } = await authService.refresh(refreshToken.value)

      if (data.success && data.data?.user) {
        user.value = data.data.user
        if (data.data.refresh_token) {
          refreshToken.value = data.data.refresh_token
          localStorage.setItem('refresh_token', data.data.refresh_token)
        }
        return true
      }

      return false
    } catch {
      refreshToken.value = null
      localStorage.removeItem('refresh_token')
      return false
    }
  }

  async function verify(): Promise<boolean> {
    try {
      const { data } = await authService.verify()

      if (!data.success) {
        // Try refresh before giving up
        if (refreshToken.value) {
          return await refresh()
        }
        user.value = null
        return false
      }

      user.value = data.data?.user ?? null
      return true
    } catch {
      // Try refresh before giving up
      if (refreshToken.value) {
        return await refresh()
      }
      user.value = null
      return false
    }
  }

  return { user, refreshToken, isAuthenticated, hasPermission, login, logout, refresh, verify }
})
