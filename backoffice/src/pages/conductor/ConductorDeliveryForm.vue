<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { routeRunService, routeStopService, type RouteStop } from '../../services/routeService'
import { useToast } from '../../composables/useToast'
import { driverLocationService } from '../../services/routeService'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const stopId = computed(() => Number(route.params.stopId))

const loading = ref(true)
const stop = ref<RouteStop | null>(null)
const error = ref('')
const saving = ref(false)
const step = ref<'form' | 'signature' | 'photo' | 'confirm'>('form')

const notes = ref('')
const signatureData = ref('')
const photoData = ref('')
const useCamera = ref(true)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const videoRef = ref<HTMLVideoElement | null>(null)
const streamRef = ref<MediaStream | null>(null)
const photoPreview = ref('')
const currentPosition = ref<{ lat: number; lng: number } | null>(null)

async function load() {
  loading.value = true
  try {
    const res = await driverLocationService.driverRoutes()
    const runs: any[] = res.data.data.runs ?? []
    const active = runs.find(r => r.status_name === 'active') || runs[0]
    if (!active) { error.value = 'Sin ruta activa'; return }

    const detail = await routeRunService.get(active.id)
    const stops: RouteStop[] = detail.data.data.run.stops ?? []
    stop.value = stops.find(s => s.id === stopId.value) ?? null
    if (!stop.value) error.value = 'Parada no encontrada'

    // Get current position
    navigator.geolocation.getCurrentPosition(
      (pos) => { currentPosition.value = { lat: pos.coords.latitude, lng: pos.coords.longitude } },
      () => {},
      { enableHighAccuracy: true, timeout: 10000 }
    )
  } catch (e: any) {
    error.value = e?.response?.data?.error || 'Error al cargar'
  } finally {
    loading.value = false
  }
}

// Signature canvas
function initCanvas() {
  if (!canvasRef.value) return setTimeout(() => initCanvas(), 100)
  const canvas = canvasRef.value
  const ctx = canvas.getContext('2d')!
  ctx.strokeStyle = '#000'
  ctx.lineWidth = 2
  ctx.lineCap = 'round'

  let drawing = false

  canvas.addEventListener('pointerdown', () => { drawing = true; ctx.beginPath() })
  canvas.addEventListener('pointerup', () => { drawing = false })
  canvas.addEventListener('pointermove', (e) => {
    if (!drawing) return
    const rect = canvas.getBoundingClientRect()
    ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top)
    ctx.stroke()
  })
}

function clearSignature() {
  const canvas = canvasRef.value
  if (!canvas) return
  canvas.getContext('2d')!.clearRect(0, 0, canvas.width, canvas.height)
  signatureData.value = ''
}

function saveSignature() {
  const canvas = canvasRef.value
  if (!canvas) return
  const dataUrl = canvas.toDataURL('image/png')
  // Check if signature is empty
  const pixels = canvas.getContext('2d')!.getImageData(0, 0, canvas.width, canvas.height).data
  const hasDrawing = pixels.some((p, i) => p !== 0 && i % 4 === 3)
  if (!hasDrawing) {
    toast.error('Debe dibujar una firma')
    return
  }
  signatureData.value = dataUrl
  step.value = 'photo'
}

// Camera
function startCamera() {
  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then((stream) => {
      streamRef.value = stream
      useCamera.value = true
      if (videoRef.value) videoRef.value.srcObject = stream
    })
    .catch(() => {
      useCamera.value = false
    })
}

function capturePhoto() {
  if (!videoRef.value) return
  const canvas = document.createElement('canvas')
  canvas.width = videoRef.value.videoWidth
  canvas.height = videoRef.value.videoHeight
  canvas.getContext('2d')!.drawImage(videoRef.value, 0, 0)
  photoData.value = canvas.toDataURL('image/jpeg', 0.8)
  photoPreview.value = photoData.value
  stopCamera()
  step.value = 'confirm'
}

function stopCamera() {
  if (streamRef.value) {
    streamRef.value.getTracks().forEach(t => t.stop())
    streamRef.value = null
  }
}

function selectPhoto(event: Event) {
  const input = event.target as HTMLInputElement
  if (!input.files?.[0]) return
  const reader = new FileReader()
  reader.onload = (e) => {
    photoData.value = e.target?.result as string
    photoPreview.value = photoData.value
    step.value = 'confirm'
  }
  reader.readAsDataURL(input.files[0])
}

function skipPhoto() {
  photoData.value = ''
  photoPreview.value = ''
  step.value = 'confirm'
}

async function submitDelivery() {
  if (!stop.value) return
  saving.value = true

  try {
    const payload: any = {
      id: stop.value.id,
      notes: notes.value || null,
      latitude: currentPosition.value?.lat ?? null,
      longitude: currentPosition.value?.lng ?? null,
    }

    if (signatureData.value) {
      await routeStopService.uploadSignature(stop.value.id, signatureData.value)
    }

    if (photoData.value) {
      // Convert base64 to blob and upload
      const res = await fetch(photoData.value)
      const blob = await res.blob()
      const file = new File([blob], `delivery_${stop.value.id}.jpg`, { type: 'image/jpeg' })
      await routeStopService.uploadPhoto(stop.value.id, file)
    }

    await routeStopService.deliver(payload)
    toast.success('Entrega registrada exitosamente')
    router.push('/conductor')
  } catch (e: any) {
    toast.error(e?.response?.data?.error || 'Error al registrar entrega')
  } finally {
    saving.value = false
  }
}

function goBack() {
  stopCamera()
  router.push(`/conductor/stop/${stopId.value}`)
}

onMounted(() => {
  load()
  nextTick(() => initCanvas())
})

onUnmounted(() => {
  stopCamera()
})
</script>

