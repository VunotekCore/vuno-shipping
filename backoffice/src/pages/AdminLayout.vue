<script setup lang="ts">
import AdminSidebar from '../components/layout/AdminSidebar.vue'
import AdminHeader from '../components/layout/AdminHeader.vue'
import AppToast from '../components/ui/AppToast.vue'
</script>

<template>
  <div class="flex min-h-screen bg-[#0a1022]">
    <AdminSidebar />

    <div class="flex flex-1 flex-col pl-60 transition-all duration-300 max-lg:pl-[60px]">
      <AdminHeader />

      <main class="flex-1 overflow-y-auto p-6">
        <RouterView />
      </main>
    </div>

    <AppToast />
  </div>
</template>
