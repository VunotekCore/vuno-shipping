<script setup lang="ts">
import { ref, computed, onMounted, watch, useId } from 'vue'
import {
  ID_LENGTH,
  classifyNicaraguanId,
  formatIdForDisplay,
  idNatureLabel,
  normalizeId,
} from '../../utils/validation'

const props = withDefaults(defineProps<{
  modelValue: string | number | null
  label?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
  error?: string
  helper?: string
}>(), {
  placeholder: '001-291289-0042T',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  blur: []
}>()

const inputRef = ref<HTMLInputElement | null>(null)

// Evita que el watcher reescriba el input mientras el usuario escribe.
let applying = false

/**
 * El input muestra el valor con guiones pero emite el canonico de 14
 * caracteres, que es lo que se persiste (VARCHAR(30) alcanza y asi las
 * comparaciones y busquedas no dependen de como se escribio).
 */
function flushFormat(input: HTMLInputElement, rawNew: string, cursorStart: number) {
  const core = normalizeId(rawNew)
  const display = formatIdForDisplay(core)
  const cursorTarget = normalizeId(rawNew.slice(0, cursorStart)).length

  input.value = display
  setCursorAfterCore(input, Math.min(cursorTarget, core.length))
  emit('update:modelValue', core)
}

/** Ubica el cursor despues del N-esimo caracter alfanumerico (saltando guiones). */
function setCursorAfterCore(input: HTMLInputElement, target: number) {
  let count = 0
  for (let i = 0; i < input.value.length; i++) {
    if (/[A-Z0-9]/.test(input.value[i])) count++
    if (count === target) {
      input.setSelectionRange(i + 1, i + 1)
      return
    }
  }
  input.setSelectionRange(input.value.length, input.value.length)
}

function onBeforeInput(e: InputEvent) {
  if (applying || props.disabled) return

  const input = e.target as HTMLInputElement
  const oldVal = input.value
  const start = input.selectionStart ?? oldVal.length
  const end = input.selectionEnd ?? start

  let rawNew: string
  let newCursor: number
  if (e.inputType === 'deleteContentBackward') {
    // Se salta los guiones: son solo presentacion y reaparecerian al
    // reformatear, asi que borrarlos dejaria el input sin cambio apparent.
    let cut = start - 1
    while (cut >= 0 && !/[A-Z0-9]/.test(oldVal[cut])) cut--
    if (cut < 0) {
      e.preventDefault()
      return
    }
    rawNew = oldVal.slice(0, cut) + oldVal.slice(end)
    newCursor = cut
  } else if (e.inputType === 'deleteContentForward') {
    let cut = start
    while (cut < oldVal.length && !/[A-Z0-9]/.test(oldVal[cut])) cut++
    if (cut >= oldVal.length) {
      e.preventDefault()
      return
    }
    rawNew = oldVal.slice(0, start) + oldVal.slice(cut + 1)
    newCursor = start
  } else if ((e.inputType === 'insertText' || e.inputType === 'insertFromPaste') && e.data) {
    rawNew = oldVal.slice(0, start) + e.data + oldVal.slice(end)
    newCursor = start + e.data.length
  } else {
    return
  }

  e.preventDefault()
  applying = true
  flushFormat(input, rawNew, newCursor)
  applying = false
}

// Red de seguridad para navegadores que no disparan beforeinput (p. ej. al
// arrastrar texto o usar el dictado).
function onInputFallback(e: Event) {
  if (applying || props.disabled) return
  const input = e.target as HTMLInputElement
  const core = normalizeId(input.value)
  input.value = formatIdForDisplay(core)
  emit('update:modelValue', core)
}

function onPaste(e: ClipboardEvent) {
  if (applying || props.disabled) return
  e.preventDefault()
  const pasted = e.clipboardData?.getData('text') ?? ''
  if (!pasted) return
  const input = e.target as HTMLInputElement
  const start = input.selectionStart ?? input.value.length
  const end = input.selectionEnd ?? start
  applying = true
  // El cursor queda DESPUES de lo pegado, no en el punto de insercion.
  flushFormat(input, input.value.slice(0, start) + pasted + input.value.slice(end), start + pasted.length)
  applying = false
}

function syncFromModel() {
  if (!inputRef.value) return
  const display = formatIdForDisplay(props.modelValue)
  if (inputRef.value.value !== display) {
    applying = true
    inputRef.value.value = display
    applying = false
  }
}

watch(() => props.modelValue, syncFromModel)

onMounted(syncFromModel)

const core = computed(() => normalizeId(props.modelValue))
const charCount = computed(() => core.value.length)

const classification = computed(() => classifyNicaraguanId(core.value))

/**
 * Error mostrado. El del padre (validacion del formulario o 422 del backend)
 * manda; si no hay, se calcula en vivo pero SOLO cuando el valor ya esta
 * completo: mientras se escribe el formato esta incompleto por definicion y
 * un error en rojo seria ruido.
 *
 * A partir de 14 caracteres si se muestra, para que un valor largo se marque
 * en vez de quedar en silencio.
 */
const error = computed(() => {
  if (props.error) return props.error
  if (core.value.length < ID_LENGTH) return ''
  return classification.value.error ?? ''
})

const hasError = computed(() => Boolean(error.value))
const showNature = computed(() => props.helper || idNatureLabel(core.value))

/**
 * Un valor de 14 caracteres que empieza con digito y termina en letra es
 * ambiguo: puede ser una cedula con la fecha mal tecleada, o un RUC de persona
 * juridica con la letra en el lugar equivocado. Se muestran los dos formatos
 * para que el usuario ubique el error sin tener que adivinar.
 */
const showFormats = computed(() => hasError.value && !props.error && core.value.length === ID_LENGTH)

// a11y: el mensaje de error y la pista de formatos se anuncian con el campo.
const uid = useId()
const errorId = `${uid}-error`
const hintId = `${uid}-hint`
const describedBy = computed(() =>
  [hasError.value ? errorId : '', showFormats.value ? hintId : ''].filter(Boolean).join(' ') || undefined
)
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

    <input
      ref="inputRef"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      class="app-ruc-input__input"
      inputmode="text"
      autocomplete="off"
      spellcheck="false"
      autocapitalize="characters"
      :aria-invalid="hasError || undefined"
      :aria-describedby="describedBy"
      @beforeinput="onBeforeInput"
      @input="onInputFallback"
      @paste="onPaste"
      @blur="emit('blur')"
    />

    <div class="app-form-field__footer">
      <span
        v-if="showNature"
        class="app-form-field__helper"
        :class="!props.helper && !hasError ? 'app-form-field__helper--accent' : ''"
      >{{ showNature }}</span>
      <span v-else />
      <span
        class="app-form-field__charcount"
        :class="charCount === ID_LENGTH ? '' : charCount > 0 ? 'app-form-field__charcount--warn' : ''"
      >
        {{ charCount }}/{{ ID_LENGTH }}
      </span>
    </div>

    <p v-if="hasError" :id="errorId" class="app-form-field__error">{{ error }}</p>
    <p v-if="showFormats" :id="hintId" class="app-form-field__hint">
      Cédula: <span class="app-ruc-input__sample">001-291289-0042T</span> ·
      RUC: <span class="app-ruc-input__sample">J1234567890123</span>
    </p>
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
.app-form-field__helper--accent {
  color: #69dca4;
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
/* Pista de formatos: mas apagada que el error, va debajo. */
.app-form-field__hint {
  margin-top: 0.25rem;
  font-size: 0.6875rem;
  color: #64748b;
  line-height: 1.4;
}
.app-ruc-input__sample {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  color: #94a3b8;
}

.app-ruc-input__input {
  width: 100%;
  border-radius: 0.375rem;
  border: 1.5px solid #404944;
  background: #1e293b;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  color: #dae2fd;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
}
.app-ruc-input__input:focus {
  border-color: #69dca4;
  box-shadow: 0 0 0 2px rgba(105, 220, 164, 0.25);
}
.app-ruc-input__input::placeholder {
  color: #64748b;
}
.app-ruc-input__input:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>