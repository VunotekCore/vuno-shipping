<script setup lang="ts">
import { ref } from 'vue'
import AdminSidebar from '../components/layout/AdminSidebar.vue'
import AdminHeader from '../components/layout/AdminHeader.vue'
import AppToast from '../components/ui/AppToast.vue'

const sidebarOpen = ref(false)

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function closeSidebar() {
  sidebarOpen.value = false
}
</script>

<template>
  <div class="relative flex min-h-screen">
    <!-- Atmospheric canvas -->
    <div class="admin-atmosphere" aria-hidden="true" />

    <!-- Sidebar (fixed) -->
    <AdminSidebar :class="{ 'max-lg:translate-x-0': sidebarOpen }" @close="closeSidebar" />

    <!-- Overlay (mobile) -->
    <Transition name="fade">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-30 bg-primary-deep/45 lg:hidden"
        @click="closeSidebar"
      />
    </Transition>

    <!-- Content -->
    <div class="relative z-10 flex flex-1 flex-col pl-60 transition-all duration-300 max-lg:pl-0">
      <AdminHeader @toggle-sidebar="toggleSidebar" />
      <main class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8">
        <div class="mx-auto w-full max-w-[1400px]">
          <RouterView />
        </div>
      </main>
    </div>

    <AppToast />
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
