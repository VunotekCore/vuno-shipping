<script setup lang="ts">
defineProps<{
  visible: boolean
  title?: string
  size?: 'sm' | 'md' | 'lg'
}>()

defineEmits<{
  close: []
}>()

function onBackdrop(e: MouseEvent) {
  if ((e.target as HTMLElement).classList.contains('admin-modal-overlay')) {
    ;(e.currentTarget as HTMLElement).dispatchEvent(new Event('close'))
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="visible"
        class="admin-modal-overlay fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        @click="onBackdrop"
      >
        <div
          :class="[
            'w-full rounded-xl border border-[#1e293b] bg-[#111d2e] shadow-2xl max-h-[90vh] flex flex-col',
            size === 'sm' ? 'max-w-sm' : size === 'lg' ? 'max-w-2xl' : 'max-w-lg'
          ]"
          @click.stop
        >
          <!-- Header -->
          <div v-if="title || $slots.header" class="flex items-center justify-between border-b border-[#1e293b] px-6 py-4">
            <slot name="header">
              <h2 class="text-lg font-semibold text-[#dae2fd]">{{ title }}</h2>
            </slot>
            <button
              class="rounded-lg p-1.5 text-[#64748b] transition-colors hover:bg-white/[0.06] hover:text-[#dae2fd]"
              @click="$emit('close')"
              aria-label="Cerrar"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-6 py-4">
            <slot />
          </div>

          <!-- Footer -->
          <div v-if="$slots.footer" class="border-t border-[#1e293b] px-6 py-4">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active { animation: modalIn 0.2s ease-out; }
.modal-leave-active { animation: modalOut 0.15s ease-in; }
@keyframes modalIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes modalOut { from { opacity: 1; } to { opacity: 0; } }
</style>
