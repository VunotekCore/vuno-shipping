<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()
import { driverLocationService, routeRunService, type RouteRun, type RouteStop } from '../../services/routeService'

const loading = ref(true)
const history = ref<{ route: RouteRun; stops: RouteStop[]; completed: number; total: number }[]>([])
const error = ref('')

async function load() {
  loading.value = true
  try {
    const res = await driverLocationService.driverRoutes()
    const runs: RouteRun[] = res.data.data.runs ?? []
    const completedRuns = runs.filter(r => r.status_name === 'completed')

    const items = []
    for (const r of completedRuns.slice(0, 20)) {
      try {
        const detail = await routeRunService.get(r.id)
        const stops: RouteStop[] = detail.data.data.run.stops ?? []
        items.push({
          route: r,
          stops,
          completed: stops.filter(s => s.status_name === 'delivered').length,
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

function formatSchedule(start: string | null | undefined, end: string | null | undefined) {
  if (!start && !end) return '—'
  if (start && end) return `${start}–${end}`
  return start || end || '—'
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-lg font-bold">Historial de rutas</h1>
      <button class="text-sm text-subtle transition-colors hover:text-content" @click="router.push('/conductor')">Volver</button>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
    </div>

    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-sm text-danger">{{ error }}</p>
    </div>

    <div v-else-if="history.length === 0" class="admin-card p-8 text-center">
      <p class="text-subtle">Sin rutas completadas</p>
    </div>

    <div v-else class="space-y-3">
      <div v-for="item in history" :key="item.route.id" class="admin-card p-4 space-y-2">
        <div class="flex items-center justify-between">
          <p class="font-medium text-content truncate">{{ item.route.route_name }}</p>
          <span class="shrink-0 rounded-full bg-success/15 px-2.5 py-0.5 text-[11px] font-medium text-success">Completada</span>
        </div>
        <div class="flex items-center gap-4 text-xs text-subtle">
          <span>{{ formatSchedule(item.route.start_time, item.route.end_time) }}</span>
          <span>{{ item.completed }}/{{ item.total }} entregas</span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-raised">
          <div class="h-full rounded-full bg-success transition-all" :style="{ width: `${item.total > 0 ? (item.completed / item.total) * 100 : 0}%` }" />
        </div>
      </div>
    </div>
  </div>
</template>
