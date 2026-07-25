<script setup lang="ts">
defineProps<{
  visible: boolean
  title?: string
  message?: string
  confirmText?: string
  cancelText?: string
  variant?: 'danger' | 'warning' | 'info'
}>()

defineEmits<{
  confirm: []
  cancel: []
}>()
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="visible" class="admin-confirm-overlay fixed inset-0 z-[90] flex items-center justify-center p-4" @click.self="$emit('cancel')">
        <div class="admin-confirm-card w-full max-w-md rounded-xl border border-[#1e293b] bg-[#111d2e] p-6 shadow-2xl">
          <div class="flex items-start gap-4">
            <div
              :class="[
                'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                variant === 'danger' ? 'bg-[#DC2626]/15 text-[#DC2626]' :
                variant === 'warning' ? 'bg-[#f59e0b]/15 text-[#f59e0b]' :
                'bg-[#00A8FF]/15 text-[#00A8FF]'
              ]"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
              </svg>
            </div>
            <div class="flex-1">
              <h3 v-if="title" class="text-lg font-semibold text-[#dae2fd]">{{ title }}</h3>
              <p v-if="message" class="mt-1.5 text-sm text-[#94a3b8] leading-relaxed">{{ message }}</p>
            </div>
          </div>
          <div class="mt-6 flex justify-end gap-3">
            <button
              class="admin-btn admin-btn-ghost px-4 py-2 text-sm"
              @click="$emit('cancel')"
            >
              {{ cancelText || 'Cancelar' }}
            </button>
            <button
              :class="[
                'admin-btn px-4 py-2 text-sm',
                variant === 'danger' ? 'admin-btn-danger' : 'admin-btn-primary'
              ]"
              @click="$emit('confirm')"
            >
              {{ confirmText || 'Confirmar' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active { animation: modalIn 0.2s ease; }
.modal-leave-active { animation: modalOut 0.15s ease; }
@keyframes modalIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes modalOut { from { opacity: 1; } to { opacity: 0; } }
</style>
