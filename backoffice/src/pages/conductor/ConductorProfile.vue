<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { driverLocationService } from '../../services/routeService'
import { pushService } from '../../services/pushService'
import { usePushNotifications } from '../../composables/usePushNotifications'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'

const router = useRouter()
const auth = useAuthStore()

const user = auth.user
const location = ref<{ latitude: number | null; longitude: number | null; battery_level?: number | null; last_update?: string | null } | null>(null)
const loading = ref(true)

const push = usePushNotifications()
const testing = ref(false)
const testMsg = ref('')

async function load() {
  loading.value = true
  try {
    const res = await driverLocationService.get()
    location.value = res.data.data.location ?? null
  } catch {} finally { loading.value = false }
}

async function onEnable() {
  testMsg.value = ''
  const ok = await push.enable()
  if (ok) testMsg.value = 'Notificaciones activadas en este dispositivo'
}

async function onDisable() {
  testMsg.value = ''
  await push.disable()
  testMsg.value = 'Notificaciones desactivadas en este dispositivo'
}

async function onTest() {
  testing.value = true
  testMsg.value = ''
  try {
    await pushService.test()
    testMsg.value = 'Notificación de prueba enviada — revisá tu dispositivo'
  } catch (e: any) {
    testMsg.value = e?.response?.data?.error || 'No se pudo enviar la prueba'
  } finally {
    testing.value = false
  }
}

const pushStatusText = () => {
  if (!push.supported) return 'Tu navegador no soporta notificaciones push'
  if (push.permission.value === 'denied') return 'Bloqueado en el navegador — habilitalo en los ajustes'
  if (push.subscribed.value) return 'Activas en este dispositivo'
  return push.permission.value === 'granted' ? 'Listas para activar' : 'Sin activar'
}

onMounted(() => {
  load()
  push.init()
})
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-lg font-bold">Mi Perfil</h1>

    <div class="admin-card p-5 space-y-4">
      <div class="flex items-center gap-3">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-primary to-secondary font-bold text-lg text-surface">
          {{ (user?.name || '?')[0].toUpperCase() }}
        </div>
        <div>
          <p class="font-semibold">{{ user?.name || '—' }}</p>
          <p class="text-sm text-subtle">{{ user?.email || '—' }}</p>
        </div>
      </div>
    </div>

    <div class="admin-card p-5 space-y-3">
      <p class="text-sm font-medium text-muted uppercase tracking-wide">Ubicación</p>
      <div v-if="loading" class="h-4 w-32 animate-pulse rounded bg-surface-raised" />
      <div v-else-if="location">
        <p class="text-sm font-mono">{{ location.latitude !== null ? Number(location.latitude).toFixed(6) : '—' }}, {{ location.longitude !== null ? Number(location.longitude).toFixed(6) : '—' }}</p>
        <p v-if="location.battery_level != null" class="mt-1 text-xs text-subtle">Batería: {{ location.battery_level }}%</p>
        <p v-if="location.last_update" class="mt-1 text-xs text-subtle">Última actualización: {{ location.last_update }}</p>
      </div>
      <p v-else class="text-sm text-subtle">No hay ubicación registrada</p>
    </div>

    <div class="admin-card p-5 space-y-3">
      <p class="text-sm font-medium text-muted uppercase tracking-wide">Notificaciones push</p>
      <p v-if="push.error.value" class="text-sm text-danger">{{ push.error.value }}</p>
      <p v-else class="text-sm text-muted">{{ pushStatusText() }}</p>

      <div class="flex gap-2 pt-1">
        <button
          v-if="!push.subscribed.value"
          class="flex-1 rounded-lg bg-primary py-2.5 text-center text-sm font-semibold text-surface transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="!push.supported || push.enabling.value"
          @click="onEnable"
        >
          {{ push.enabling.value ? 'Activando...' : 'Activar notificaciones' }}
        </button>
        <button
          v-else
          class="flex-1 rounded-lg border border-line-strong py-2.5 text-center text-sm font-medium text-content transition-colors hover:bg-surface-raised disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="push.disabling.value"
          @click="onDisable"
        >
          {{ push.disabling.value ? 'Desactivando...' : 'Desactivar' }}
        </button>
        <button
          v-if="push.subscribed.value"
          class="rounded-lg border border-line-strong px-4 py-2.5 text-sm font-medium text-content transition-colors hover:bg-surface-raised disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="testing"
          @click="onTest"
        >
          {{ testing ? 'Enviando...' : 'Probar' }}
        </button>
      </div>
      <p v-if="testMsg" class="text-xs text-muted">{{ testMsg }}</p>
    </div>

    <div class="admin-card p-5">
      <p class="text-sm font-medium text-muted uppercase tracking-wide">Rol</p>
      <p class="mt-1 text-sm capitalize">{{ user?.role_name || 'Conductor' }}</p>
    </div>

    <button class="w-full rounded-lg border border-line-strong py-3 text-sm text-muted transition-colors hover:bg-surface-raised" @click="router.push('/conductor')">
      Volver al inicio
    </button>
  </div>
</template>
