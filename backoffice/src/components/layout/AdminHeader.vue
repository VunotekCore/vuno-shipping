<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const emit = defineEmits<{
  (e: 'toggle-sidebar'): void
}>()

const route = useRoute()

const pageTitle = computed(() => {
  const titles: Record<string, string> = {
    dashboard: 'Dashboard',
    users: 'Usuarios',
    roles: 'Roles',
    permissions: 'Permisos',
  }
  return titles[route.name as string] ?? 'Dashboard'
})
</script>

<template>
  <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-4 border-b border-[#1e293b] bg-[#111d2e]/80 backdrop-blur-md px-6">
    <!-- Left: mobile hamburger + page title -->
    <div class="flex items-center gap-3">
      <button
        class="flex items-center justify-center rounded-lg p-1.5 text-[#94a3b8] transition-colors hover:bg-white/[0.06] hover:text-[#dae2fd] lg:hidden"
        aria-label="Abrir menu"
        @click="emit('toggle-sidebar')"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>
      <h1 class="text-lg font-semibold text-[#dae2fd] max-sm:text-base">{{ pageTitle }}</h1>
    </div>

    <!-- Right: notifications placeholder -->
    <div class="ml-auto flex items-center gap-2">
      <button
        class="relative flex items-center justify-center rounded-lg p-2 text-[#94a3b8] transition-colors hover:bg-white/[0.06] hover:text-[#dae2fd]"
        aria-label="Notificaciones"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 01-3.46 0" />
        </svg>
        <span class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-[#DC2626] text-[9px] font-bold text-white">
          0
        </span>
      </button>
    </div>
  </header>
</template>
