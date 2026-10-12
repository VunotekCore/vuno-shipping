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
    invoices: 'Facturas',
    'invoice-detail': 'Detalle de factura',
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
  <header class="admin-appbar sticky top-0 z-30 flex h-16 shrink-0 items-center gap-3 px-4 sm:px-6">
    <div class="flex min-w-0 items-center gap-3">
      <button
        class="flex items-center justify-center rounded-lg p-1.5 text-muted transition-colors hover:bg-surface-raised hover:text-content lg:hidden"
        aria-label="Abrir menu"
        @click="emit('toggle-sidebar')"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>
      <span class="hidden h-6 w-px bg-line lg:block" />
      <div class="min-w-0">
        <div class="admin-breadcrumb hidden lg:flex">
          <span>Vuno Shipping</span>
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6" /></svg>
          <span class="admin-breadcrumb-current">{{ pageTitle }}</span>
        </div>
        <h1 class="truncate text-base font-semibold text-content-strong lg:text-lg">{{ pageTitle }}</h1>
      </div>
    </div>

    <div class="ml-auto flex items-center gap-2" data-notif-dropdown>
      <button class="admin-appbar-search hidden sm:flex" type="button" aria-label="Buscar">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        <span>Buscar…</span>
        <span class="admin-kbd ml-2">⌘K</span>
      </button>
      <div class="relative">
        <button
          class="relative flex items-center justify-center rounded-lg p-2 text-muted transition-colors hover:bg-surface-raised hover:text-content"
          aria-label="Notificaciones"
          @click.stop="toggleDropdown"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 01-3.46 0" />
          </svg>
          <span
            v-if="notifStore.unreadCount > 0"
            class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[9px] font-bold text-white"
          >
            {{ notifStore.unreadCount > 99 ? '99+' : notifStore.unreadCount }}
          </span>
        </button>

        <transition enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition ease-in duration-100" leave-to-class="opacity-0 translate-y-1">
          <div v-if="showDropdown" class="absolute right-0 top-full mt-2 w-80 rounded-xl border border-line bg-surface shadow-lg shadow-primary-deep/10 overflow-hidden">
            <div class="px-4 py-3 border-b border-line">
              <p class="text-sm font-semibold text-content">Notificaciones</p>
            </div>

            <div v-if="notifStore.notifications.length === 0" class="px-4 py-8 text-center text-sm text-subtle">
              Sin notificaciones
            </div>

            <div
              v-for="n in notifStore.notifications"
              :key="n.id"
              :class="['px-4 py-3 border-b border-line cursor-pointer transition-colors hover:bg-surface-raised', !n.is_read ? 'bg-surface-raised' : '']"
              @click="markRead(n)"
            >
              <p class="text-sm text-content">{{ n.title }}</p>
              <p class="text-xs text-subtle mt-0.5">{{ n.message }}</p>
              <div class="flex items-center gap-2 mt-1">
                <span v-if="n.tracking_number" class="text-xs font-mono text-primary">{{ n.tracking_number }}</span>
                <span class="text-xs text-muted">{{ timeAgo(n.created_at) }}</span>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </header>
</template>
