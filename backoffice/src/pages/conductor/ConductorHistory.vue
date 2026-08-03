<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()
import { driverLocationService, routeService, type DeliveryRoute, type RouteStop } from '../../services/routeService'

const loading = ref(true)
const history = ref<{ route: DeliveryRoute; stops: RouteStop[]; completed: number; total: number }[]>([])
const error = ref('')

async function load() {
  loading.value = true
  try {
    const res = await driverLocationService.driverRoutes()
    const routes: DeliveryRoute[] = res.data.data.routes ?? []
    const completedRoutes = routes.filter(r => r.status_name === 'completed')

    const items = []
    for (const r of completedRoutes.slice(0, 20)) {
      try {
        const detail = await routeService.get(r.id)
        const stops: RouteStop[] = detail.data.data.route.stops ?? []
        items.push({
          route: r,
          stops,
          completed: stops.filter(s => s.status_name === 'visited').length,
          total: stops.length,
        })
      } catch {}
    }
    history.value = items
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar historial'
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr: string | null | undefined) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('es-NI', { year: 'numeric', month: 'short', day: 'numeric' })
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-bold">Historial de rutas</h1>
      <button class="text-sm text-[#64748b] transition-colors hover:text-[#dae2fd]" @click="router.push('/conductor')">Volver</button>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-[#69dca4] border-t-transparent" />
    </div>

    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-sm text-[#DC2626]">{{ error }}</p>
    </div>

    <div v-else-if="history.length === 0" class="admin-card p-8 text-center">
      <p class="text-[#64748b]">Sin rutas completadas</p>
    </div>

    <div v-else class="space-y-3">
      <div v-for="item in history" :key="item.route.id" class="admin-card p-4 space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-medium text-[#dae2fd] truncate">{{ item.route.name }}</p>
          <span class="shrink-0 rounded-full bg-[#22c55e]/15 px-2.5 py-0.5 text-[11px] font-medium text-[#22c55e]">Completada</span>
        </div>
        <div class="flex items-center gap-4 text-xs text-[#64748b]">
          <span>{{ formatDate(item.route.scheduled_date) }}</span>
          <span>{{ item.completed }}/{{ item.total }} entregas</span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-[#1e293b]">
          <div class="h-full rounded-full bg-[#22c55e] transition-all" :style="{ width: `${item.total > 0 ? (item.completed / item.total) * 100 : 0}%` }" />
        </div>
      </div>
    </div>
  </div>
</template>
