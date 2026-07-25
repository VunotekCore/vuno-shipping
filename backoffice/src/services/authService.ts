import api from './api'

export const authService = {
  login(email: string, password: string) {
    return api.post('/admin/login.php', { email, password })
  },

  verify() {
    return api.get('/admin/verify.php')
  },

  logout() {
    return api.post('/admin/logout.php')
  },

  refresh(refreshToken: string) {
    return api.post('/admin/refresh.php', { refresh_token: refreshToken })
  },
}
