<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import AppModal from '../ui/AppModal.vue'
import AppFormField from '../ui/AppFormField.vue'
import { vehicleService } from '../../services/vehicleService'
import { readingTypeLabel } from '../../utils/format'
import { useAuthStore } from '../../stores/auth'
import type { Vehicle } from '../../types'

const props = withDefaults(defineProps<{
  visible: boolean
  vehicle: Vehicle | null
  defaultType?: string
  /** Restringe los tipos de lectura mostrados. Si se omite, se muestran todos. */
  allowedTypes?: string[]
  /** Fija el tipo de lectura al defaultType (no editable). */
  lockType?: boolean
  routeRunId?: number | null
  title?: string
}>(), {
  defaultType: 'manual',
  lockType: false,
  routeRunId: null,
})

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'saved', payload: { result: Record<string, unknown>; vehicle: Vehicle | null }): void
}>()

const auth = useAuthStore()

const ALL_TYPES = [
  'manual',
  'inicio_ruta',
  'fin_ruta',
  'entrega_llaves',
  'devolucion_llaves',
  'inspeccion',
  'mantenimiento',
  'reparacion',
  'otro',
]

const typeOptions = computed(() => {
  const list = props.allowedTypes && props.allowedTypes.length > 0 ? props.allowedTypes : ALL_TYPES
  return list.map((t) => ({ value: t, label: readingTypeLabel(t) }))
})

const readingType = ref<string>(props.defaultType)
const kmReading = ref<number | null>(null)
const notes = ref('')
const saving = ref(false)
const error = ref('')

const canCorrect = computed(() => auth.hasPermission('vehicle', 'km_correction'))
const previousKm = computed(() => props.vehicle?.current_km ?? 0)
const delta = computed(() => (kmReading.value == null ? null : kmReading.value - previousKm.value))
const isCorrection = computed(() => delta.value != null && delta.value < 0)

function reset() {
  readingType.value = props.defaultType
  kmReading.value = null
  notes.value = ''
  saving.value = false
  error.value = ''
}

watch(() => props.visible, (v) => { if (v) reset() })

async function submit() {
  error.value = ''

  if (kmReading.value == null) {
    error.value = 'Ingresá la lectura del odómetro'
    return
  }
  if (kmReading.value < 0) {
    error.value = 'La lectura no puede ser negativa'
    return
  }
  if (isCorrection.value) {
    if (!canCorrect.value) {
      error.value = 'No tenés permiso para corregir el kilometraje hacia abajo'
      return
    }
    if (!notes.value.trim()) {
      error.value = 'La corrección de kilometraje requiere una justificación'
      return
    }
  }
  if (!props.vehicle) return

  saving.value = true
  try {
    const { data } = await vehicleService.kmRegister({
      vehicle_id: props.vehicle.id,
      km_reading: kmReading.value,
      reading_type: readingType.value,
      route_run_id: props.routeRunId ?? null,
      notes: notes.value.trim() ? notes.value.trim() : null,
    })
    emit('saved', {
      result: (data.data?.result ?? {}) as Record<string, unknown>,
      vehicle: data.data?.vehicle ?? null,
    })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || 'No se pudo registrar la lectura'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal
    :visible="visible"
    :title="title || 'Registrar kilometraje'"
    size="md"
    @close="!saving && emit('close')"
  >
    <form class="space-y-4" @submit.prevent="submit">
      <div
        v-if="vehicle"
        class="rounded-xl border border-[#1e293b] bg-[#0f172a] px-4 py-3 text-sm"
      >
        <div class="flex items-center justify-between">
          <span class="text-[#64748b]">Vehículo</span>
          <span class="font-medium text-[#dae2fd]">{{ vehicle.plate }}</span>
        </div>
        <div class="mt-1 flex items-center justify-between">
          <span class="text-[#64748b]">Kilometraje actual</span>
          <span class="text-[#94a3b8]">{{ previousKm.toLocaleString('es-NI') }} km</span>
        </div>
      </div>

      <AppFormField
        :model-value="readingType"
        @update:model-value="(v: string | number | null) => readingType = String(v)"
        label="Tipo de lectura"
        type="select"
        icon="car"
        :options="typeOptions"
        :disabled="saving || lockType"
      />

      <AppFormField
        :model-value="kmReading"
        @update:model-value="(v: string | number | null) => kmReading = (v === null || v === '' ? null : Number(v))"
        label="Lectura del odómetro (km)"
        type="number"
        icon="clock"
        placeholder="ej: 45230.5"
        :required="true"
        :min="0"
        :step="0.1"
        :disabled="saving"
      />

      <p v-if="delta != null" class="text-sm" :class="isCorrection ? 'text-[#f59e0b]' : 'text-[#64748b]'">
        Variación: <span class="font-medium">{{ delta > 0 ? '+' : '' }}{{ delta.toLocaleString('es-NI') }} km</span>
        <template v-if="isCorrection"> · corrección hacia abajo</template>
      </p>

      <AppFormField
        :model-value="notes"
        @update:model-value="(v: string | number | null) => notes = String(v ?? '')"
        label="Notas / justificación"
        type="textarea"
        icon="file-text"
        placeholder="Opcional, obligatorio si corregís el kilometraje hacia abajo..."
        :disabled="saving"
        :maxlength="500"
        :rows="3"
      />

      <p v-if="error" class="rounded-lg border border-[#7f1d1d] bg-[#450a0a] px-3 py-2 text-sm text-[#fca5a5]">
        {{ error }}
      </p>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <button class="admin-btn admin-btn-ghost" :disabled="saving" @click="emit('close')">Cancelar</button>
        <button
          class="admin-btn admin-btn-primary"
          :disabled="saving || kmReading == null || (isCorrection && !canCorrect)"
          @click="submit"
        >
          {{ saving ? 'Registrando...' : 'Registrar lectura' }}
        </button>
      </div>
    </template>
  </AppModal>
</template>
