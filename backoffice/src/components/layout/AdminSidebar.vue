<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const collapsed = ref(false)

interface NavItem {
  label: string
  route: string
  icon: string
  permission?: string
}

const navItems = computed<NavItem[]>(() => {
  const items: NavItem[] = [
    {
      label: 'Dashboard',
      route: '/',
      icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
      label: 'Usuarios',
      route: '/users',
      icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
      permission: 'user.read',
    },
    {
      label: 'Roles',
      route: '/roles',
      icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
      permission: 'role.read',
    },
    {
      label: 'Permisos',
      route: '/permissions',
      icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
      permission: 'permission.read',
    },
  ]

  return items.filter((item) => {
    if (!item.permission) return true
    const [module, action] = item.permission.split('.')
    return auth.hasPermission(module, action)
  })
})

function isActive(itemRoute: string): boolean {
  if (itemRoute === '/') return route.path === '/'
  return route.path.startsWith(itemRoute)
}

function navigate(path: string) {
  router.push(path)
}
</script>

<template>
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-40 flex flex-col border-r border-[#1e293b] bg-[#0b1326] transition-all duration-300',
      collapsed ? 'w-[60px]' : 'w-60'
    ]"
  >
    <!-- Logo -->
    <div class="flex h-16 items-center gap-2 border-b border-[#1e293b] px-4">
      <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#69dca4]/15 text-[#69dca4]">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" /><circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
        </svg>
      </div>
      <Transition name="fade">
        <span v-if="!collapsed" class="text-sm font-semibold text-[#dae2fd] tracking-wide">
          Shipping
        </span>
      </Transition>
    </div>

    <!-- Nav -->
    <nav class="sidebar-scroll flex-1 overflow-y-auto py-3">
      <div class="space-y-1 px-2">
        <button
          v-for="item in navItems"
          :key="item.route"
          :class="[
            'group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150',
            isActive(item.route)
              ? 'sidebar-link-active'
              : 'text-[#94a3b8] hover:bg-white/[0.04] hover:text-[#dae2fd]'
          ]"
          @click="navigate(item.route)"
        >
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            :class="[
              'shrink-0 transition-colors',
              isActive(item.route) ? 'text-[#69dca4]' : 'text-[#64748b] group-hover:text-[#94a3b8]'
            ]"
          >
            <path :d="item.icon" />
          </svg>
          <Transition name="fade">
            <span v-if="!collapsed">{{ item.label }}</span>
          </Transition>
        </button>
      </div>
    </nav>

    <!-- Toggle -->
    <div class="border-t border-[#1e293b] p-2">
      <button
        class="flex w-full items-center justify-center rounded-lg p-2 text-[#64748b] transition-colors hover:bg-white/[0.04] hover:text-[#dae2fd]"
        @click="collapsed = !collapsed"
        :aria-label="collapsed ? 'Expandir sidebar' : 'Colapsar sidebar'"
      >
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          :class="['transition-transform duration-300', collapsed && 'rotate-180']"
        >
          <path d="M11 19l-7-7 7-7M18 19l-7-7 7-7" />
        </svg>
      </button>
    </div>
  </aside>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
