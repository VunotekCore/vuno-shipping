<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useNotificationStore } from '../../stores/notification'

const emit = defineEmits<{
  (e: 'toggle-sidebar'): void
}>()

const route = useRoute()
const notifStore = useNotificationStore()
const showDropdown = ref(false)

const pageTitle = computed(() => {
  const titles: Record<string, string> = {
    dashboard: 'Dashboard',
    users: 'Usuarios',
    roles: 'Roles',
    permissions: 'Permisos',
    company: 'Empresa',
    areas: 'Áreas',
    puestos: 'Puestos',
    empleados: 'Empleados',
    'document-sequences': 'Secuencias',
    admision: 'Admisión',
    scan: 'Escaner',
    packages: 'Paquetes',
    'package-detail': 'Detalle de paquete',
    clients: 'Clientes',
    vehicles: 'Vehículos',
    zones: 'Zonas',
    pricing: 'Precios',
    'shipping-settings': 'Config. Envíos',
  }
  return titles[route.name as string] ?? 'Dashboard'
})

function toggleDropdown() {
  showDropdown.value = !showDropdown.value
  if (showDropdown.value) {
    notifStore.fetch()
  }
}

function closeDropdown() {
  showDropdown.value = false
}

function handleClickOutside(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('[data-notif-dropdown]')) {
    closeDropdown()
  }
}

function markRead(n: { id: number; is_read: number }) {
  if (!n.is_read) {
    notifStore.markRead(n.id)
  }
}

function timeAgo(dateStr: string): string {
  const now = Date.now()
  const date = new Date(dateStr.replace(' ', 'T') + 'Z').getTime()
  const diff = Math.floor((now - date) / 1000)
  if (diff < 60) return 'ahora'
  if (diff < 3600) return `${Math.floor(diff / 60)}m`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h`
  return `${Math.floor(diff / 86400)}d`
}

onMounted(() => {
  notifStore.startPolling()
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  notifStore.stopPolling()
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-4 border-b border-[#1e293b] bg-[#111d2e]/80 backdrop-blur-md px-6">
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

    <div class="ml-auto flex items-center gap-2" data-notif-dropdown>
      <div class="relative">
        <button
          class="relative flex items-center justify-center rounded-lg p-2 text-[#94a3b8] transition-colors hover:bg-white/[0.06] hover:text-[#dae2fd]"
          aria-label="Notificaciones"
          @click.stop="toggleDropdown"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 01-3.46 0" />
          </svg>
          <span
            v-if="notifStore.unreadCount > 0"
            class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[#DC2626] px-1 text-[9px] font-bold text-white"
          >
            {{ notifStore.unreadCount > 99 ? '99+' : notifStore.unreadCount }}
          </span>
        </button>

        <transition enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition ease-in duration-100" leave-to-class="opacity-0 translate-y-1">
          <div v-if="showDropdown" class="absolute right-0 top-full mt-2 w-80 rounded-xl border border-[#1e293b] bg-[#0f1729] shadow-xl shadow-black/40 overflow-hidden">
            <div class="px-4 py-3 border-b border-[#1e293b]">
              <p class="text-sm font-semibold text-[#dae2fd]">Notificaciones</p>
            </div>

            <div v-if="notifStore.notifications.length === 0" class="px-4 py-8 text-center text-sm text-[#64748b]">
              Sin notificaciones
            </div>

            <div
              v-for="n in notifStore.notifications"
              :key="n.id"
              :class="['px-4 py-3 border-b border-[#1e293b] cursor-pointer transition-colors hover:bg-white/[0.04]', !n.is_read ? 'bg-white/[0.02]' : '']"
              @click="markRead(n)"
            >
              <p class="text-sm text-[#dae2fd]">{{ n.title }}</p>
              <p class="text-xs text-[#64748b] mt-0.5">{{ n.message }}</p>
              <div class="flex items-center gap-2 mt-1">
                <span v-if="n.tracking_number" class="text-xs font-mono text-[#69dca4]">{{ n.tracking_number }}</span>
                <span class="text-xs text-[#475569]">{{ timeAgo(n.created_at) }}</span>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </header>
</template>
