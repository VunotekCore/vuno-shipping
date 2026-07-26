<script setup lang="ts">
export interface TableColumn {
  key: string
  label: string
  width?: string
  align?: 'left' | 'center' | 'right'
  sortable?: boolean
}

defineProps<{
  columns: TableColumn[]
  loading?: boolean
  emptyTitle?: string
  emptyDesc?: string
  isEmpty?: boolean
}>()
</script>

<template>
  <div class="overflow-x-auto">
    <table class="admin-table">
      <thead>
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            :style="{ width: col.width }"
            :class="[col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : '']"
          >
            {{ col.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <tr v-for="i in 5" :key="i">
            <td v-for="col in columns" :key="col.key">
              <div class="h-4 w-3/4 animate-pulse rounded bg-[#1e293b]" />
            </td>
          </tr>
        </template>
        <template v-else>
          <slot />
        </template>
      </tbody>
    </table>

    <div v-if="!loading && (isEmpty ?? $slots['empty-row'] === undefined)" class="admin-empty-state py-12">
      <slot name="empty">
        <svg class="admin-empty-state-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
          <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <p class="admin-empty-state-title">{{ emptyTitle || 'Sin resultados' }}</p>
        <p class="admin-empty-state-desc">{{ emptyDesc || 'No hay datos para mostrar.' }}</p>
      </slot>
    </div>
  </div>
</template>
