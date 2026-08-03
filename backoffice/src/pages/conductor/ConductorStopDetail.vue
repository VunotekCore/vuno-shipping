<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { routeService, type RouteStop } from '../../services/routeService'

const route = useRoute()
const router = useRouter()
const stopId = computed(() => Number(route.params.id))

const loading = ref(true)
const stop = ref<RouteStop | null>(null)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    // Load stops from the active route, find the one with matching id
    const res = await driverLocationService.driverRoutes()
    const routes: any[] = res.data.data.routes ?? []
    const active = routes.find(r => r.status_name === 'active') || routes[0]
    if (!active) { error.value = 'Sin ruta activa'; return }

    const detail = await routeService.get(active.id)
    const stops: RouteStop[] = detail.data.data.route.stops ?? []
    stop.value = stops.find(s => s.id === stopId.value) ?? null
    if (!stop.value) error.value = 'Parada no encontrada'
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar'
  } finally {
    loading.value = false
  }
}

function goDeliver() {
  router.push(`/conductor/deliver/${stopId.value}`)
}

function goBack() {
  router.push('/conductor')
}

import { driverLocationService } from '../../services/routeService'

onMounted(() => load())
</script>

<template>
  <div class="space-y-4">
    <button class="flex items-center gap-2 text-sm text-[#94a3b8] hover:text-[#dae2fd]" @click="goBack">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Volver
    </button>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-[#69dca4] border-t-transparent" />
    </div>

    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-sm text-[#DC2626]">{{ error }}</p>
    </div>

    <template v-else-if="stop">
      <!-- Status badge -->
      <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold">Parada #{{ stop.stop_order }}</h1>
        <span class="rounded-full px-3 py-1 text-[11px] font-medium"
          :class="stop.status_name === 'visited' ? 'bg-[#22c55e]/15 text-[#22c55e]' : stop.status_name === 'pending' ? 'bg-[#eab308]/15 text-[#eab308]' : 'bg-[#1e293b] text-[#64748b]'"
        >
          {{ stop.status_name }}
        </span>
      </div>

      <!-- Client info -->
      <div class="admin-card p-5 space-y-4">
        <div>
          <p class="text-xs text-[#64748b] uppercase tracking-wide">Cliente</p>
          <p class="mt-0.5 text-base font-medium">{{ stop.client_name || '—' }}</p>
        </div>

        <div>
          <p class="text-xs text-[#64748b] uppercase tracking-wide">Dirección</p>
          <p class="mt-0.5 text-sm">{{ stop.address || '—' }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-[#64748b] uppercase tracking-wide">Tracking</p>
            <p class="mt-0.5 text-sm font-mono">{{ stop.tracking_number || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-[#64748b] uppercase tracking-wide">Teléfono</p>
            <p class="mt-0.5 text-sm">{{ stop.recipient_phone || '—' }}</p>
          </div>
        </div>

        <div v-if="stop.notes">
          <p class="text-xs text-[#64748b] uppercase tracking-wide">Notas</p>
          <p class="mt-0.5 text-sm">{{ stop.notes }}</p>
        </div>
      </div>

      <!-- Package info -->
      <div v-if="stop.package_description" class="admin-card p-5">
        <p class="text-xs text-[#64748b] uppercase tracking-wide">Descripción del paquete</p>
        <p class="mt-0.5 text-sm">{{ stop.package_description }}</p>
      </div>

      <!-- Action -->
      <button
        v-if="stop.status_name === 'pending'"
        class="w-full rounded-lg bg-[#69dca4] py-3 text-center text-sm font-semibold text-[#0b1326] transition-opacity hover:opacity-90"
        @click="goDeliver"
      >
        Entregar paquete
      </button>

      <div v-else-if="stop.signature_data" class="admin-card p-5">
        <p class="text-xs text-[#64748b] uppercase tracking-wide">Firma del cliente</p>
        <img :src="stop.signature_data" alt="Firma" class="mt-2 max-h-24 w-full rounded-lg border border-[#1e293b] bg-white object-contain p-2" />
      </div>

      <div v-if="stop.photo_url" class="admin-card p-5">
        <p class="text-xs text-[#64748b] uppercase tracking-wide">Foto de evidencia</p>
        <img :src="stop.photo_url" alt="Foto" class="mt-2 w-full rounded-lg border border-[#1e293b]" />
      </div>

      <div v-if="stop.actual_arrival" class="admin-card p-5">
        <p class="text-xs text-[#64748b] uppercase tracking-wide">Entregado a las</p>
        <p class="mt-0.5 text-sm">{{ stop.actual_arrival }}</p>
      </div>
    </template>
  </div>
</template>
