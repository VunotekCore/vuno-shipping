<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { routeRunService, routeStopService, driverLocationService, type RouteStop } from '../../services/routeService'
import { useToast } from '../../composables/useToast'
import { statusLabel } from '../../utils/format'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const stopId = computed(() => Number(route.params.id))

const loading = ref(true)
const stop = ref<RouteStop | null>(null)
const error = ref('')
const runStatus = ref('')

const showActionModal = ref(false)
const actionStatus = ref<'skipped' | 'failed'>('skipped')
const actionNotes = ref('')
const saving = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    // Load stops from the active run, find the one with matching id
    const res = await driverLocationService.driverRoutes()
    const runs: any[] = res.data.data.runs ?? []
    const active = runs.find(r => r.status_name === 'active') || runs[0]
    if (!active) { error.value = 'Sin ruta activa'; return }

    runStatus.value = active.status_name

    const detail = await routeRunService.get(active.id)
    const stops: RouteStop[] = detail.data.data.run.stops ?? []
    stop.value = stops.find(s => s.id === stopId.value) ?? null
    if (!stop.value) error.value = 'Parada no encontrada'
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar'
  } finally {
    loading.value = false
  }
}

function goDeliver() {
  router.push(`/conductor/deliver/${stopId.value}`)
}

function openAction(status: 'skipped' | 'failed') {
  actionStatus.value = status
  actionNotes.value = stop.value?.notes ?? ''
  showActionModal.value = true
}

async function confirmAction() {
  if (!stop.value || !actionNotes.value.trim()) return
  saving.value = true
  try {
    await routeStopService.updateStatus(stop.value.id, actionStatus.value, actionNotes.value)
    showActionModal.value = false
    toast.success(actionStatus.value === 'skipped' ? 'Parada omitida (se arrastrará al próximo despacho)' : 'Parada fallada (se arrastrará al próximo despacho)')
    await load()
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al actualizar la parada')
  } finally {
    saving.value = false
  }
}

