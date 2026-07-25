<script setup lang="ts">
defineProps<{
  currentPage: number
  totalPages: number
}>()

defineEmits<{
  'update:currentPage': [page: number]
}>()
</script>

<template>
  <div v-if="totalPages > 1" class="flex items-center justify-center gap-1 pt-4">
    <button
      class="admin-btn admin-btn-ghost admin-btn-sm"
      :disabled="currentPage <= 1"
      @click="$emit('update:currentPage', currentPage - 1)"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 19l-7-7 7-7"/></svg>
    </button>

    <template v-for="page in totalPages" :key="page">
      <button
        v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
        :class="[
          'admin-btn admin-btn-sm min-w-[32px]',
          page === currentPage ? 'admin-btn-primary' : 'admin-btn-ghost'
        ]"
        @click="$emit('update:currentPage', page)"
      >
        {{ page }}
      </button>
      <span
        v-else-if="page === currentPage - 2 || page === currentPage + 2"
        class="px-1 text-[#64748b]"
      >
        &hellip;
      </span>
    </template>

    <button
      class="admin-btn admin-btn-ghost admin-btn-sm"
      :disabled="currentPage >= totalPages"
      @click="$emit('update:currentPage', currentPage + 1)"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg>
    </button>
  </div>
</template>
