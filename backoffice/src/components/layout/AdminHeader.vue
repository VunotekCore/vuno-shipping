<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()

const userInitials = computed(() => {
  if (!auth.user?.name) return '?'
  return auth.user.name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

function handleLogout() {
  auth.logout()
}
</script>

<template>
  <header class="flex h-16 items-center justify-between border-b border-[#1e293b] bg-[#0a1022] px-6">
    <!-- Left: mobile menu placeholder -->
    <div class="flex items-center gap-3">
      <div class="lg:hidden">
        <!-- mobile hamburger — wired later -->
      </div>
    </div>

    <!-- Right: user + logout -->
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-3">
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#69dca4]/15 text-[10px] font-bold text-[#69dca4]">
          {{ userInitials }}
        </div>
        <div class="hidden sm:block">
          <p class="text-sm font-medium text-[#dae2fd] leading-tight">{{ auth.user?.name }}</p>
          <p class="text-xs text-[#64748b] leading-tight">{{ auth.user?.role_name }}</p>
        </div>
      </div>

      <button
        class="admin-btn admin-btn-ghost admin-btn-sm"
        @click="handleLogout"
        aria-label="Cerrar sesión"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" />
        </svg>
        <span class="hidden sm:inline">Salir</span>
      </button>
    </div>
  </header>
</template>
