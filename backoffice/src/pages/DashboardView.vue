<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const stats = ref([
  { label: 'Envíos activos', value: '—', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', color: '#00A8FF' },
  { label: 'Clientes', value: '—', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', color: '#69dca4' },
  { label: 'En tránsito', value: '—', icon: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0', color: '#f59e0b' },
  { label: 'Entregados', value: '—', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', color: '#10b981' },
])
</script>

<template>
  <div class="space-y-6">
    <!-- Welcome -->
    <div>
      <h1 class="text-xl font-bold text-[#dae2fd]">Dashboard</h1>
      <p class="mt-1 text-sm text-[#64748b]">
        Bienvenido, <span class="text-[#94a3b8]">{{ auth.user?.name }}</span>
      </p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div
        v-for="stat in stats"
        :key="stat.label"
        class="admin-card-sm flex items-center gap-4 transition-all hover:border-[#1e293b]/80 hover:shadow-md"
      >
        <div
          class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
          :style="{ backgroundColor: stat.color + '15', color: stat.color }"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path :d="stat.icon" />
          </svg>
        </div>
        <div>
          <p class="text-2xl font-bold text-[#dae2fd] leading-none">{{ stat.value }}</p>
          <p class="mt-1 text-xs text-[#64748b]">{{ stat.label }}</p>
        </div>
      </div>
    </div>

    <!-- Placeholder card -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h2 class="text-sm font-semibold text-[#dae2fd]">Actividad reciente</h2>
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
</template>
