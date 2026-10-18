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
  <div class="flex min-h-screen bg-surface">
    <!-- Left panel — hero institucional (solo desktop) -->
    <div
      class="relative hidden w-full flex-col overflow-hidden bg-primary-deep p-10 text-white lg:flex lg:w-1/2 xl:p-14"
    >
      <!-- Grid táctico -->
      <div
        class="pointer-events-none absolute inset-0 opacity-60"
        style="
          background-size: 36px 36px;
          background-image:
            linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px),
            linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        "
      ></div>
      <!-- Glow ambiental -->
      <div
        class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-secondary/15 blur-3xl"
      ></div>
      <div
        class="pointer-events-none absolute -bottom-24 -right-24 h-96 w-96 rounded-full bg-accent/20 blur-3xl"
      ></div>
      <!-- Rutas de tránsito -->
      <svg
        class="pointer-events-none absolute inset-0 h-full w-full opacity-25"
        aria-hidden="true"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M-50,200 C150,120 300,380 500,240 S800,100 1100,320"
          fill="none"
          stroke="#51c7fe"
          stroke-dasharray="6 6"
          stroke-width="2"
        ></path>
        <path
          d="M-20,450 C200,380 350,550 600,420 S900,300 1200,480"
          fill="none"
          stroke="#c3e8ff"
          stroke-width="1.5"
        ></path>
        <circle
          class="animate-ping"
          cx="500"
          cy="240"
          r="4"
          fill="#51c7fe"
          style="transform-box: fill-box; transform-origin: center"
        ></circle>
        <circle cx="500" cy="240" r="3" fill="#ffffff"></circle>
        <circle cx="600" cy="420" r="4" fill="#ffb59e"></circle>
      </svg>

      <!-- Contenido principal -->
      <div class="relative z-10 flex flex-col justify-between h-full">
        <!-- Encabezado: identidad del hub -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="flex items-center justify-center rounded-xl bg-white p-2 shadow-md">
              <svg
                class="text-primary"
                width="34"
                height="34"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <rect x="1" y="3" width="15" height="13" />
                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
                <circle cx="5.5" cy="18.5" r="2.5" />
                <circle cx="18.5" cy="18.5" r="2.5" />
              </svg>
            </div>
            <div>
              <div class="flex items-center gap-1.5">
                <span class="inline-block h-2 w-2 rounded-full bg-success"></span>
                <span
                  class="font-mono text-xs font-medium uppercase tracking-widest text-secondary-soft"
                  >Gateway MIA → MGA</span
                >
              </div>
              <p class="text-[0.6875rem] font-bold uppercase tracking-wider text-white/70">
                Centro de control de envíos
              </p>
            </div>
          </div>
          <div
            class="hidden items-center gap-2 rounded-full border border-secondary/30 bg-primary/80 px-3 py-1.5 sm:flex"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="#51c7fe"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M5 12.55a11 11 0 0 1 14.08 0" />
              <path d="M1.42 9a16 16 0 0 1 21.16 0" />
              <path d="M8.53 16.11a6 6 0 0 1 6.95 0" />
              <circle cx="12" cy="20" r="1" />
            </svg>
            <span class="font-mono text-xs font-medium tracking-tight text-white">EN LÍNEA</span>
          </div>
        </div>

        <!-- Hero -->
        <div class="my-12 max-w-xl lg:my-0">
          <div
            class="mb-6 inline-flex items-center gap-2 rounded-full border border-secondary/20 bg-white/10 px-3 py-1 text-secondary-soft"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <circle cx="6" cy="19" r="3" />
              <path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15" />
              <circle cx="18" cy="5" r="3" />
            </svg>
            <span class="text-xs font-semibold tracking-[0.02em]"
              >Miami → Nicaragua · Envío integral</span
            >
          </div>
          <h1 class="mb-4 text-3xl font-bold leading-tight tracking-tight xl:text-4xl">
            Envíos de Miami a Nicaragua, bajo control
          </h1>
          <p class="mb-8 text-base leading-relaxed text-white/70">
            Recepción en nuestro almacén de Miami, consolidación, trámite aduanero y entrega a
            domicilio en Nicaragua — todo desde un solo panel.
          </p>

          <!-- Métricas reales -->
          <div class="grid grid-cols-3 gap-3">
            <div
              class="rounded-xl border border-secondary/20 bg-primary/40 p-4 backdrop-blur-sm transition-all hover:bg-primary/60"
            >
              <div class="mb-1 flex items-center gap-2 text-secondary">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                  <circle cx="12" cy="10" r="3" />
                </svg>
                <span class="text-[0.6875rem] font-bold uppercase tracking-wider text-white/70"
                  >Cobertura</span
                >
              </div>
              <div class="text-xl font-bold text-white">17</div>
              <p class="text-[0.6875rem] font-bold uppercase tracking-wider text-white/60">
                Departamentos
              </p>
            </div>
            <div
              class="rounded-xl border border-secondary/20 bg-primary/40 p-4 backdrop-blur-sm transition-all hover:bg-primary/60"
            >
              <div class="mb-1 flex items-center gap-2 text-secondary">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                  <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
                <span class="text-[0.6875rem] font-bold uppercase tracking-wider text-white/70"
                  >Entrega</span
                >
              </div>
              <div class="text-xl font-bold text-white">153</div>
              <p class="text-[0.6875rem] font-bold uppercase tracking-wider text-white/60">
                Municipios
              </p>
            </div>
            <div
              class="rounded-xl border border-secondary/20 bg-primary/40 p-4 backdrop-blur-sm transition-all hover:bg-primary/60"
            >
              <div class="mb-1 flex items-center gap-2 text-secondary">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path
                    d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"
                  />
                </svg>
                <span class="text-[0.6875rem] font-bold uppercase tracking-wider text-white/70"
                  >Transporte</span
                >
              </div>
              <div class="text-xl font-bold text-white">2</div>
              <p class="text-[0.6875rem] font-bold uppercase tracking-wider text-white/60">
                Vías: aérea y marítima
              </p>
            </div>
          </div>
        </div>

        <!-- Pie: estado de seguridad -->
        <div
          class="hidden border-t border-primary pt-6 lg:flex lg:items-center lg:justify-between lg:gap-3 lg:text-xs"
        >
          <div class="flex items-center gap-2 text-white/60">
            <svg
              width="13"
              height="13"
              viewBox="0 0 24 24"
              fill="none"
              stroke="#16a34a"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
              <path d="M7 11V7a5 5 0 0110 0v4" />
            </svg>
            <span>Conexión encriptada SSL 256-bit</span>
          </div>
          <div class="flex items-center gap-2 font-mono text-xs text-white/70">
            <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
            <span>SERVIDORES OK</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Right panel — formulario -->
    <div class="flex w-full flex-col p-6 sm:p-12 lg:w-1/2 xl:p-16">
      <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-8">
        <!-- Logo mobile -->
        <div class="mb-8 flex flex-col items-center gap-3 lg:hidden">
          <div
            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
          >
            <svg
              width="28"
              height="28"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <rect x="1" y="3" width="15" height="13" />
              <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
              <circle cx="5.5" cy="18.5" r="2.5" />
              <circle cx="18.5" cy="18.5" r="2.5" />
            </svg>
          </div>
          <h1 class="text-xl font-bold text-content">Vuno Shipping</h1>
          <p class="text-xs text-primary">Panel de administración</p>
        </div>

        <!-- Encabezado -->
        <div class="mb-8">
          <div
            class="mb-3 inline-flex items-center gap-1.5 rounded bg-secondary-soft px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-primary"
          >
            <svg
              width="12"
              height="12"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
            Acceso autorizado
          </div>
          <h2 class="text-2xl font-bold tracking-tight text-primary-deep">
            Bienvenido a Mundo Cargo Logistic
          </h2>
          <p class="mt-1.5 text-sm text-muted">
            Ingresa tus credenciales para acceder al panel de administración y control de envíos.
          </p>
        </div>

        <!-- Error -->
        <Transition name="fade">
          <div
            v-if="error"
            class="mb-5 flex items-center gap-2.5 rounded-lg border border-danger/20 bg-danger-soft px-3 py-2.5 text-sm text-danger"
          >
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="shrink-0"
              aria-hidden="true"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span>{{ error }}</span>
          </div>
        </Transition>

        <!-- Form -->
        <form class="space-y-4" @submit.prevent="handleLogin">
          <!-- Email -->
          <div>
            <label
              for="email"
              class="mb-1.5 block text-xs font-semibold tracking-[0.02em] text-content"
              >Correo electrónico</label
            >
            <div class="admin-input-group">
              <span class="admin-input-icon">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path
                    d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                  />
                  <polyline points="22,6 12,13 2,6" />
                </svg>
              </span>
              <input
                id="email"
                v-model="email"
                type="email"
                required
                autocomplete="email"
                placeholder="tu.correo@mundocargo.com"
                class="admin-input shadow-sm"
                :disabled="loading"
              />
            </div>
          </div>

          <!-- Contraseña -->
          <div>
            <label
              for="password"
              class="mb-1.5 block text-xs font-semibold tracking-[0.02em] text-content"
              >Contraseña</label
            >
            <div class="admin-input-group">
              <span class="admin-input-icon">
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                  <path d="M7 11V7a5 5 0 0110 0v4" />
                </svg>
              </span>
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="Ingresa tu contraseña"
                class="admin-input shadow-sm"
                :disabled="loading"
              />
              <button
                type="button"
                class="admin-input-toggle"
                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                :aria-pressed="showPassword"
                @click="showPassword = !showPassword"
              >
                <!-- Ojo abierto -->
                <svg
                  v-if="!showPassword"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
                <!-- Ojo cerrado -->
                <svg
                  v-else
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <path
                    d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"
                  />
                  <line x1="1" y1="1" x2="23" y2="23" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Submit -->
          <div class="pt-2">
            <button
              type="submit"
              :disabled="loading || !email || !password"
              class="admin-btn admin-btn-primary w-full py-3 shadow-sm transition-all active:scale-[0.99]"
            >
              <svg
                v-if="loading"
                class="animate-spin"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <circle
                  cx="12"
                  cy="12"
                  r="10"
                  stroke-dasharray="31.4159265"
                  stroke-dashoffset="10"
                />
              </svg>
              <template v-else>
                <span>Iniciar sesión</span>
                <svg
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                >
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 12 19" />
                </svg>
              </template>
            </button>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div
        class="mt-8 flex w-full max-w-md mx-auto flex-col items-center justify-between gap-3 border-t border-line pt-6 text-xs text-muted sm:flex-row"
      >
        <div class="flex items-center gap-1.5">
          <svg
            width="12"
            height="12"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0110 0v4" />
          </svg>
          <span>Conexión segura · SSL</span>
        </div>
        <span
          >&copy; {{ new Date().getFullYear() }} <a href="http://vunotek.com">Vunotek</a>- Todos los
          derechos reservados</span
        >
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
