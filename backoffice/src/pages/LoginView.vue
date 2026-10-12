<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppToast from '../components/ui/AppToast.vue'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  error.value = ''
  loading.value = true

  try {
    const success = await auth.login(email.value, password.value)
    if (success) {
      router.push('/')
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err?.response?.data?.message || 'Error al iniciar sesion'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen bg-canvas">
    <!-- Left panel - brand (hidden on mobile) -->
    <div class="hidden lg:flex lg:w-[480px] xl:w-1/2 relative overflow-hidden flex-col bg-primary-deep">
      <!-- Decorative background patterns -->
      <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(circle at 1px 1px, #1ea7dc 1px, transparent 0); background-size: 40px 40px;"></div>
      <div class="absolute inset-0 opacity-[0.015]" style="background-image: linear-gradient(rgba(30,167,220,0.35) 1px, transparent 1px), linear-gradient(90deg, rgba(30,167,220,0.35) 1px, transparent 1px); background-size: 40px 40px;"></div>

      <!-- Centered content -->
      <div class="relative z-10 flex flex-1 flex-col items-center justify-center gap-6 px-12 text-center">
        <!-- Logo -->
        <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-white/10 text-white ring-1 ring-white/15">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" /><circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
          </svg>
        </div>

        <!-- Brand -->
        <div>
          <h1 class="text-2xl font-bold text-white tracking-tight">Vuno Shipping</h1>
          <p class="mt-2 text-sm text-white/60 leading-relaxed">Panel de administración</p>
        </div>

        <!-- Divider -->
        <div class="h-px w-12 bg-white/20"></div>

        <!-- Features -->
        <div class="mt-2 space-y-3 text-sm text-white/70">
          <div class="flex items-center gap-2.5">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1ea7dc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Gestion de envios Miami a Nicaragua</span>
          </div>
          <div class="flex items-center gap-2.5">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1ea7dc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Control de usuarios y permisos</span>
          </div>
          <div class="flex items-center gap-2.5">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1ea7dc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>Tracking en tiempo real</span>
          </div>
        </div>
      </div>

      <!-- Footer bar — pinned to bottom, spread to edges -->
      <div class="relative z-10 flex items-center justify-between px-6 py-4 text-xs text-subtle">
        <div class="flex items-center gap-1.5">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0110 0v4" />
          </svg>
          <span>Conexion segura - SSL</span>
        </div>
        <span>&copy; {{ new Date().getFullYear() }} Shipping Agency</span>
      </div>
    </div>

    <!-- Right panel - login form -->
    <div class="flex flex-1 items-center justify-center px-6 py-12">
      <div class="w-full max-w-sm">
        <!-- Mobile-only logo -->
        <div class="mb-8 flex flex-col items-center gap-3 lg:hidden">
          <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" /><circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
            </svg>
          </div>
          <h1 class="text-xl font-bold text-content">Vuno Shipping</h1>
          <p class="text-xs text-primary">admin panel</p>
        </div>

        <!-- Glass card -->
        <div class="admin-glass-card">
          <!-- Desktop card header -->
          <div class="mb-6 hidden flex-col items-center gap-2 lg:flex">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" /><circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
              </svg>
            </div>
            <h2 class="text-lg font-bold text-content">Bienvenido a Vuno Shipping</h2>
            <p class="text-center text-sm text-subtle">Ingresa tus credenciales para acceder al panel de administracion.</p>
          </div>

          <!-- Mobile heading -->
          <div class="mb-6 lg:hidden">
            <h2 class="text-lg font-bold text-content">Iniciar sesion</h2>
            <p class="mt-1.5 text-sm text-subtle">Ingresa tus credenciales para acceder</p>
          </div>

          <!-- Error -->
          <Transition name="fade">
            <div v-if="error" class="mb-5 flex items-center gap-2.5 rounded-lg border border-danger/20 bg-danger-soft px-3 py-2.5 text-sm text-danger">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
                <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              <span>{{ error }}</span>
            </div>
          </Transition>

          <!-- Form -->
          <form class="space-y-4" @submit.prevent="handleLogin">
            <!-- Email -->
            <div>
              <label for="email" class="mb-1.5 block text-sm font-medium text-muted">Email</label>
              <div class="admin-input-group">
                <span class="admin-input-icon">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" /><polyline points="22,6 12,13 2,6" />
                  </svg>
                </span>
                <input
                  id="email"
                  v-model="email"
                  type="email"
                  required
                  autocomplete="email"
                  placeholder="admin@shipping.com"
                  class="admin-input"
                  :disabled="loading"
                />
              </div>
            </div>

            <!-- Password -->
            <div>
              <label for="password" class="mb-1.5 block text-sm font-medium text-muted">Contrasena</label>
              <div class="admin-input-group">
                <span class="admin-input-icon">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0110 0v4" />
                  </svg>
                </span>
                <input
                  id="password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  autocomplete="current-password"
                  placeholder="Ingresa tu contrasena"
                  class="admin-input"
                  :disabled="loading"
                />
                <button
                  type="button"
                  class="admin-input-toggle"
                  :aria-label="showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'"
                  :aria-pressed="showPassword"
                  @click="showPassword = !showPassword"
                >
                  <!-- Eye open -->
                  <svg v-if="!showPassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" />
                  </svg>
                  <!-- Eye closed -->
                  <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" /><line x1="1" y1="1" x2="23" y2="23" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Submit -->
            <button
              type="submit"
              :disabled="loading || !email || !password"
              class="admin-btn admin-btn-primary w-full py-2.5 text-sm uppercase tracking-wide"
            >
              <svg v-if="loading" class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" stroke-dasharray="31.4159265" stroke-dashoffset="10" />
              </svg>
              <template v-else>
                <span>{{ loading ? 'Ingresando...' : 'Iniciar sesion' }}</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" />
                </svg>
              </template>
            </button>
          </form>
        </div>

        <!-- Mobile footer -->
        <div class="mt-6 flex flex-col items-center gap-2 text-xs text-subtle lg:hidden">
          <div class="flex items-center gap-1.5">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0110 0v4" />
            </svg>
            <span>Conexion segura - SSL</span>
          </div>
          <span>&copy; {{ new Date().getFullYear() }} Shipping Agency</span>
        </div>
      </div>
    </div>

    <AppToast />
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
