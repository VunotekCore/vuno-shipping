<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  page: number
  pages: number
  total: number
  limit: number
}>()

const emit = defineEmits<{
  'update:page': [page: number]
  'update:limit': [limit: number]
}>()

const limitOptions = [10, 20, 50, 100]

const showingFrom = computed(() => {
  if (props.total === 0) return 0
  return (props.page - 1) * props.limit + 1
})

const showingTo = computed(() => {
  const to = props.page * props.limit
  return to > props.total ? props.total : to
})

const pageNumbers = computed(() => {
  const total = props.pages
  const current = props.page
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }
  const pages: (number | 'dots')[] = []
  pages.push(1)
  if (current > 3) {
    pages.push('dots')
  }
  const start = Math.max(2, current - 1)
  const end = Math.min(total - 1, current + 1)
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  if (current < total - 2) {
    pages.push('dots')
  }
  pages.push(total)
  return pages
})

function goToPage(n: number) {
  if (n >= 1 && n <= props.pages && n !== props.page) {
    emit('update:page', n)
  }
}

function setLimit(e: Event) {
  const val = Number((e.target as HTMLSelectElement).value)
  emit('update:limit', val)
}
</script>

<template>
  <div v-if="total > 0" class="admin-pagination">
    <div class="admin-pagination-info">
      <span class="text-sm text-[#94a3b8]">
        Mostrando {{ showingFrom }}–{{ showingTo }} de {{ total }}
      </span>
      <select :value="limit" class="admin-pagination-select" @change="setLimit">
        <option v-for="opt in limitOptions" :key="opt" :value="opt">{{ opt }} por página</option>
      </select>
    </div>

    <div class="admin-pagination-buttons">
      <button
        class="admin-pagination-btn"
        :disabled="page === 1"
        @click="goToPage(1)"
        title="Primera"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/></svg>
      </button>
      <button
        class="admin-pagination-btn"
        :disabled="page === 1"
        @click="goToPage(page - 1)"
        title="Anterior"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
      </button>

      <template v-for="(p, idx) in pageNumbers" :key="idx">
        <span v-if="p === 'dots'" class="admin-pagination-dots">…</span>
        <button
          v-else
          :class="['admin-pagination-btn', 'admin-pagination-num', { active: p === page }]"
          @click="goToPage(p as number)"
        >
          {{ p }}
        </button>
      </template>

      <button
        class="admin-pagination-btn"
        :disabled="page === pages"
        @click="goToPage(page + 1)"
        title="Siguiente"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
      </button>
      <button
        class="admin-pagination-btn"
        :disabled="page === pages"
        @click="goToPage(pages)"
        title="Última"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 17l5-5-5-5M6 17l5-5-5-5"/></svg>
      </button>
    </div>
  </div>
</template>
