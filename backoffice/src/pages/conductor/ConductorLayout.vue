<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useBackgroundGeo } from '../../composables/useBackgroundGeo'
import ProfileModal from '../../components/ui/ProfileModal.vue'
import AppToast from '../../components/ui/AppToast.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const sidebarOpen = ref(false)
const showProfile = ref(false)

const geo = useBackgroundGeo()
const trackingActive = geo.tracking
const queueCount = geo.queueCount

const navItems = [
  { label: 'Entregas', path: '/conductor', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { label: 'Ruta', path: '/conductor/ruta', icon: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7' },
  { label: 'Historial', path: '/conductor/historial', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { label: 'Perfil', path: '/conductor/perfil', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
]

function isActive(path: string) {
  if (path === '/conductor') return route.path === '/conductor'
  return route.path.startsWith(path)
}

function navigate(path: string) {
  router.push(path)
  sidebarOpen.value = false
}

function toggleTracking() {
  if (trackingActive.value) {
    geo.stop()
  } else {
    geo.start()
  }
}

onUnmounted(() => {
  geo.stop()
})

function handleLogout() {
  geo.stop()
  auth.logout()
}

const initials = computed(() => {
  if (!auth.user?.name) return '?'
  return auth.user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

function onClickOutside(e: MouseEvent) {
  const el = document.getElementById('conductor-sidebar')
  const btn = document.getElementById('sidebar-toggle')
  if (el && !el.contains(e.target as Node) && btn && !btn.contains(e.target as Node)) {
    sidebarOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
})
</script>

<template>
  <div class="min-h-screen bg-[#0a1022] text-[#dae2fd]">
    <!-- Top bar -->
    <header class="fixed inset-x-0 top-0 z-30 flex h-14 items-center justify-between border-b border-[#1e293b] bg-[#0b1326]/95 px-4 backdrop-blur-sm">
      <div class="flex items-center gap-3">
        <button id="sidebar-toggle" class="flex h-9 w-9 items-center justify-center rounded-lg text-[#94a3b8] hover:bg-white/[0.04] hover:text-[#dae2fd]" @click="sidebarOpen = !sidebarOpen">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </button>
        <span class="text-sm font-semibold tracking-wide">Conductor</span>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
          :class="trackingActive ? 'bg-[#22c55e]/15 text-[#22c55e]' : 'bg-[#1e293b] text-[#94a3b8]'"
          @click="toggleTracking"
        >
          <span class="relative flex h-2 w-2">
            <span v-if="trackingActive" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#22c55e] opacity-75" />
            <span class="relative inline-flex h-2 w-2 rounded-full" :class="trackingActive ? 'bg-[#22c55e]' : 'bg-[#64748b]'" />
          </span>
          {{ trackingActive ? 'GPS activo' : 'GPS' }}
        </button>
        <button class="flex h-8 w-8 items-center justify-center rounded-full bg-[#69dca4]/15 text-[10px] font-bold text-[#69dca4]" @click="showProfile = true">
          {{ initials }}
        </button>
      </div>
    </header>

    <!-- Sidebar -->
    <aside
      id="conductor-sidebar"
      :class="[
        'fixed inset-y-0 left-0 z-40 flex w-60 flex-col border-r border-[#1e293b] bg-[#0b1326] transition-transform duration-300',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <div class="flex h-14 items-center gap-2 border-b border-[#1e293b] px-4">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#69dca4]/15 text-[#69dca4]">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
        </div>
        <span class="text-sm font-semibold tracking-wide">Conductor</span>
      </div>

      <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-1">
        <button
          v-for="item in navItems"
          :key="item.path"
          :class="[
            'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all',
            isActive(item.path)
              ? 'bg-[#69dca4]/10 text-[#69dca4]'
              : 'text-[#94a3b8] hover:bg-white/[0.04] hover:text-[#dae2fd]'
          ]"
          @click="navigate(item.path)"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
            <path :d="item.icon" />
          </svg>
          {{ item.label }}
        </button>
      </nav>

      <div class="border-t border-[#1e293b] p-3">
        <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-[#DC2626] transition-colors hover:bg-[#DC2626]/10" @click="handleLogout">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
          Cerrar sesión
        </button>
      </div>
    </aside>

    <!-- Overlay -->
    <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

    <!-- Main content -->
    <main class="pt-14">
      <div class="mx-auto max-w-5xl p-4 sm:p-6">
        <router-view />
      </div>
    </main>
  </div>

  <!-- GPS indicator bar -->
  <div
    v-if="trackingActive"
    class="fixed bottom-0 inset-x-0 z-20 flex items-center justify-center gap-2 py-1.5 text-[10px]"
    :class="queueCount > 0 ? 'bg-[#eab308]/10 border-t border-[#eab308]/20 text-[#eab308]' : 'bg-[#22c55e]/10 border-t border-[#22c55e]/20 text-[#22c55e]'"
  >
    <template v-if="queueCount > 0">
      <span class="relative flex h-1.5 w-1.5">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#eab308] opacity-75" />
        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-[#eab308]" />
      </span>
      Sin conexión — {{ queueCount }} ubicaciones en cola, se enviarán al recuperar la red
    </template>
    <template v-else>
      <span class="relative flex h-1.5 w-1.5">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#22c55e] opacity-75" />
        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-[#22c55e]" />
      </span>
      Geolocalización activa
    </template>
  </div>

  <ProfileModal :visible="showProfile" @close="showProfile = false" />
  <AppToast />
</template>
