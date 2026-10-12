<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, useAttrs } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import ProfileModal from '../ui/ProfileModal.vue'

defineOptions({ inheritAttrs: false })

const attrs = useAttrs()
const emit = defineEmits<{
  (e: 'close'): void
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const showUserMenu = ref(false)
const showProfileModal = ref(false)
const menuRef = ref<HTMLElement | null>(null)

const userInitials = computed(() => {
  if (!auth.user?.name) return '?'
  return auth.user.name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

function onClickOutside(e: MouseEvent) {
  if (menuRef.value && !menuRef.value.contains(e.target as Node)) {
    showUserMenu.value = false
  }
}

function onKeyDown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    showUserMenu.value = false
  }
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
  document.addEventListener('keydown', onKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
  document.removeEventListener('keydown', onKeyDown)
})

// --- Navigation ---

interface NavItem {
  label: string
  route: string
  icon: string
  /** Uno o varios permisos `modulo.accion`; el item se muestra con cualquiera */
  permission?: string | string[]
}

interface NavGroup {
  key: string
  label: string
  icon: string
  items: NavItem[]
}

const dashboardItem: NavItem = {
  label: 'Dashboard',
  route: '/',
  icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
}

function filterByPermission(items: NavItem[]): NavItem[] {
  return items.filter((item) => {
    if (!item.permission) return true
    const required = Array.isArray(item.permission) ? item.permission : [item.permission]
    return required.some((entry) => {
      const [module, action] = entry.split('.')
      return auth.hasPermission(module, action)
    })
  })
}

const navGroups = computed<NavGroup[]>(() => {
  const groups: NavGroup[] = [
    {
      key: 'operaciones',
      label: 'Operaciones',
      icon: 'M22 8.35V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8.35A2 2 0 0 1 3.26 6.5l8-3.2a2 2 0 0 1 1.48 0l8 3.2A2 2 0 0 1 22 8.35ZM6 18h12M6 14h12M6 10h12',
      items: [
        { label: 'Admisión de Envíos', route: '/admision', icon: 'M12 4v16m8-8H4', permission: 'package.create' },
        { label: 'Escáner', route: '/scan', icon: 'M3 9V7a2 2 0 012-2h2M3 15v2a2 2 0 002 2h2M21 9V7a2 2 0 00-2-2h-2M21 15v2a2 2 0 01-2 2h-2M7 12h.01M12 12h.01M17 12h.01', permission: 'package.read' },
        { label: 'Paquetes', route: '/packages', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', permission: 'package.read' },
        { label: 'Embarques', route: '/embarques', icon: 'M22 2 11 13M22 2l-7 20-4-9-9-4Z', permission: 'shipment.read' },
      ],
    },
    {
      key: 'despacho',
      label: 'Despacho',
      icon: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM16.24 7.76l-2.12 6.36-6.36 2.12 2.12-6.36 6.36-2.12z',
      items: [
        { label: 'Rutas maestras', route: '/routes', icon: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7', permission: 'delivery_route.read' },
        { label: 'Despachos', route: '/runs', icon: 'M8 17h.01M16 17h.01M3 11l1.5-5A2 2 0 016.4 4h11.2a2 2 0 011.9 1.4L21 11M3 11h18M3 11v6a1 1 0 001 1h1m16-7v6a1 1 0 01-1 1h-1M3 11v-1m18 0v1', permission: 'delivery_route.read' },
        { label: 'Vehículos', route: '/vehicles', icon: 'M6 17H5a2 2 0 0 1-2-2v-3l2-5a2 2 0 0 1 1.8-1.2h8.4A2 2 0 0 1 17 7l2 5v3a2 2 0 0 1-2 2h-1M4 17a2 2 0 1 0 4 0 2 2 0 1 0-4 0M16 17a2 2 0 1 0 4 0 2 2 0 1 0-4 0', permission: 'vehicle.read' },
      ],
    },
    {
      key: 'comercial',
      label: 'Comercial',
      icon: 'M8 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM19 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6',
      items: [
        { label: 'Cotizaciones', route: '/cotizaciones', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', permission: 'quote.read' },
        { label: 'Facturas', route: '/facturas', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', permission: 'invoice.read' },
        { label: 'Clientes', route: '/clients', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', permission: 'client.read' },
      ],
    },
    {
      key: 'configuracion',
      label: 'Configuración',
      icon: 'M21 4H14M10 4H3M21 12H12M8 12H3M21 20H16M12 20H3M14 2v4M8 10v4M16 18v4',
      items: [
        { label: 'Zonas', route: '/zones', icon: 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0zM12 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', permission: 'zone.read' },
        { label: 'Reglas de pricing', route: '/pricing', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', permission: 'pricing_rule.read' },
        { label: 'Servicios y opciones', route: '/settings/shipping', icon: 'M5 12h14M12 5l7 7-7 7', permission: 'shipping_service.read' },
        { label: 'Almacenaje', route: '/almacenaje', icon: 'M3 21V8l9-5 9 5v13M9 21v-8h6v8M3 21h18', permission: ['warehouse.read', 'storage_location.read', 'storage_category.read'] },
        { label: 'Empresa', route: '/company', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', permission: ['company.read', 'billing_config.read'] },
        { label: 'Secuencias', route: '/document-sequences', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', permission: 'document_sequence.read' },
      ],
    },
    {
      key: 'organizacion',
      label: 'Organización',
      icon: 'M3 21h18M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16M9 7h1m-1 4h1m4-4h1m-1 4h1M9 21v-4h6v4',
      items: [
        { label: 'Áreas', route: '/areas', icon: 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z', permission: 'area.read' },
        { label: 'Puestos', route: '/puestos', icon: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', permission: 'puesto.read' },
        { label: 'Empleados', route: '/empleados', icon: 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z', permission: 'empleado.read' },
      ],
    },
    {
      key: 'seguridad',
      label: 'Seguridad',
      icon: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
      items: [
        { label: 'Usuarios', route: '/users', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', permission: 'user.read' },
        { label: 'Roles', route: '/roles', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', permission: 'role.read' },
        { label: 'Permisos', route: '/permissions', icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', permission: 'permission.read' },
      ],
    },
  ]

  return groups
    .map((g) => ({ ...g, items: filterByPermission(g.items) }))
    .filter((g) => g.items.length > 0)
})

// --- Accordion state ---

const EXPANDED_STORAGE_KEY = 'sidebar_expanded_v2'
const DEFAULT_EXPANDED = ['operaciones', 'despacho']

function loadExpanded(): Set<string> {
  const validKeys = ['operaciones', 'despacho', 'comercial', 'configuracion', 'organizacion', 'seguridad']
  try {
    const raw = localStorage.getItem(EXPANDED_STORAGE_KEY)
    if (raw) {
      const saved = (JSON.parse(raw) as string[]).filter((k) => validKeys.includes(k))
      if (saved.length > 0) return new Set(saved)
    }
  } catch {}
  return new Set(DEFAULT_EXPANDED)
}

const expandedGroups = ref<Set<string>>(loadExpanded())

function isExpanded(key: string): boolean {
  return expandedGroups.value.has(key)
}

function toggleGroup(key: string) {
  if (expandedGroups.value.has(key)) {
    expandedGroups.value.delete(key)
  } else {
    expandedGroups.value.add(key)
  }
  localStorage.setItem(EXPANDED_STORAGE_KEY, JSON.stringify([...expandedGroups.value]))
}

// --- Navigation ---

function isActive(itemRoute: string): boolean {
  if (itemRoute === '/') return route.path === '/'
  return route.path.startsWith(itemRoute)
}

function isGroupActive(items: NavItem[]): boolean {
  return items.some((item) => isActive(item.route))
}

function navigate(path: string) {
  router.push(path)
  emit('close')
}

function openUserMenu() {
  showUserMenu.value = !showUserMenu.value
}

function handleLogout() {
  showUserMenu.value = false
  auth.logout()
}
</script>

<template>
  <aside
    v-bind="attrs"
    class="sidebar-shell fixed inset-y-0 left-0 z-40 flex w-60 flex-col transition-transform duration-300 max-lg:-translate-x-full max-lg:shadow-2xl"
  >
    <!-- Logo -->
    <div class="sidebar-brand flex h-16 shrink-0 items-center gap-2 px-4">
      <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white ring-1 ring-white/15">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" /><circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
        </svg>
      </div>
      <span class="text-sm font-bold text-white tracking-tight">Vuno Shipping</span>
    </div>

    <!-- Nav -->
    <nav class="sidebar-scroll flex-1 overflow-y-auto py-3">
      <div class="px-2 space-y-0.5">
        <!-- Dashboard (top-level) -->
        <button
          :class="[
            'group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150',
            isActive(dashboardItem.route) ? 'sidebar-link-active' : 'sidebar-link'
          ]"
          @click="navigate(dashboardItem.route)"
        >
          <svg
            width="20" height="20" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
            class="shrink-0"
          >
            <path :d="dashboardItem.icon" />
          </svg>
          <span>{{ dashboardItem.label }}</span>
        </button>

        <!-- Groups -->
        <div v-for="group in navGroups" :key="group.key" class="mt-2 first:mt-1">
          <!-- Group header -->
          <button
            class="sidebar-group-header"
            :class="{ 'sidebar-group-active': isGroupActive(group.items) && !isExpanded(group.key) }"
            @click="toggleGroup(group.key)"
          >
            <span class="flex items-center gap-3">
              <svg
                v-if="group.icon"
                width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                class="shrink-0"
              >
                <path :d="group.icon" />
              </svg>
              <span>{{ group.label }}</span>
            </span>
            <svg
              width="14" height="14" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              class="shrink-0 transition-transform duration-200"
              :class="isExpanded(group.key) ? 'rotate-90' : ''"
            >
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </button>

          <!-- Group items -->
          <div v-show="isExpanded(group.key)" class="sidebar-group-items">
            <template v-if="group.items.length > 0">
              <button
                v-for="item in group.items"
                :key="item.route"
                :class="[
                  'group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition-all duration-150',
                  isActive(item.route) ? 'sidebar-link-active' : 'sidebar-link'
                ]"
                @click="navigate(item.route)"
              >
                <svg
                  width="18" height="18" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                  class="shrink-0"
                >
                  <path :d="item.icon" />
                </svg>
                <span>{{ item.label }}</span>
              </button>
            </template>
            <p v-else class="px-3 py-2 text-xs italic" style="color: rgba(148,163,184,0.6)">Próximamente</p>
          </div>
        </div>
      </div>
    </nav>

    <!-- Bottom: ver sitio web + user -->
    <div class="shrink-0 border-t sidebar-divider p-3 space-y-1">
      <!-- Ver sitio web -->
      <a
        href="/"
        target="_blank"
        rel="noopener noreferrer"
        class="sidebar-link flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
          <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6" /><polyline points="15 3 21 3 21 9" /><line x1="10" y1="14" x2="21" y2="3" />
        </svg>
        <span>Ver sitio web</span>
      </a>

      <!-- User (clickable → popover) -->
      <div ref="menuRef" class="relative">
        <button
          class="sidebar-link flex w-full items-center gap-3 rounded-lg px-3 py-2 transition-colors"
          @click="openUserMenu"
        >
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15 text-[10px] font-bold text-white">
            {{ userInitials }}
          </div>
          <div class="min-w-0 flex-1 text-left">
            <p class="truncate text-sm font-medium text-white leading-tight">{{ auth.user?.name }}</p>
            <p class="truncate text-xs text-white/55 leading-tight">{{ auth.user?.role_name }}</p>
          </div>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 text-white/55" :class="{ 'rotate-90': showUserMenu }">
            <polyline points="9 18 15 12 9 6" />
          </svg>
        </button>

        <!-- Popover menu -->
        <Transition name="pop">
          <div
            v-if="showUserMenu"
            class="absolute bottom-full left-0 right-0 mb-2 rounded-lg border border-line bg-surface shadow-xl z-50"
          >
            <!-- User info -->
            <div class="flex items-center gap-3 border-b border-line px-3 py-3">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[11px] font-bold text-primary">
                {{ userInitials }}
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-content">{{ auth.user?.name }}</p>
                <p class="truncate text-xs text-subtle">{{ auth.user?.email }}</p>
              </div>
            </div>

            <!-- Actions -->
            <div class="p-1.5">
              <!-- Role badge -->
              <div class="flex items-center justify-between rounded-md px-2.5 py-2">
                <span class="text-xs text-subtle">Rol</span>
                <span class="rounded-full bg-primary/15 px-2 py-0.5 text-[10px] font-medium text-primary">{{ auth.user?.role_name }}</span>
              </div>

              <!-- Edit profile -->
              <button
                class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-xs text-muted transition-colors hover:bg-surface-raised hover:text-content"
                @click="showUserMenu = false; showProfileModal = true"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
                <span>Editar perfil</span>
              </button>

              <!-- Logout -->
              <button
                class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-xs text-danger transition-colors hover:bg-danger/10"
                @click="handleLogout"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
                  <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                <span>Cerrar sesion</span>
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </aside>

  <!-- Profile modal -->
  <ProfileModal :visible="showProfileModal" @close="showProfileModal = false" />
</template>

<style scoped>
.pop-enter-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.pop-leave-active {
  transition: opacity 0.1s ease, transform 0.1s ease;
}
.pop-enter-from,
.pop-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
