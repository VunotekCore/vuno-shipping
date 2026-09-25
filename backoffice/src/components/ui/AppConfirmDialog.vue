<script setup lang="ts">
import { watch, nextTick, ref } from 'vue'

defineProps<{
  visible: boolean
  title?: string
  message?: string
  confirmText?: string
  cancelText?: string
  variant?: 'danger' | 'warning' | 'info'
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

const dialogRef = ref<HTMLElement | null>(null)

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    emit('cancel')
    return
  }
  if (e.key === 'Tab' && dialogRef.value) {
    const focusable = dialogRef.value.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )
    if (focusable.length === 0) return
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (e.shiftKey) {
      if (document.activeElement === first) {
        e.preventDefault()
        last.focus()
      }
    } else {
      if (document.activeElement === last) {
        e.preventDefault()
        first.focus()
      }
    }
  }
}

watch(() => dialogRef.value, (el) => {
  if (el) {
    nextTick(() => {
      const cancelBtn = el.querySelector<HTMLElement>('.admin-btn-ghost')
      cancelBtn?.focus()
    })
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="confirm-modal">
      <div
        v-if="visible"
        class="admin-confirm-overlay fixed inset-0 z-[1200] flex items-center justify-center p-4"
        @click.self="$emit('cancel')"
        @keydown="onKeydown"
      >
        <div
          ref="dialogRef"
          role="alertdialog"
          aria-modal="true"
          :aria-label="title"
          :aria-describedby="message ? 'confirm-message' : undefined"
          class="admin-confirm-card w-full max-w-md rounded-xl border border-[#1e293b] bg-[#111d2e] p-6 shadow-2xl"
        >
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
              <p v-if="message" id="confirm-message" class="mt-1.5 text-sm text-[#94a3b8] leading-relaxed">{{ message }}</p>
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
.confirm-modal-enter-active { animation: confirmModalIn 0.2s ease; }
.confirm-modal-leave-active { animation: confirmModalOut 0.15s ease; }
@keyframes confirmModalIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes confirmModalOut { from { opacity: 1; } to { opacity: 0; } }
</style>
