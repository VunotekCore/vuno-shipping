<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { driverLocationService } from '../../services/routeService'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'

const router = useRouter()
const auth = useAuthStore()

const user = auth.user
const location = ref<{ latitude: number | null; longitude: number | null; battery_level?: number | null; last_update?: string | null } | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const res = await driverLocationService.get()
    location.value = res.data.data.location ?? null
  } catch {} finally { loading.value = false }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-lg font-bold">Mi Perfil</h1>

    <div class="admin-card p-5 space-y-4">
      <div class="flex items-center gap-3">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-[#69dca4] to-[#00A8FF] font-bold text-lg text-[#0b1326]">
          {{ (user?.name || '?')[0].toUpperCase() }}
        </div>
        <div>
          <p class="font-semibold">{{ user?.name || '—' }}</p>
          <p class="text-sm text-[#64748b]">{{ user?.email || '—' }}</p>
        </div>
      </div>
    </div>

    <div class="admin-card p-5 space-y-3">
      <p class="text-sm font-medium text-[#94a3b8] uppercase tracking-wide">Ubicación</p>
      <div v-if="loading" class="h-4 w-32 animate-pulse rounded bg-[#1e293b]" />
      <div v-else-if="location">
        <p class="text-sm font-mono">{{ location.latitude !== null ? Number(location.latitude).toFixed(6) : '—' }}, {{ location.longitude !== null ? Number(location.longitude).toFixed(6) : '—' }}</p>
        <p v-if="location.battery_level != null" class="mt-1 text-xs text-[#64748b]">Batería: {{ location.battery_level }}%</p>
        <p v-if="location.last_update" class="mt-1 text-xs text-[#64748b]">Última actualización: {{ location.last_update }}</p>
      </div>
      <p v-else class="text-sm text-[#64748b]">No hay ubicación registrada</p>
    </div>

    <div class="admin-card p-5">
      <p class="text-sm font-medium text-[#94a3b8] uppercase tracking-wide">Rol</p>
      <p class="mt-1 text-sm capitalize">{{ user?.role_name || 'Conductor' }}</p>
    </div>

    <button class="w-full rounded-lg border border-[#334155] py-3 text-sm text-[#94a3b8] transition-colors hover:bg-white/[0.04]" @click="router.push('/conductor')">
      Volver al inicio
    </button>
  </div>
</template>
