<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { dashboardService } from '../services/dashboardService'
import { formatUSD, formatCord } from '../utils/format'
import PageHeader from '../components/ui/PageHeader.vue'

const auth = useAuthStore()
const router = useRouter()

const stats = ref([
  { label: 'Envíos activos', value: '—', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', accent: '#1e4b88' },
  { label: 'Clientes', value: '—', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', accent: '#1e4b88' },
  { label: 'En tránsito', value: '—', icon: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0', accent: '#0284c7' },
  { label: 'Entregados hoy', value: '—', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', accent: '#16a34a' },
  { label: 'Por cobrar (USD)', value: '—', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', accent: '#d97706' },
  { label: 'Cobrado hoy (USD)', value: '—', icon: 'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z', accent: '#16a34a' },
  { label: 'Cobrado hoy (C$)', value: '—', icon: 'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z', accent: '#16a34a' },
])

const quickActions = [
  { label: 'Admisión', desc: 'Registrar un paquete', route: { name: 'admision' }, color: '#1ea7dc', icon: 'M12 5v14M5 12h14' },
  { label: 'Escanear', desc: 'Buscar por tracking', route: { name: 'scan' }, color: '#1e4b88', icon: 'M11 4a7 7 0 100 14 7 7 0 000-14zM21 21l-4.35-4.35' },
  { label: 'Nueva cotización', desc: 'Cotizar un envío', route: { name: 'quotes' }, color: '#eb5a28', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
  { label: 'Embarques', desc: 'Consolidar y despachar', route: { name: 'embarques' }, color: '#16a34a', icon: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1' },
]

async function loadStats() {
  try {
    const { data } = await dashboardService.getStats()
    const s = data.data?.stats
    if (s) {
      stats.value[0].value = String(s.active_packages)
      stats.value[1].value = String(s.total_clients)
      stats.value[2].value = String(s.in_transit)
      stats.value[3].value = String(s.delivered_today)
      // `paid_amount` esta en la moneda cobrada: USD y C$ van en KPIs separados
      stats.value[4].value = s.pending_invoices != null ? formatUSD(s.pending_invoices) : '—'
      stats.value[5].value = s.paid_today_usd != null ? formatUSD(s.paid_today_usd) : '—'
      stats.value[6].value = s.paid_today_cord != null ? formatCord(s.paid_today_cord) : '—'
    }
  } catch {}
}

onMounted(() => loadStats())
</script>

<template>
  <div class="space-y-6">
    <!-- Welcome -->
    <PageHeader eyebrow="Panel de control" title="Dashboard">
      <template #subtitle>
        Bienvenido, <span class="font-medium text-content">{{ auth.user?.name }}</span>
      </template>
    </PageHeader>

    <!-- Stats -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
      <div
        v-for="stat in stats"
        :key="stat.label"
        class="admin-card-sm admin-stat transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[var(--shadow-card-hover)]"
      >
        <span class="admin-stat-accent" :style="{ backgroundColor: stat.accent }" />
        <div class="flex items-center gap-4">
          <div class="admin-stat-chip">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path :d="stat.icon" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="admin-stat-value">{{ stat.value }}</p>
            <p class="admin-stat-label">{{ stat.label }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Quick actions -->
      <div class="admin-card lg:col-span-1">
        <div class="admin-card-header">
          <h2 class="text-sm font-semibold text-content">Accesos rápidos</h2>
        </div>
        <div class="admin-card-body space-y-1.5">
          <button
            v-for="action in quickActions"
            :key="action.label"
            class="admin-quick-item flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left"
            @click="router.push(action.route)"
          >
            <span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
              :style="{ backgroundColor: action.color + '15', color: action.color }"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path :d="action.icon" />
              </svg>
            </span>
            <span class="min-w-0">
              <span class="block text-sm font-medium text-content">{{ action.label }}</span>
              <span class="block text-xs text-subtle">{{ action.desc }}</span>
            </span>
          </button>
        </div>
      </div>

      <!-- Recent activity -->
      <div class="admin-card lg:col-span-2">
        <div class="admin-card-header">
          <h2 class="text-sm font-semibold text-content">Actividad reciente</h2>
        </div>
        <div class="admin-card-body">
          <div class="admin-empty-state">
            <svg class="admin-empty-state-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
              <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="admin-empty-state-title">Sin actividad reciente</p>
            <p class="admin-empty-state-desc">Los envíos y movimientos aparecerán aquí cuando estén disponibles.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
