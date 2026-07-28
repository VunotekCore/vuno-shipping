<script setup lang="ts">
import { ref, computed, watch } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: string | number | null
  label?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
  error?: string
  helper?: string
}>(), {
  placeholder: '1234-5678',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const inputRef = ref<HTMLInputElement | null>(null)

const COUNTRY_PREFIX = '+505'
const DIGIT_COUNT = 8

function extractDigits(val: string | number | null): string {
  return String(val ?? '').replace(/\D/g, '')
}

function formatPhone(digits: string): string {
  const clean = digits.slice(0, DIGIT_COUNT)
  if (!clean) return ''
  if (clean.length <= 4) return `${COUNTRY_PREFIX} ${clean}`
  return `${COUNTRY_PREFIX} ${clean.slice(0, 4)}-${clean.slice(4)}`
}

const displayValue = ref('')

function syncDisplay() {
  const digits = extractDigits(props.modelValue)
  displayValue.value = formatPhone(digits)
}

watch(() => props.modelValue, syncDisplay, { immediate: true })

function onInput(e: Event) {
  const raw = (e.target as HTMLInputElement).value
  const digits = extractDigits(raw)
  const formatted = formatPhone(digits)
  displayValue.value = formatted
  if (digits.length > 0) {
    emit('update:modelValue', formatted)
  } else {
    emit('update:modelValue', '')
  }
}

function onPaste(e: ClipboardEvent) {
  e.preventDefault()
  const pasted = e.clipboardData?.getData('text') ?? ''
  const digits = extractDigits(pasted).slice(0, DIGIT_COUNT)
  const formatted = formatPhone(digits)
  displayValue.value = formatted
  if (digits.length > 0) {
    emit('update:modelValue', formatted)
  }
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Backspace') {
    const digits = extractDigits(displayValue.value)
    if (digits.length === 0) return
    const newDigits = digits.slice(0, -1)
    const formatted = formatPhone(newDigits)
    displayValue.value = formatted
    if (newDigits.length > 0) {
      emit('update:modelValue', formatted)
    } else {
      emit('update:modelValue', '')
    }
    e.preventDefault()
  }
}

const digitCount = computed(() => extractDigits(displayValue.value).length)
const hasError = computed(() => !!props.error)
</script>

<template>
  <div class="app-form-field" :class="{ 'app-form-field--error': hasError }">
    <label v-if="label" class="app-form-field__label">
      <svg class="app-form-field__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z" />
      </svg>
      {{ label }}
      <span v-if="required" class="app-form-field__required">*</span>
    </label>

    <div class="app-phone-input" :class="{ 'app-phone-input--error': hasError, 'app-phone-input--disabled': disabled }">
      <span class="app-phone-input__prefix">{{ COUNTRY_PREFIX }}</span>
      <input
        ref="inputRef"
        :value="displayValue.replace(COUNTRY_PREFIX + ' ', '')"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        class="app-phone-input__input"
        inputmode="numeric"
        autocomplete="tel-national"
        @input="onInput"
        @paste="onPaste"
        @keydown="onKeydown"
      />
    </div>

    <div class="app-form-field__footer">
      <span v-if="helper" class="app-form-field__helper">{{ helper }}</span>
      <span v-else-if="hasError && error" class="app-form-field__helper app-form-field__helper--error">{{ error }}</span>
      <span v-else />
      <span class="app-form-field__charcount" :class="digitCount === DIGIT_COUNT ? '' : digitCount > 0 ? 'app-form-field__charcount--warn' : ''">
        {{ digitCount }}/{{ DIGIT_COUNT }}
      </span>
    </div>

    <p v-if="hasError && error" class="app-form-field__error">{{ error }}</p>
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
  color: #94a3b8;
  line-height: 1;
}
.app-form-field__icon {
  opacity: 0.5;
  flex-shrink: 0;
}
.app-form-field__required {
  color: #f87171;
  font-weight: 600;
}
.app-form-field__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.375rem;
  gap: 0.5rem;
  min-height: 1.25rem;
}
.app-form-field__helper {
  font-size: 0.6875rem;
  color: #64748b;
  line-height: 1.3;
}
.app-form-field__helper--error {
  color: #f87171;
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
  color: #fbbf24;
}
.app-form-field__error {
  margin-top: 0.375rem;
  font-size: 0.75rem;
  color: #f87171;
  line-height: 1.3;
}

.app-phone-input {
  display: flex;
  align-items: stretch;
  border-radius: 0.375rem;
  border: 1.5px solid #404944;
  background: #1e293b;
  overflow: hidden;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.app-phone-input:focus-within {
  border-color: #69dca4;
  box-shadow: 0 0 0 2px rgba(105, 220, 164, 0.25);
}
.app-phone-input--error {
  border-color: #f87171 !important;
  box-shadow: 0 0 0 2px rgba(248, 113, 113, 0.25) !important;
}
.app-phone-input--disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.app-phone-input__prefix {
  display: flex;
  align-items: center;
  padding: 0 0.625rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #69dca4;
  background: #111d2e;
  white-space: nowrap;
  user-select: none;
}
.app-phone-input__input {
  flex: 1;
  border: none;
  background: transparent;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  color: #dae2fd;
  outline: none;
  min-width: 0;
}
.app-phone-input__input::placeholder {
  color: #64748b;
}
.app-phone-input__input:disabled {
  cursor: not-allowed;
}
</style>
