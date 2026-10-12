<script setup lang="ts">
import { useToast } from '../../composables/useToast'

const { toasts, removeToast } = useToast()

function toastIcon(type: string) {
  if (type === 'success') return 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z'
  if (type === 'error') return 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'
  return 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z'
}

function toastColor(type: string) {
  if (type === 'success') return 'border-primary/20 bg-primary/10 text-primary'
  if (type === 'error') return 'border-danger/20 bg-danger/10 text-danger'
  return 'border-content/12 bg-content/6 text-content'
}
</script>

<template>
  <div class="fixed top-4 right-4 z-[1300] flex flex-col gap-2 max-w-sm">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="['admin-toast flex items-center gap-3 px-4 py-3 rounded-lg border text-sm shadow-lg', toastColor(toast.type)]"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
          <path :d="toastIcon(toast.type)" />
        </svg>
        <span class="flex-1">{{ toast.message }}</span>
        <button @click="removeToast(toast.id)" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity" aria-label="Cerrar">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active { animation: toastIn 0.3s ease; }
.toast-leave-active { animation: toastOut 0.2s ease; }
@keyframes toastIn { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: translateX(0); } }
@keyframes toastOut { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(24px); } }
</style>
