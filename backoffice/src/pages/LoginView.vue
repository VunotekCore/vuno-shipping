<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppToast from '../components/ui/AppToast.vue'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
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
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen bg-[#0a1022]">
    <!-- Left panel — brand -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden items-center justify-center bg-gradient-to-br from-[#0b1326] to-[#111d2e]">
      <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(circle at 1px 1px, #69dca4 1px, transparent 0); background-size: 40px 40px;"></div>

      <div class="relative z-10 flex flex-col items-center gap-6 px-12 text-center">
        <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-[#69dca4]/10 text-[#69dca4]">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" /><circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-[#dae2fd] tracking-tight">Shipping Agency</h1>
          <p class="mt-2 text-sm text-[#64748b] leading-relaxed">Panel de administración</p>
        </div>
        <div class="mt-4 flex gap-3 text-xs text-[#64748b]">
          <span class="flex items-center gap-1.5 rounded-full bg-white/[0.04] px-3 py-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-[#69dca4]"></span>
            Envíos Miami → Nicaragua
          </span>
          <span class="flex items-center gap-1.5 rounded-full bg-white/[0.04] px-3 py-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-[#00A8FF]"></span>
            Gestión de paquetes
          </span>
        </div>
      </div>
    </div>

    <!-- Right panel — login form -->
    <div class="flex flex-1 items-center justify-center px-6 py-12">
      <div class="w-full max-w-sm">
        <!-- Mobile-only logo -->
        <div class="mb-8 flex flex-col items-center gap-3 lg:hidden">
          <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#69dca4]/10 text-[#69dca4]">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" /><circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
            </svg>
          </div>
          <h1 class="text-xl font-bold text-[#dae2fd]">Shipping Agency</h1>
        </div>

        <div>
          <h2 class="text-xl font-bold text-[#dae2fd]">Iniciar sesión</h2>
          <p class="mt-1.5 text-sm text-[#64748b]">Ingresá tus credenciales para acceder</p>
        </div>

        <form class="mt-8 space-y-5" @submit.prevent="handleLogin">
          <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Email</label>
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

          <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Contraseña</label>
            <input
              id="password"
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              class="admin-input"
              :disabled="loading"
            />
          </div>

          <Transition name="fade">
            <p v-if="error" class="rounded-lg border border-[#DC2626]/20 bg-[#DC2626]/10 px-3 py-2 text-sm text-[#f87171]">
              {{ error }}
            </p>
          </Transition>

          <button
            type="submit"
            :disabled="loading || !email || !password"
            class="admin-btn admin-btn-primary w-full py-2.5 text-sm"
          >
            <svg v-if="loading" class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="31.4159265" stroke-dashoffset="10" /></svg>
            <span>{{ loading ? 'Ingresando...' : 'Iniciar sesión' }}</span>
          </button>
        </form>
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
