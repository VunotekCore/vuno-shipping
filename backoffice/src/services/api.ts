import axios from 'axios'

export function getApiUrl(): string {
  if (import.meta.env.DEV) {
    return ''
  }
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

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      if (typeof window !== 'undefined') {
        const path = window.location.pathname
        if (path !== '/login') {
          window.location.href = '/login'
        }
      }
    }
    return Promise.reject(error)
  }
)

export default api
