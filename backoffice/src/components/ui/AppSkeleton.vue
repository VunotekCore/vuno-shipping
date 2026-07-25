<script setup lang="ts">
defineProps<{
  rows?: number
  type?: 'text' | 'title'
}>()
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="i in (rows || 3)"
      :key="i"
      :class="[
        'animate-pulse rounded bg-[#1e293b]',
        type === 'title' ? 'h-6 w-32' : 'h-4 w-full'
      ]"
      :style="{ animationDelay: `${i * 100}ms` }"
    />
  </div>
</template>
