import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '../services/authService'
import type { User } from '../types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const refreshToken = ref<string | null>(localStorage.getItem('refresh_token'))
  const mustChangePassword = ref(false)

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
      mustChangePassword.value = !!data.data.must_change_password
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
    mustChangePassword.value = false
    localStorage.removeItem('refresh_token')
    authService.logout().catch(() => {})
    window.location.href = '/login'
  }

  function setUser(userData: User) {
    user.value = userData
  }

  function setRefreshToken(token: string) {
    refreshToken.value = token
    localStorage.setItem('refresh_token', token)
  }

  function clearAuth() {
    user.value = null
    refreshToken.value = null
    mustChangePassword.value = false
    localStorage.removeItem('refresh_token')
  }

  async function verify(): Promise<boolean> {
    try {
      const { data } = await authService.verify()

      if (!data.success) {
        user.value = null
        return false
      }

      user.value = data.data?.user ?? null
      mustChangePassword.value = !!data.data?.must_change_password
      return true
    } catch {
      user.value = null
      return false
    }
  }

  return { user, refreshToken, isAuthenticated, mustChangePassword, hasPermission, login, logout, setUser, setRefreshToken, clearAuth, verify }
})
