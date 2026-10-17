<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { packageService, type Package, type TrackingEvent } from '../services/packageService'
import { useToast } from '../composables/useToast'
import { statusColor, formatCurrency } from '../utils/format'

const toast = useToast()
const route = useRoute()

const scanInput = ref('')
const scanInputRef = ref<HTMLInputElement | null>(null)
const loading = ref(false)
const packageData = ref<Package | null>(null)
const events = ref<TrackingEvent[]>([])
const showStatusModal = ref(false)
const selectedStatus = ref('')
const statusNote = ref('')
const statusUpdating = ref(false)
const historyMode = ref(false)

const statusLabels: Record<string, { label: string; color: string; icon: string }> = {
  pending: { label: 'Pendiente', color: 'admin-badge-warning', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  received: { label: 'Recibido en almacén Miami', color: 'admin-badge-info', icon: 'M5 13l4 4L19 7' },
  in_transit: { label: 'En tránsito a Nicaragua', color: 'admin-badge-info', icon: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2-1m4 0l2 1m4-3l2 1m-4 3v2a1 1 0 001 1h3M7 17v2a1 1 0 001 1h3' },
  in_nicaragua: { label: 'Recibido en Nicaragua', color: 'admin-badge-info', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  out_for_delivery: { label: 'En ruta de entrega', color: 'admin-badge-warning', icon: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7' },
  delivered: { label: 'Entregado', color: 'admin-badge-success', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  returned: { label: 'Devuelto', color: 'admin-badge-danger', icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
  cancelled: { label: 'Cancelado', color: 'admin-badge-danger', icon: 'M6 18L18 6M6 6l12 12' },
  disputed: { label: 'En disputa', color: 'admin-badge-danger', icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z' },
}

// Valid transitions map (same as seed.sql)
const validTransitions: Record<string, string[]> = {
  pending: ['received', 'cancelled'],
  received: ['in_transit', 'cancelled'],
  in_transit: ['in_nicaragua'],
  in_nicaragua: ['out_for_delivery', 'cancelled'],
  out_for_delivery: ['delivered', 'returned'],
  delivered: ['disputed'],
  returned: ['disputed'],
  cancelled: [],
  disputed: [],
}

const currentStatusName = computed(() => packageData.value?.status_name || null)

const availableTransitions = computed(() => {
  if (!currentStatusName.value) return []
  return validTransitions[currentStatusName.value] || []
})

const statusColorClass = computed(() => {
  return statusColor(currentStatusName.value)
})

const lastEvents = computed(() => {
  return events.value.slice(0, 5)
})

async function lookupPackage() {
  const input = scanInput.value.trim()
  if (!input) return

  const isNumeric = /^\d+$/.test(input)

  loading.value = true
  packageData.value = null
  events.value = []
  historyMode.value = false

  try {
    const res = isNumeric
      ? await packageService.get(parseInt(input))
      : await packageService.getByTracking(input)

    const pkg = res.data.data?.package
    if (pkg) {
      packageData.value = pkg
      events.value = res.data.data?.events || []
      scanInput.value = ''
    } else {
      toast.error('Paquete no encontrado')
    }
  } catch {
    toast.error('Paquete no encontrado')
  } finally {
    loading.value = false
    nextTick(() => scanInputRef.value?.focus())
  }
}

function openStatusTransition(status: string) {
  selectedStatus.value = status
  statusNote.value = ''
  showStatusModal.value = true
}

async function confirmStatusChange() {
  if (!packageData.value || !selectedStatus.value) return

  statusUpdating.value = true
  try {
    await packageService.updateStatus(
      packageData.value.id,
      selectedStatus.value,
      statusNote.value || undefined,
    )
    toast.success(`Estado actualizado a "${statusLabels[selectedStatus.value]?.label || selectedStatus.value}"`)

    // Reload package
    const res = await packageService.get(packageData.value.id)
    packageData.value = res.data.data?.package || null
    events.value = res.data.data?.events || []
    showStatusModal.value = false
  } catch {
    toast.error('Error al actualizar estado')
  } finally {
    statusUpdating.value = false
  }
}

function viewHistory() {
  historyMode.value = !historyMode.value
}

onMounted(() => {
  const trackingParam = route.query.tracking as string | undefined
  if (trackingParam) {
    scanInput.value = trackingParam
    lookupPackage()
  } else {
    nextTick(() => scanInputRef.value?.focus())
  }
})
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <!-- Scan input -->
    <div class="admin-card p-5">
      <div class="flex items-center gap-3">
        <svg class="h-6 w-6 shrink-0 text-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 9V7a2 2 0 012-2h2M3 15v2a2 2 0 002 2h2M21 9V7a2 2 0 00-2-2h-2M21 15v2a2 2 0 01-2 2h-2M7 12h.01M12 12h.01M17 12h.01" />
        </svg>
        <input
          ref="scanInputRef"
          v-model="scanInput"
          class="admin-input flex-1 text-lg"
          placeholder="Escaneá o ingresá el código del paquete..."
          autocomplete="off"
          autocapitalize="off"
          spellcheck="false"
          @keyup.enter="lookupPackage"
        />
        <button class="admin-btn admin-btn-primary shrink-0" :disabled="loading || !scanInput.trim()" @click="lookupPackage">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" /></svg>
          <span class="hidden sm:inline ml-1.5">Buscar</span>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="mt-6 space-y-3">
      <div v-for="i in 4" :key="i" class="h-5 animate-pulse rounded bg-surface-raised" />
    </div>

    <!-- Package info -->
    <div v-else-if="packageData" class="mt-6 space-y-4">
      <!-- Header card -->
      <div class="admin-card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <p class="text-xs text-subtle">Tracking</p>
            <p class="text-2xl font-bold font-mono tracking-wider text-content">{{ packageData.tracking_number }}</p>
          </div>
          <div class="text-right">
            <span :class="['admin-badge', statusColorClass, 'text-sm px-3 py-1']">
              {{ statusLabels[packageData.status_name || '']?.label || packageData.status_name }}
            </span>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
          <div>
            <p class="text-xs text-subtle">Destinatario</p>
            <p class="font-medium text-content truncate">{{ packageData.recipient_name }}</p>
          </div>
          <div>
            <p class="text-xs text-subtle">Teléfono</p>
            <p class="text-muted">{{ packageData.recipient_phone }}</p>
          </div>
          <div>
            <p class="text-xs text-subtle">Peso cobrable</p>
            <p class="text-muted">{{ packageData.chargeable_weight ? Number(packageData.chargeable_weight).toFixed(2) + ' lb' : '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-subtle">Total</p>
            <p class="font-medium text-primary">{{ formatCurrency(packageData.shipping_cost) }}</p>
          </div>
        </div>

        <div v-if="packageData.recipient_address" class="mt-2 text-xs text-subtle">
          {{ packageData.recipient_address }}{{ packageData.recipient_city ? ', ' + packageData.recipient_city : '' }}
        </div>
      </div>

      <!-- Status transitions -->
      <div v-if="availableTransitions.length > 0" class="admin-card p-5">
        <p class="admin-form-section mb-3">Cambiar estado</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="status in availableTransitions"
            :key="status"
            class="admin-btn text-sm font-medium"
            :class="status === 'delivered' ? 'admin-btn-success bg-primary text-canvas hover:bg-success' : 'admin-btn-primary'"
            @click="openStatusTransition(status)"
          >
            <svg class="-ml-1 mr-1.5 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path :d="statusLabels[status]?.icon || 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'" /></svg>
            {{ statusLabels[status]?.label || status }}
          </button>
        </div>
      </div>

      <div v-else class="admin-card p-5 text-center">
        <p class="text-sm text-subtle">Este paquete no tiene cambios de estado disponibles</p>
      </div>

      <!-- Timeline -->
      <div class="admin-card p-5">
        <div class="flex items-center justify-between mb-4">
          <p class="admin-form-section mb-0">Eventos</p>
          <button class="text-xs text-secondary hover:underline" @click="viewHistory">
            {{ historyMode ? 'Ver menos' : 'Ver historial completo' }}
          </button>
        </div>

        <div class="space-y-0">
          <div
            v-for="(ev, i) in historyMode ? events : lastEvents"
            :key="ev.id"
            class="relative flex gap-3 pb-4"
            :class="{ 'pb-0': i === (historyMode ? events.length : Math.min(lastEvents.length, events.length)) - 1 }"
          >
            <!-- Timeline dot + line -->
            <div class="flex flex-col items-center">
              <div
                :class="[
                  'h-3 w-3 rounded-full border-2 shrink-0',
                  i === 0 ? 'border-primary bg-primary' : 'border-line bg-surface'
                ]"
              />
              <div v-if="i < (historyMode ? events.length : Math.min(lastEvents.length, events.length)) - 1" class="mt-0.5 w-px flex-1 bg-surface-raised" />
            </div>
            <!-- Event content -->
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-content">
                  {{ statusLabels[ev.status]?.label || ev.status }}
                </span>
                <span :class="['admin-badge', statusColor(ev.status), 'text-[10px]']">{{ ev.status }}</span>
              </div>
              <p v-if="ev.description" class="mt-0.5 text-xs text-muted">{{ ev.description }}</p>
              <div class="mt-0.5 flex items-center gap-2 text-[10px] text-subtle">
                <span>{{ ev.recorded_at || ev.created_at }}</span>
                <span v-if="ev.created_by_name">— {{ ev.created_by_name }}</span>
                <span v-if="ev.location_name">— {{ ev.location_name }}</span>
              </div>
            </div>
          </div>

          <div v-if="events.length === 0" class="py-4 text-center text-sm text-subtle">
            Sin eventos registrados
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="mt-16 text-center">
      <svg class="mx-auto h-16 w-16 text-content" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
        <path d="M3 9V7a2 2 0 012-2h2M3 15v2a2 2 0 002 2h2M21 9V7a2 2 0 00-2-2h-2M21 15v2a2 2 0 01-2 2h-2M7 12h.01M12 12h.01M17 12h.01" />
      </svg>
      <p class="mt-4 text-lg font-medium text-subtle">Escaneá o ingresá un código</p>
      <p class="mt-1 text-sm text-muted">Usá el escáner o escribí el número de tracking</p>
    </div>

    <!-- Status confirm modal -->
    <Teleport to="body">
      <div
        v-if="showStatusModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-primary-deep/55 p-4"
        @click.self="showStatusModal = false"
      >
        <div class="w-full max-w-sm rounded-xl border border-line bg-surface p-6 shadow-2xl">
          <h3 class="text-lg font-bold text-content">
            {{ statusLabels[selectedStatus]?.label || selectedStatus }}
          </h3>
          <p class="mt-1 text-sm text-subtle">
            Paquete <span class="font-mono text-muted">{{ packageData?.tracking_number }}</span>
          </p>

          <div class="mt-4">
            <label class="mb-1.5 block text-sm font-medium text-content">Nota (opcional)</label>
            <textarea
              v-model="statusNote"
              class="admin-input w-full"
              rows="2"
              placeholder="Agregar comentario..."
            />
          </div>

          <div class="mt-6 flex gap-3 justify-end">
            <button class="admin-btn admin-btn-ghost" :disabled="statusUpdating" @click="showStatusModal = false">Cancelar</button>
            <button
              class="admin-btn"
              :class="selectedStatus === 'cancelled' || selectedStatus === 'returned' ? 'admin-btn-danger' : 'admin-btn-primary'"
              :disabled="statusUpdating"
              @click="confirmStatusChange"
            >
              {{ statusUpdating ? 'Actualizando...' : 'Confirmar' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
