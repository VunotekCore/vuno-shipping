import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '../services/authService'
import type { User } from '../types'

localStorage.removeItem('refresh_token')

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
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
      return true
    }

    return false
  }

  function logout() {
    user.value = null
    mustChangePassword.value = false
    authService.logout().catch(() => {})
    window.location.href = '/backoffice/login'
  }

  function setUser(userData: User) {
    user.value = userData
  }

  function clearAuth() {
    user.value = null
    mustChangePassword.value = false
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

  return { user, isAuthenticated, mustChangePassword, hasPermission, login, logout, setUser, clearAuth, verify }
})