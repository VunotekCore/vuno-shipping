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
  <div class="flex min-h-screen bg-[#0a1022]">
    <!-- Sidebar (fixed) -->
    <AdminSidebar :class="{ 'max-lg:translate-x-0': sidebarOpen }" @close="closeSidebar" />

    <!-- Overlay (mobile) -->
    <Transition name="fade">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-30 bg-black/50 lg:hidden"
        @click="closeSidebar"
      />
    </Transition>

    <!-- Content -->
    <div class="flex flex-1 flex-col pl-60 transition-all duration-300 max-lg:pl-0">
      <AdminHeader @toggle-sidebar="toggleSidebar" />
      <main class="flex-1 min-h-0 overflow-x-hidden overflow-y-auto p-4 sm:p-6 lg:p-8">
        <RouterView />
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