function goBack() {
  router.push('/conductor')
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-4">
    <button class="flex items-center gap-2 text-sm text-muted hover:text-content" @click="goBack">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Volver
    </button>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
    </div>

    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-sm text-danger">{{ error }}</p>
    </div>

    <template v-else-if="stop">
      <!-- Status badge -->
      <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold">Parada #{{ stop.stop_order }}</h1>
        <span class="rounded-full px-3 py-1 text-[11px] font-medium"
          :class="stop.status_name === 'delivered' ? 'bg-success/15 text-success' : stop.status_name === 'pending' ? 'bg-warning/15 text-warning' : stop.status_name === 'failed' ? 'bg-danger/15 text-danger' : 'bg-surface-raised text-subtle'"
        >
          {{ statusLabel(stop.status_name) }}
        </span>
      </div>

      <!-- Client info -->
      <div class="admin-card p-5 space-y-4">
        <div>
          <p class="text-xs text-subtle uppercase tracking-wide">Cliente</p>
          <p class="mt-0.5 text-base font-medium">{{ stop.client_name || '—' }}</p>
        </div>

        <div>
          <p class="text-xs text-subtle uppercase tracking-wide">Dirección</p>
          <p class="mt-0.5 text-sm">{{ stop.address || '—' }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-subtle uppercase tracking-wide">Tracking</p>
            <p class="mt-0.5 text-sm font-mono">{{ stop.tracking_number || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-subtle uppercase tracking-wide">Teléfono</p>
            <p class="mt-0.5 text-sm">{{ stop.recipient_phone || '—' }}</p>
          </div>
        </div>

        <div v-if="stop.invoice_number" class="flex items-center justify-between rounded-lg border border-line px-3 py-2.5">
          <div>
            <p class="text-xs text-subtle uppercase tracking-wide">Pago del paquete</p>
            <p class="mt-0.5 text-xs text-muted">{{ stop.invoice_number }}</p>
          </div>
          <span
            class="rounded-full px-2.5 py-1 text-[11px] font-medium"
            :class="stop.invoice_status === 'paid' ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning'"
          >
            {{ stop.invoice_status === 'paid' ? 'PAGADO' : 'COBRAR EN ENTREGA' }}
          </span>
        </div>

        <div v-if="stop.notes">
          <p class="text-xs text-subtle uppercase tracking-wide">Notas</p>
          <p class="mt-0.5 text-sm">{{ stop.notes }}</p>
        </div>
      </div>

      <!-- Package info -->
      <div v-if="stop.package_description" class="admin-card p-5">
        <p class="text-xs text-subtle uppercase tracking-wide">Descripción del paquete</p>
        <p class="mt-0.5 text-sm">{{ stop.package_description }}</p>
      </div>

      <!-- Actions -->
      <div v-if="stop.status_name === 'pending'" class="space-y-2">
        <template v-if="runStatus === 'active'">
          <button
            class="w-full rounded-lg bg-primary py-3 text-center text-sm font-semibold text-surface transition-opacity hover:opacity-90"
            @click="goDeliver"
          >
            Entregar paquete
          </button>
          <div class="grid grid-cols-2 gap-2">
            <button
              class="rounded-lg bg-surface-raised py-2.5 text-center text-sm font-medium text-content transition-colors hover:bg-line-strong"
              @click="openAction('skipped')"
            >
              Omitir
            </button>
            <button
              class="rounded-lg bg-danger/10 py-2.5 text-center text-sm font-medium text-danger transition-colors hover:bg-danger/20"
              @click="openAction('failed')"
            >
              No se pudo entregar
            </button>
          </div>
        </template>
        <div v-else class="admin-card p-4 text-center">
          <p class="text-sm text-subtle">Esta parada aún no puede procesarse: el despacho está en "{{ runStatus }}".</p>
        </div>
      </div>

      <div v-else-if="stop.signature_data" class="admin-card p-5">
        <p class="text-xs text-subtle uppercase tracking-wide">Firma del cliente</p>
        <img :src="stop.signature_data" alt="Firma" class="mt-2 max-h-24 w-full rounded-lg border border-line bg-white object-contain p-2" />
      </div>

      <div v-if="stop.photo_url" class="admin-card p-5">
        <p class="text-xs text-subtle uppercase tracking-wide">Foto de evidencia</p>
        <img :src="stop.photo_url" alt="Foto" class="mt-2 w-full rounded-lg border border-line" />
      </div>

      <div v-if="stop.actual_arrival" class="admin-card p-5">
        <p class="text-xs text-subtle uppercase tracking-wide">Entregado a las</p>
        <p class="mt-0.5 text-sm">{{ stop.actual_arrival }}</p>
      </div>
    </template>
  </div>

  <!-- Observation modal (skip/failed) -->
  <div v-if="showActionModal" class="fixed inset-0 z-50 flex items-end justify-center bg-primary-deep/55 p-4 sm:items-center" @click.self="showActionModal = false">
    <div class="w-full max-w-md rounded-2xl border border-line bg-surface p-5 shadow-2xl">
      <h3 class="text-sm font-semibold text-content">
        {{ actionStatus === 'skipped' ? 'Omitir parada' : 'No se pudo entregar' }}
      </h3>
      <p v-if="stop" class="mt-0.5 text-xs text-muted">{{ stop.client_name }} · {{ stop.tracking_number }}</p>
      <div class="mt-4">
        <label class="mb-1.5 block text-xs font-medium text-muted">
          Observación (obligatoria — se arrastrará al próximo despacho)
        </label>
        <textarea
          v-model="actionNotes"
          class="admin-input min-h-[90px]"
          placeholder="Ej: Cliente no estaba, coordinar por teléfono..."
          maxlength="1000"
          :disabled="saving"
        />
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <button class="admin-btn admin-btn-ghost" :disabled="saving" @click="showActionModal = false">Cancelar</button>
        <button
          class="admin-btn"
          :class="actionStatus === 'skipped' ? 'admin-btn-ghost' : 'admin-btn-danger'"
          :disabled="saving || !actionNotes.trim()"
          @click="confirmAction"
        >
          {{ saving ? 'Guardando...' : 'Confirmar' }}
        </button>
      </div>
    </div>
  </div>
</template>
