<script setup lang="ts">
import { computed } from 'vue'
import AppDropdown from './AppDropdown.vue'

const props = withDefaults(defineProps<{
  label: string
  modelValue: string | number | null
  type?: 'text' | 'email' | 'number' | 'password' | 'date' | 'textarea' | 'select'
  placeholder?: string
  required?: boolean
  disabled?: boolean
  maxlength?: number
  helper?: string
  error?: string
  icon?: string
  options?: Array<{ value: string | number | null; label: string }>
  rows?: number
  min?: number
  max?: number
  step?: number
}>(), {
  type: 'text',
  rows: 3,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null]
}>()

const charCount = computed(() => {
  if (props.modelValue == null) return 0
  return String(props.modelValue).length
})

const charPercent = computed(() => {
  if (!props.maxlength) return 0
  return (charCount.value / props.maxlength) * 100
})

const charClass = computed(() => {
  if (!props.maxlength) return ''
  if (charPercent.value >= 100) return 'admin-char-count--limit'
  if (charPercent.value >= 80) return 'admin-char-count--warn'
  return ''
})

const hasError = computed(() => !!props.error)

function onInput(e: Event) {
  const target = e.target as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
  const val = props.type === 'number' ? (target.value === '' ? null : Number(target.value)) : target.value
  emit('update:modelValue', val)
}

function onDropdownUpdate(val: string | number | null) {
  emit('update:modelValue', val)
}

const icons: Record<string, string> = {
  user: 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
  mail: 'M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zM22 6l-10 7L2 6',
  phone: 'M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z',
  'map-pin': 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0zM12 13a3 3 0 100-6 3 3 0 000 6z',
  car: 'M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2M7 17a2 2 0 100-4 2 2 0 000 4M17 17a2 2 0 100-4 2 2 0 000 4z',
  hash: 'M4 9h16M4 15h16M10 3l-2 18M16 3l-2 18',
  'file-text': 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M16 13H8M16 17H8M10 9H8',
  fuel: 'M3 22V6a2 2 0 012-2h6a2 2 0 012 2v16M3 22h10M14 12h2a2 2 0 012 2v3a2 2 0 002 2 2 2 0 002-2V9l-3-3',
  scale: 'M12 3v18M3 7l3 6a4 4 0 006 0l3-6M15 7l3 6a4 4 0 006 0l3-6',
  'box': 'M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0022 16zM3.27 6.96L12 12.01l8.73-5.05M12 22.08V12',
  truck: 'M1 3h15v13H1zM16 8h4l3 3v5h-7V8zM5.5 21a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM18.5 21a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
  dollar: 'M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6',
  package: 'M16.5 9.4l-9-5.19M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16zM3.27 6.96L12 12.01l8.73-5.05M12 22.08V12',
  calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z',
  palette: 'M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.7-.8 1.7-1.7 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-.9.8-1.7 1.7-1.7H17c3.3 0 6-2.7 6-6 0-5.3-4.5-9-11-9zM6.5 12a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm3-4a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm5 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm3 4a1.5 1.5 0 110-3 1.5 1.5 0 010 3z',
  building: 'M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16',
  lock: 'M19 11H5a2 2 0 00-2 2v7a2 2 0 002 2h14a2 2 0 002-2v-7a2 2 0 00-2-2zM7 11V7a5 5 0 0110 0v4',
  briefcase: 'M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16',
  layers: 'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
  tag: 'M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82zM7 7h.01',
  'dollar-sign': 'M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6',
  percent: 'M19 5L5 19M6.5 6.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm11 11a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
  globe: 'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z',
  'credit-card': 'M1 4h22v16H1zM1 10h22',
}
</script>

<template>
  <div class="app-form-field" :class="{ 'app-form-field--error': hasError }">
    <label class="app-form-field__label">
      <svg v-if="icon && icons[icon]" class="app-form-field__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path :d="icons[icon]" />
      </svg>
      {{ label }}
      <span v-if="required" class="app-form-field__required">*</span>
    </label>

    <!-- Text / Email / Password / Date -->
    <input
      v-if="type === 'text' || type === 'email' || type === 'password' || type === 'date'"
      :type="type"
      :value="modelValue ?? ''"
      :placeholder="placeholder"
      :disabled="disabled"
      :maxlength="maxlength"
      :required="required"
      class="admin-input"
      :class="{ 'admin-input--error': hasError }"
      @input="onInput"
    />

    <!-- Number -->
    <input
      v-else-if="type === 'number'"
      type="number"
      :value="modelValue ?? ''"
      :placeholder="placeholder"
      :disabled="disabled"
      :min="min"
      :max="max"
      :step="step"
      class="admin-input"
      :class="{ 'admin-input--error': hasError }"
      @input="onInput"
    />

    <!-- Textarea -->
    <textarea
      v-else-if="type === 'textarea'"
      :value="String(modelValue ?? '')"
      :placeholder="placeholder"
      :disabled="disabled"
      :maxlength="maxlength"
      :rows="rows"
      class="admin-input app-form-field__textarea"
      :class="{ 'admin-input--error': hasError }"
      @input="onInput"
    />

    <!-- Select (custom dropdown) -->
    <AppDropdown
      v-else-if="type === 'select'"
      :model-value="modelValue"
      :options="options ?? []"
      :placeholder="placeholder"
      :disabled="disabled"
      :error="hasError"
      @update:model-value="onDropdownUpdate"
    />

    <!-- Footer: helper + char count -->
    <div v-if="helper || maxlength" class="app-form-field__footer">
      <span v-if="helper" class="app-form-field__helper">{{ helper }}</span>
      <span v-if="maxlength" class="app-form-field__charcount" :class="charClass">
        {{ charCount }}/{{ maxlength }}
      </span>
    </div>

    <!-- Error -->
    <p v-if="hasError" class="app-form-field__error">{{ error }}</p>
  </div>
</template>

<style scoped>
.app-form-field {
  display: flex;
  flex-direction: column;
}
.app-form-field__label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-bottom: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #374151;
  line-height: 1;
}
.app-form-field__icon {
  opacity: 0.5;
  flex-shrink: 0;
}
.app-form-field__required {
  color: #dc2626;
  font-weight: 600;
}
.app-form-field__textarea {
  min-height: 5rem;
  resize: vertical;
}
.app-form-field__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.375rem;
  gap: 0.5rem;
}
.app-form-field__helper {
  font-size: 0.6875rem;
  color: #64748b;
  line-height: 1.3;
}
.app-form-field__charcount {
  font-size: 0.6875rem;
  font-variant-numeric: tabular-nums;
  color: #64748b;
  white-space: nowrap;
  flex-shrink: 0;
  transition: color 0.15s;
}
.app-form-field__charcount--warn {
  color: #d97706;
}
.app-form-field__charcount--limit {
  color: #dc2626;
  font-weight: 600;
}
.app-form-field__error {
  margin-top: 0.375rem;
  font-size: 0.75rem;
  color: #dc2626;
  line-height: 1.3;
}

/* Error state border */
.admin-input--error {
  border-color: #dc2626 !important;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15) !important;
}
</style>
