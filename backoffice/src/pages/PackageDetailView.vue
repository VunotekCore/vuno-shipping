<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { packageService, type Package, type TrackingEvent } from '../services/packageService'
import { useToast } from '../composables/useToast'
import { formatCurrency, statusColor } from '../utils/format'
import AppModal from '../components/ui/AppModal.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const backofficeUrl = window.location.origin
const trackingUrl = computed(() => pkg.value
  ? `${backofficeUrl}/scan?tracking=${pkg.value.tracking_number}`
  : '')
function goToScan() {
  if (!pkg.value) return
  router.push(`/scan?tracking=${pkg.value.tracking_number}`)
}

const pkg = ref<Package | null>(null)
const events = ref<TrackingEvent[]>([])
const loading = ref(true)
const showStatusModal = ref(false)
const statusForm = ref({ status: '', description: '' })
const savingStatus = ref(false)

const statusOptions = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'received', label: 'Recibido' },
  { value: 'in_transit', label: 'En tránsito' },
  { value: 'out_for_delivery', label: 'Salió para entrega' },
  { value: 'delivered', label: 'Entregado' },
  { value: 'returned', label: 'Devuelto' },
  { value: 'cancelled', label: 'Cancelado' },
]

async function loadPackage() {
  loading.value = true
  try {
    const id = Number(route.params.id)
    const { data } = await packageService.get(id)
    pkg.value = data.data?.package ?? null
    events.value = data.data?.events ?? []
  } catch {
    toast.error('Error al cargar el paquete')
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('es-NI', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function openStatusUpdate() {
  statusForm.value = { status: '', description: '' }
  showStatusModal.value = true
}

async function updateStatus() {
  if (!statusForm.value.status || !pkg.value) return
  savingStatus.value = true
  try {
    await packageService.updateStatus(pkg.value.id, statusForm.value.status, statusForm.value.description || undefined)
    toast.success('Estado actualizado')
    showStatusModal.value = false
    await loadPackage()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al actualizar estado')
  } finally {
    savingStatus.value = false
  }
}

onMounted(() => loadPackage())
</script>

<template>
  <div class="space-y-6">
    <!-- Loading skeleton -->
    <template v-if="loading">
      <div class="flex items-center gap-3">
        <div class="h-8 w-8 animate-pulse rounded-lg bg-[#1e293b]" />
        <div class="h-6 w-48 animate-pulse rounded bg-[#1e293b]" />
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 admin-card p-6 space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div v-for="i in 8" :key="i" class="space-y-2">
              <div class="h-3 w-16 animate-pulse rounded bg-[#1e293b]" />
              <div class="h-4 w-full animate-pulse rounded bg-[#1e293b]" />
            </div>
          </div>
        </div>
        <div class="admin-card p-6 space-y-4">
          <div v-for="i in 4" :key="i" class="flex gap-3">
            <div class="h-3 w-3 animate-pulse rounded-full bg-[#1e293b]" />
            <div class="flex-1 space-y-2">
              <div class="h-3 w-24 animate-pulse rounded bg-[#1e293b]" />
              <div class="h-3 w-full animate-pulse rounded bg-[#1e293b]" />
            </div>
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="pkg">
      <!-- Page header -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
          <button
            class="admin-btn admin-btn-ghost admin-btn-sm"
            @click="router.push('/packages')"
            title="Volver"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 12H5M12 19l-7-7 7-7" />
            </svg>
          </button>
          <div>
            <h1 class="text-xl font-bold text-[#dae2fd] font-mono">{{ pkg.tracking_number }}</h1>
            <p class="mt-1 text-sm text-[#64748b]">Detalle del paquete</p>
          </div>
        </div>
        <button class="admin-btn admin-btn-primary sm:w-auto w-full" @click="openStatusUpdate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14" />
          </svg>
          Actualizar estado
        </button>
      </div>

      <!-- Two-column layout -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Package info -->
        <div class="lg:col-span-2">
          <div class="admin-card p-6">
            <h2 class="text-sm font-semibold text-[#dae2fd] mb-4">Información del paquete</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <p class="text-xs text-[#64748b] mb-1">Tracking</p>
                <p class="font-mono font-medium text-[#dae2fd]">{{ pkg.tracking_number }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Estado</p>
                <span class="admin-badge" :class="statusColor(pkg.status_name)">
                  {{ pkg.status_name || '—' }}
                </span>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Cliente</p>
                <p class="text-sm text-[#94a3b8]">{{ pkg.client_name || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Servicio de envío</p>
                <p class="text-sm text-[#94a3b8]">{{ pkg.shipping_service_name || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-[#64748b] mb-1">Opción de entrega</p>
                <p class="text-sm text-[#94a3b8]">{{ pkg.delivery_option_name || pkg.delivery_option || '—' }}</p>
              </div>

              <div class="sm:col-span-2 border-t border-[#1e293b] pt-4 mt-2">
                <p class="text-xs text-[#64748b] mb-1">Descripción</p>
                <p class="text-sm text-[#94a3b8]">{{ pkg.description || '—' }}</p>
              </div>

              <div class="sm:col-span-2 border-t border-[#1e293b] pt-4 mt-2">
                <p class="text-xs text-[#64748b] mb-2">Remitente</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Nombre</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.sender_name }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Teléfono</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.sender_phone || '—' }}</p>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-2 border-t border-[#1e293b] pt-4 mt-2">
                <p class="text-xs text-[#64748b] mb-2">Destinatario</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Nombre</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.recipient_name }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Teléfono</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.recipient_phone }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Dirección</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.recipient_address }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Ciudad / Departamento</p>
                    <p class="text-sm text-[#94a3b8]">
                      {{ pkg.recipient_city || '—' }}{{ pkg.recipient_department ? ' / ' + pkg.recipient_department : '' }}
                    </p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Zona de entrega</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.zone_name || '—' }}</p>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-2 border-t border-[#1e293b] pt-4 mt-2">
                <p class="text-xs text-[#64748b] mb-2">Dimensiones y peso</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Peso físico</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.weight ? pkg.weight + ' lb' : '—' }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">
                      Peso volumétrico
                      <span class="help-tip" title="Peso volumétrico = (largo × ancho × alto) ÷ divisor (166 para aéreo). Representa el espacio que ocupa el paquete en el avión.">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline-block ml-0.5 -mt-0.5 opacity-50"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                      </span>
                    </p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.volumetric_weight ? pkg.volumetric_weight + ' lb' : '—' }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">
                      Peso cobrable
                      <span class="help-tip" title="Peso cobrable = el mayor entre el peso físico y el volumétrico. Es lo que se usa para calcular el flete.">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline-block ml-0.5 -mt-0.5 opacity-50"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                      </span>
                    </p>
                    <p class="text-sm text-[#94a3b8] font-medium">{{ pkg.chargeable_weight ? pkg.chargeable_weight + ' lb' : '—' }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Largo × Ancho × Alto</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.length ?? '—' }} × {{ pkg.width ?? '—' }} × {{ pkg.height ?? '—' }}</p>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-2 border-t border-[#1e293b] pt-4 mt-2">
                <p class="text-xs text-[#64748b] mb-2">Desglose de costos</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">
                      Valor declarado
                      <span class="help-tip" title="Valor de la factura de compra (ej. $100 en Amazon). Usado para reglas de tipo porcentaje del valor.">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline-block ml-0.5 -mt-0.5 opacity-50"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                      </span>
                    </p>
                    <p class="text-sm text-[#94a3b8]">{{ formatCurrency(pkg.declared_value) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">
                      Flete (internacional)
                      <span class="help-tip" title="Costo del envío Miami → Nicaragua. Se calcula según la regla de precios (por peso, volumen, valor declarado o tarifa fija).">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline-block ml-0.5 -mt-0.5 opacity-50"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                      </span>
                    </p>
                    <p class="text-sm text-[#94a3b8]">{{ formatCurrency(pkg.flete_cost) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">
                      Delivery (última milla)
                      <span class="help-tip" title="Costo de entrega a domicilio en Nicaragua. Se aplica solo si el tipo de entrega es 'Domicilio' y se basa en la zona de entrega.">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline-block ml-0.5 -mt-0.5 opacity-50"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                      </span>
                    </p>
                    <p class="text-sm text-[#94a3b8]">{{ formatCurrency(pkg.delivery_cost) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">
                      Total
                      <span class="help-tip" title="Total = flete + delivery. Es el monto final que paga el cliente.">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="inline-block ml-0.5 -mt-0.5 opacity-50"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                      </span>
                    </p>
                    <p class="text-sm text-[#dae2fd] font-medium">{{ formatCurrency(pkg.shipping_cost) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Zona</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.zone_name || '—' }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Regla de pricing</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.pricing_rule_name || '—' }}</p>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-2 border-t border-[#1e293b] pt-4 mt-2">
                <p class="text-xs text-[#64748b] mb-2">Asignación</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Asignado a</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.assigned_to_name || '—' }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Notas</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.notes || '—' }}</p>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-2 border-t border-[#1e293b] pt-4 mt-2">
                <p class="text-xs text-[#64748b] mb-2">Fechas</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Creado</p>
                    <p class="text-sm text-[#94a3b8]">{{ formatDate(pkg.created_at) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Recibido</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.received_at ? formatDate(pkg.received_at) : '—' }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-[#64748b] mb-1">Entregado</p>
                    <p class="text-sm text-[#94a3b8]">{{ pkg.delivered_at ? formatDate(pkg.delivered_at) : '—' }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Tracking timeline + QR -->
        <div class="lg:col-span-1 space-y-6">
          <div class="admin-card p-6">
            <h2 class="text-sm font-semibold text-[#dae2fd] mb-4">Historial de seguimiento</h2>

            <!-- Empty timeline -->
            <div v-if="events.length === 0" class="text-center py-8">
              <svg class="mx-auto mb-3 text-[#64748b]" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p class="text-sm text-[#64748b]">Sin eventos registrados</p>
            </div>

            <!-- Timeline -->
            <div v-else class="space-y-0">
              <div v-for="(event, idx) in events" :key="event.id" class="flex gap-3">
                <div class="flex flex-col items-center">
                  <div
                    class="h-3 w-3 rounded-full"
                    :class="idx === 0 ? 'bg-[#69dca4]' : 'bg-[#1e293b]'"
                  ></div>
                  <div v-if="idx < events.length - 1" class="w-px flex-1 bg-[#1e293b]"></div>
                </div>
                <div class="pb-4 flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-0.5">
                    <p class="text-sm font-medium text-[#dae2fd]">{{ event.status }}</p>
                    <span
                      v-if="idx === 0"
                      class="admin-badge admin-badge-success text-[10px] py-0 px-1.5"
                    >
                      Actual
                    </span>
                  </div>
                  <p v-if="event.description" class="text-sm text-[#94a3b8] mb-0.5">
                    {{ event.description }}
                  </p>
                  <p v-if="event.location_name" class="text-xs text-[#64748b] mb-0.5">
                    <svg class="inline-block mr-1 -mt-0.5" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z" />
                      <circle cx="12" cy="10" r="3" />
                    </svg>
                    {{ event.location_name }}
                  </p>
                  <p class="text-xs text-[#475569]">{{ formatDate(event.recorded_at) }}</p>
                  <p v-if="event.created_by_name" class="text-xs text-[#475569]">
                    por {{ event.created_by_name }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- QR Code card -->
          <div class="admin-card p-6 text-center">
            <h2 class="text-sm font-semibold text-[#dae2fd] mb-4">Código QR</h2>
            <img
              :src="packageService.getQrUrl(pkg.id)"
              alt="QR del paquete"
              width="240"
              height="240"
              class="mx-auto rounded-lg border border-[#1e293b]"
              loading="lazy"
            />
            <p class="mt-3 text-xs text-[#64748b] break-all">{{ trackingUrl }}</p>
            <div class="mt-3 flex items-center justify-center gap-2">
              <button
                @click="goToScan"
                class="admin-btn admin-btn-ghost admin-btn-sm inline-flex items-center gap-1.5"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
                Abrir en scan
              </button>
              <a
                :href="packageService.getQrUrl(pkg.id)"
                :download="`qr-${pkg.tracking_number}.png`"
                class="admin-btn admin-btn-ghost admin-btn-sm inline-flex items-center gap-1.5"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                Descargar QR
              </a>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Not found -->
    <template v-else-if="!loading">
      <div class="admin-card p-12 text-center">
        <svg class="mx-auto mb-3 text-[#64748b]" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <p class="text-sm text-[#94a3b8] mb-1">Paquete no encontrado</p>
        <button class="admin-btn admin-btn-ghost admin-btn-sm mt-3" @click="router.push('/packages')">
          Volver a paquetes
        </button>
      </div>
    </template>

    <!-- Status update modal -->
    <AppModal :visible="showStatusModal" title="Actualizar estado" @close="showStatusModal = false">
      <form class="space-y-4" @submit.prevent="updateStatus">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Nuevo estado</label>
          <select
            v-model="statusForm.status"
            class="admin-input"
            required
            :disabled="savingStatus"
          >
            <option value="" disabled>Seleccionar estado</option>
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Descripción (opcional)</label>
          <textarea
            v-model="statusForm.description"
            class="admin-input"
            rows="3"
            placeholder="Motivo o detalles del cambio de estado..."
            :disabled="savingStatus"
          ></textarea>
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button class="admin-btn admin-btn-ghost" @click="showStatusModal = false" :disabled="savingStatus">
            Cancelar
          </button>
          <button
            class="admin-btn admin-btn-primary"
            @click="updateStatus"
            :disabled="savingStatus || !statusForm.status"
          >
            {{ savingStatus ? 'Guardando...' : 'Actualizar' }}
          </button>
        </div>
      </template>
    </AppModal>
  </div>
</template>