<template>
  <div class="space-y-4">
    <button class="flex items-center gap-2 text-sm text-[#94a3b8] hover:text-[#dae2fd]" @click="goBack">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Volver
    </button>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="h-8 w-8 animate-spin rounded-full border-2 border-[#69dca4] border-t-transparent" />
    </div>

    <div v-else-if="error" class="admin-card p-6 text-center">
      <p class="text-sm text-[#DC2626]">{{ error }}</p>
    </div>

    <template v-else-if="stop">
      <h1 class="text-lg font-bold">Entregar paquete</h1>
      <p class="text-sm text-[#64748b]">Parada #{{ stop.stop_order }} — {{ stop.client_name }}</p>

      <!-- Step indicator -->
      <div class="flex items-center gap-2 text-xs text-[#64748b]">
        <span :class="step === 'form' ? 'text-[#69dca4] font-medium' : ''">1. Notas</span>
        <span class="text-[#1e293b]">→</span>
        <span :class="step === 'signature' ? 'text-[#69dca4] font-medium' : ''">2. Firma</span>
        <span class="text-[#1e293b]">→</span>
        <span :class="step === 'photo' ? 'text-[#69dca4] font-medium' : ''">3. Foto</span>
        <span class="text-[#1e293b]">→</span>
        <span :class="step === 'confirm' ? 'text-[#69dca4] font-medium' : ''">4. Confirmar</span>
      </div>

      <!-- Step 1: Notes -->
      <div v-if="step === 'form'" class="admin-card p-5 space-y-4">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Notas de la entrega</label>
          <textarea v-model="notes" class="admin-input min-h-[100px]" placeholder="Observaciones sobre la entrega..." :disabled="saving" />
        </div>
        <button class="w-full rounded-lg bg-[#69dca4] py-3 text-sm font-semibold text-[#0b1326] transition-opacity hover:opacity-90" @click="step = 'signature'">
          Siguiente — Firma
        </button>
      </div>

      <!-- Step 2: Signature -->
      <div v-if="step === 'signature'" class="admin-card p-5 space-y-4">
        <p class="text-sm font-medium">Firma del cliente</p>
        <div class="rounded-lg border-2 border-dashed border-[#334155] bg-white overflow-hidden">
          <canvas ref="canvasRef" width="600" height="200" class="w-full touch-none" style="cursor: crosshair" />
        </div>
        <div class="flex gap-3">
          <button class="flex-1 rounded-lg border border-[#334155] py-2.5 text-sm text-[#94a3b8] transition-colors hover:bg-white/[0.04]" @click="clearSignature">
            Limpiar
          </button>
          <button class="flex-1 rounded-lg bg-[#69dca4] py-2.5 text-sm font-semibold text-[#0b1326] transition-opacity hover:opacity-90" @click="saveSignature">
            Continuar
          </button>
        </div>
      </div>

      <!-- Step 3: Photo -->
      <div v-if="step === 'photo'" class="admin-card p-5 space-y-4">
        <p class="text-sm font-medium">Foto de evidencia</p>

        <!-- Camera -->
        <div v-if="useCamera" class="overflow-hidden rounded-lg border border-[#1e293b] bg-black">
          <video ref="videoRef" autoplay playsinline class="w-full" />
        </div>

        <div class="flex flex-wrap gap-3">
          <button v-if="useCamera" class="flex-1 rounded-lg bg-[#69dca4] py-2.5 text-sm font-semibold text-[#0b1326]" @click="capturePhoto">
            Tomar foto
          </button>
          <button v-else class="flex-1 rounded-lg border border-[#334155] py-2.5 text-sm text-[#94a3b8]" @click="startCamera">
            Activar cámara
          </button>
          <label class="flex-1 cursor-pointer rounded-lg border border-[#334155] py-2.5 text-center text-sm text-[#94a3b8]">
            Subir archivo
            <input type="file" accept="image/*" class="hidden" @change="selectPhoto" />
          </label>
          <button class="flex-1 rounded-lg border border-[#334155] py-2.5 text-sm text-[#64748b]" @click="skipPhoto">
            Saltar
          </button>
        </div>
      </div>

      <!-- Step 4: Confirm -->
      <div v-if="step === 'confirm'" class="space-y-4">
        <div class="admin-card p-5 space-y-3">
          <p class="text-sm font-medium">Resumen de la entrega</p>

          <div class="flex items-center gap-2 text-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-[#22c55e]"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Firma: {{ signatureData ? 'Capturada' : 'No incluida' }}</span>
          </div>
          <div class="flex items-center gap-2 text-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :class="photoPreview ? 'text-[#22c55e]' : 'text-[#64748b]'"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Foto: {{ photoPreview ? 'Capturada' : 'No incluida' }}</span>
          </div>
          <div v-if="notes" class="flex items-center gap-2 text-sm">
            <span>Notas: {{ notes }}</span>
          </div>
          <div v-if="currentPosition" class="flex items-center gap-2 text-sm text-[#64748b]">
            <span>GPS: {{ currentPosition.lat.toFixed(6) }}, {{ currentPosition.lng.toFixed(6) }}</span>
          </div>
        </div>

        <div v-if="photoPreview" class="admin-card p-3">
          <img :src="photoPreview" alt="Evidencia" class="w-full rounded-lg" />
        </div>

        <button
          class="w-full rounded-lg bg-[#69dca4] py-3 text-sm font-semibold text-[#0b1326] transition-opacity hover:opacity-90 disabled:opacity-50"
          :disabled="saving"
          @click="submitDelivery"
        >
          {{ saving ? 'Registrando...' : 'Confirmar entrega' }}
        </button>
      </div>
    </template>
  </div>
</template>
