<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import AppModal from './AppModal.vue'
import { invoiceService } from '../../services/invoiceService'

const props = defineProps<{
  visible: boolean
  invoiceId: number | null
  invoiceNumber?: string
}>()

const emit = defineEmits<{ close: [] }>()

type PrintFormat = 'letter' | 'ticket'

const FORMAT_KEY = 'vuno.invoicePrintFormat'

const format = ref<PrintFormat>('letter')
const currency = ref<'USD' | 'CORD'>('CORD')
const loaded = ref(false)
const printing = ref(false)

const frameRef = ref<HTMLIFrameElement | null>(null)
const stageRef = ref<HTMLElement | null>(null)
const sheetWidth = ref(0)
const sheetHeight = ref(0)
const scale = ref(1)

const url = computed(() =>
  props.invoiceId === null
    ? ''
    : invoiceService.printUrl(props.invoiceId, currency.value, format.value),
)

/** Elige el formato segun el ancho disponible: 80 mm entra siempre en un modal. */
function applyScale() {
  const available = stageRef.value?.clientWidth ?? 0
  if (available <= 0 || sheetWidth.value <= 0) return
  scale.value = Math.min(1, available / sheetWidth.value)
}

/** Mide la hoja real del iframe (mismo origen, asi que es accesible). */
function measure() {
  const sheet = frameRef.value?.contentDocument?.querySelector<HTMLElement>('.sheet')
  if (!sheet) return
  sheetWidth.value = sheet.getBoundingClientRect().width
  sheetHeight.value = sheet.getBoundingClientRect().height
  applyScale()
}

const canvasStyle = computed(() => ({
  width: `${sheetWidth.value * scale.value}px`,
  height: `${sheetHeight.value * scale.value}px`,
}))

const sheetStyle = computed(() => ({
  width: `${sheetWidth.value}px`,
  height: `${sheetHeight.value}px`,
  transform: `scale(${scale.value})`,
}))

function onFrameLoad() {
  measure()
  loaded.value = true
}

function doPrint() {
  const win = frameRef.value?.contentWindow
  if (!win) return
  printing.value = true
  try {
    win.focus()
    win.print()
  } catch {
    openInNewTab()
  } finally {
    printing.value = false
  }
}

/** Escape hatch para guardar como PDF o imprimir con otro navegador. */
function openInNewTab() {
  if (url.value) window.open(url.value, '_blank', 'noopener')
}

let observer: ResizeObserver | null = null

watch(
  () => props.visible,
  async (visible) => {
    if (!visible) return
    try {
      const saved = localStorage.getItem(FORMAT_KEY)
      if (saved === 'ticket' || saved === 'letter') format.value = saved
    } catch {
      /* localStorage bloqueado: se usa el default */
    }
    loaded.value = false
    if (!observer && stageRef.value) {
      observer = new ResizeObserver(applyScale)
      observer.observe(stageRef.value)
    }
  },
  { immediate: true },
)

watch(format, (value) => {
  loaded.value = false
  try {
    localStorage.setItem(FORMAT_KEY, value)
  } catch {
    /* sin persistencia: el formato igual funciona en la sesion */
  }
})

watch(url, () => {
  loaded.value = false
})

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
})
</script>

<template>
  <AppModal
    :visible="visible"
    :title="`Imprimir factura${invoiceNumber ? ' ' + invoiceNumber : ''}`"
    size="lg"
    @close="emit('close')"
  >
    <div class="space-y-4">
      <!-- Formato -->
      <div>
        <p class="mb-1.5 text-sm font-medium text-muted">Formato</p>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="opt in ([
              { value: 'letter', label: 'Carta', hint: '8.5 × 11 in' },
              { value: 'ticket', label: 'Ticket 80 mm', hint: 'Impresora térmica' },
            ] as const)"
            :key="opt.value"
            type="button"
            :class="[
              'rounded-xl border px-3 py-2.5 text-left transition-colors',
              format === opt.value
                ? 'border-secondary bg-secondary/10'
                : 'border-line hover:border-line-strong',
            ]"
            :aria-pressed="format === opt.value"
            @click="format = opt.value"
          >
            <span class="block text-sm font-medium" :class="format === opt.value ? 'text-secondary' : 'text-content'">
              {{ opt.label }}
            </span>
            <span class="block text-xs text-subtle">{{ opt.hint }}</span>
          </button>
        </div>
      </div>

      <!-- Moneda del documento -->
      <div>
        <p class="mb-1.5 text-sm font-medium text-muted">Moneda</p>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="opt in ([
              { value: 'CORD', label: 'Córdobas (C$)' },
              { value: 'USD', label: 'Dólares (US$)' },
            ] as const)"
            :key="opt.value"
            type="button"
            :class="['admin-btn', currency === opt.value ? 'admin-btn-primary' : 'admin-btn-ghost']"
            :aria-pressed="currency === opt.value"
            @click="currency = opt.value"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <!-- Vista previa real -->
      <div class="relative">
        <div
          ref="stageRef"
          class="flex max-h-[52vh] items-start justify-center overflow-auto rounded-xl border border-line bg-surface-sunken p-4"
        >
          <div v-if="url" class="relative shrink-0" :style="canvasStyle">
            <iframe
              ref="frameRef"
              :src="url"
              title="Vista previa de la factura"
              class="origin-top-left border-0 bg-white"
              :style="sheetStyle"
              @load="onFrameLoad"
            />
          </div>
        </div>

        <div
          v-if="!loaded"
          class="absolute inset-0 flex items-center justify-center rounded-xl bg-surface-sunken/80"
        >
          <div class="flex items-center gap-2 text-sm text-muted">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-line-strong border-t-secondary" />
            Generando vista previa...
          </div>
        </div>
      </div>

      <p class="text-xs text-subtle">
        <template v-if="format === 'ticket'">
          Importes y total convertidos a córdobas. Si tu impresora es térmica, seleccioná la bobina de
          <strong class="text-muted">80 mm</strong> en el diálogo de impresión.
        </template>
        <template v-else>
          Tamaño de papel <strong class="text-muted">Carta</strong>. En el diálogo de impresión podés
          elegir “Guardar como PDF”.
        </template>
      </p>
    </div>

    <template #footer>
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <button class="admin-btn admin-btn-ghost admin-btn-sm" @click="openInNewTab">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
          Abrir en pestaña
        </button>
        <div class="flex gap-2">
          <button class="admin-btn admin-btn-ghost" @click="emit('close')">Cancelar</button>
          <button class="admin-btn admin-btn-primary" :disabled="!loaded || !url" @click="doPrint">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
            {{ printing ? 'Imprimiendo...' : 'Imprimir' }}
          </button>
        </div>
      </div>
    </template>
  </AppModal>
</template>