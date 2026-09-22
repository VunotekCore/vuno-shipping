import axios from 'axios'

function getCsrfToken(): string {
  const match = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : ''
}

export function getApiUrl(): string {
  const envUrl = import.meta.env.VITE_API_URL as string | undefined
  if (envUrl) {
    return envUrl
  }
  return '/api'
}

const api = axios.create({
  baseURL: getApiUrl(),
  timeout: 15000,
  withCredentials: true,
  headers: { 'Content-Type': 'application/json' },
})

api.interceptors.request.use((config) => {
  if (config.method && ['post', 'put', 'delete'].includes(config.method)) {
    const csrf = getCsrfToken()
    if (csrf) {
      config.headers['X-CSRF-Token'] = csrf
    }
  }
  return config
})

let isRefreshing = false
let failedQueue: Array<{ resolve: (value?: unknown) => void; reject: (reason?: unknown) => void }> = []

function processQueue(error: unknown) {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error)
    } else {
      prom.resolve()
    }
  })
  failedQueue = []
}

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config

    if (
      error.response?.status === 403 &&
      error.response?.data?.message === 'Token CSRF inválido' &&
      !originalRequest._csrfRetry
    ) {
      const url = originalRequest.url ?? ''
      const isAuthEndpoint = url.includes('/admin/login.php') || url.includes('/admin/refresh.php')

      if (isAuthEndpoint || (typeof window !== 'undefined' && window.location.pathname === '/backoffice/login')) {
        return Promise.reject(error)
      }

      originalRequest._csrfRetry = true
      try {
        await api.post('/admin/refresh.php')
        return api(originalRequest)
      } catch {
        if (typeof window !== 'undefined' && window.location.pathname !== '/backoffice/login') {
          window.location.href = '/backoffice/login'
        }
        return Promise.reject(error)
      }
    }

    if (error.response?.status === 401 && !originalRequest._retry) {
      const url = originalRequest.url ?? ''
      const isAuthEndpoint = url.includes('/admin/login.php') || url.includes('/admin/refresh.php')

      if (isAuthEndpoint || (typeof window !== 'undefined' && window.location.pathname === '/backoffice/login')) {
        return Promise.reject(error)
      }

      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then(() => api(originalRequest))
      }

      originalRequest._retry = true
      isRefreshing = true

      try {
        const { data } = await api.post('/admin/refresh.php')

        if (data.success) {
          processQueue(null)
          return api(originalRequest)
        }

        processQueue(error)
        if (typeof window !== 'undefined' && window.location.pathname !== '/backoffice/login') {
          window.location.href = '/backoffice/login'
        }
        return Promise.reject(error)
      } catch (refreshError) {
        processQueue(refreshError)
        if (typeof window !== 'undefined' && window.location.pathname !== '/backoffice/login') {
          window.location.href = '/backoffice/login'
        }
        return Promise.reject(refreshError)
      } finally {
        isRefreshing = false
      }
    }

    return Promise.reject(error)
  }
)

export default api
