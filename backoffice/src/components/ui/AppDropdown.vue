<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: string | number | null
  options: Array<{ value: string | number | null; label: string }>
  placeholder?: string
  disabled?: boolean
  error?: boolean | string
}>(), {
  placeholder: 'Seleccionar...',
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null]
}>()

const isOpen = ref(false)
const triggerRef = ref<HTMLElement | null>(null)
const listRef = ref<HTMLElement | null>(null)
const highlightedIndex = ref(-1)

const selectedLabel = computed(() => {
  const opt = props.options.find(o => o.value === props.modelValue)
  return opt ? opt.label : ''
})

const filteredOptions = computed(() => props.options)

function toggle() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    highlightedIndex.value = filteredOptions.value.findIndex(o => o.value === props.modelValue)
    nextTick(() => {
      const highlighted = listRef.value?.children[highlightedIndex.value] as HTMLElement
      highlighted?.scrollIntoView({ block: 'nearest' })
    })
  }
}

function select(value: string | number | null) {
  emit('update:modelValue', value)
  isOpen.value = false
  highlightedIndex.value = -1
  triggerRef.value?.focus()
}

function onKeydown(e: KeyboardEvent) {
  if (props.disabled) return

  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault()
    if (isOpen.value && highlightedIndex.value >= 0) {
      select(filteredOptions.value[highlightedIndex.value].value)
    } else {
      toggle()
    }
    return
  }

  if (e.key === 'Escape') {
    isOpen.value = false
    triggerRef.value?.focus()
    return
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (!isOpen.value) { toggle(); return }
    highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredOptions.value.length - 1)
    scrollToHighlighted()
  }

  if (e.key === 'ArrowUp') {
    e.preventDefault()
    if (!isOpen.value) { toggle(); return }
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
    scrollToHighlighted()
  }
}

function scrollToHighlighted() {
  nextTick(() => {
    const el = listRef.value?.children[highlightedIndex.value] as HTMLElement
    el?.scrollIntoView({ block: 'nearest' })
  })
}

function onClickOutside(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (triggerRef.value?.contains(target) || listRef.value?.contains(target)) return
  isOpen.value = false
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))

watch(() => props.modelValue, () => {
  if (!isOpen.value) {
    highlightedIndex.value = filteredOptions.value.findIndex(o => o.value === props.modelValue)
  }
})
</script>

<template>
  <div class="app-dropdown" :class="{ 'app-dropdown--open': isOpen, 'app-dropdown--disabled': disabled, 'app-dropdown--error': error }">
    <!-- Trigger -->
    <button
      ref="triggerRef"
      type="button"
      class="app-dropdown__trigger"
      :disabled="disabled"
      @click="toggle"
      @keydown="onKeydown"
      :aria-expanded="isOpen"
      aria-haspopup="listbox"
    >
      <span class="app-dropdown__value" :class="{ 'app-dropdown__value--placeholder': !selectedLabel }">
        {{ selectedLabel || placeholder }}
      </span>
      <svg class="app-dropdown__chevron" :class="{ 'app-dropdown__chevron--open': isOpen }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 9l6 6 6-6" />
      </svg>
    </button>

    <!-- Dropdown panel -->
    <Transition name="dropdown">
      <div v-if="isOpen" ref="listRef" class="app-dropdown__panel" role="listbox">
        <button
          v-for="(opt, i) in filteredOptions"
          :key="String(opt.value) + i"
          type="button"
          class="app-dropdown__option"
          :class="{
            'app-dropdown__option--selected': opt.value === modelValue,
            'app-dropdown__option--highlighted': i === highlightedIndex,
          }"
          role="option"
          :aria-selected="opt.value === modelValue"
          @click="select(opt.value)"
          @mouseenter="highlightedIndex = i"
        >
          <span class="app-dropdown__option-label">{{ opt.label }}</span>
          <svg v-if="opt.value === modelValue" class="app-dropdown__check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 6L9 17l-5-5" />
          </svg>
        </button>

        <div v-if="filteredOptions.length === 0" class="app-dropdown__empty">
          Sin opciones
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.app-dropdown {
  position: relative;
  width: 100%;
}

/* Trigger */
.app-dropdown__trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  background: #eef2f7;
  border: 1.5px solid #cbd5e1;
  color: #1e293b;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  font-family: inherit;
  border-radius: 0.375rem;
  outline: none;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
  text-align: left;
  line-height: 1.4;
}
.app-dropdown__trigger:focus {
  border-color: #1ea7dc;
  box-shadow: 0 0 0 3px rgba(30, 167, 220, 0.2);
}
.app-dropdown--error .app-dropdown__trigger {
  border-color: #dc2626;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
}
.app-dropdown--disabled .app-dropdown__trigger {
  opacity: 1;
  background: #f1f5f9;
  color: #64748b;
  cursor: not-allowed;
  border-color: #e2e8f0;
  box-shadow: none;
}
.app-dropdown--open .app-dropdown__trigger {
  border-color: #1ea7dc;
  box-shadow: 0 0 0 3px rgba(30, 167, 220, 0.2);
}

/* Value */
.app-dropdown__value {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
  min-width: 0;
}
.app-dropdown__value--placeholder {
  color: #64748b;
}

/* Chevron */
.app-dropdown__chevron {
  flex-shrink: 0;
  color: #64748b;
  transition: transform 0.2s ease;
  margin-left: 0.5rem;
}
.app-dropdown__chevron--open {
  transform: rotate(180deg);
}

/* Panel */
.app-dropdown__panel {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 50;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  box-shadow: 0 4px 12px -1px rgba(15, 41, 77, 0.12), 0 1px 3px 0 rgba(15, 41, 77, 0.05);
  max-height: 200px;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-color: rgba(30, 75, 136, 0.25) transparent;
  padding: 0.25rem;
}
.app-dropdown__panel::-webkit-scrollbar {
  width: 5px;
}
.app-dropdown__panel::-webkit-scrollbar-track {
  background: transparent;
}
.app-dropdown__panel::-webkit-scrollbar-thumb {
  background: rgba(30, 75, 136, 0.25);
  border-radius: 4px;
}

/* Options */
.app-dropdown__option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  color: #374151;
  background: transparent;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  text-align: left;
  font-family: inherit;
  transition: background-color 0.1s, color 0.1s;
  line-height: 1.4;
}
.app-dropdown__option:hover,
.app-dropdown__option--highlighted {
  background: #f8fafc;
  color: #1e293b;
}
.app-dropdown__option--selected {
  color: #1e4b88;
}
.app-dropdown__option--selected.app-dropdown__option--highlighted {
  background: #f0f7ff;
}
.app-dropdown__option-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.app-dropdown__check {
  flex-shrink: 0;
  color: #1e4b88;
  margin-left: 0.5rem;
}

/* Empty */
.app-dropdown__empty {
  padding: 0.75rem;
  text-align: center;
  font-size: 0.8125rem;
  color: #64748b;
}

/* Transition */
.dropdown-enter-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.dropdown-leave-active {
  transition: opacity 0.1s ease, transform 0.1s ease;
}
.dropdown-enter-from {
  opacity: 0;
  transform: translateY(-4px);
}
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
