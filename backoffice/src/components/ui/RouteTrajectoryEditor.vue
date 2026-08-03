<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, nextTick, computed } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const props = withDefaults(defineProps<{
  modelValue: Array<[number, number]>
  color?: string
  disabled?: boolean
}>(), {
  color: '#22c55e',
  disabled: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: Array<[number, number]>): void
}>()

const mapContainer = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let polyline: L.Polyline | null = null
let startMarker: L.CircleMarker | null = null
let endMarker: L.CircleMarker | null = null

const points = computed(() => props.modelValue)

function toLatLng(p: [number, number]): [number, number] {
  return [p[1], p[0]]
}

function initMap() {
  if (!mapContainer.value) return
  map = L.map(mapContainer.value, {
    zoomControl: true,
    attributionControl: false,
  }).setView([12.1149, -86.2362], 8)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
  }).addTo(map)

  map.on('click', (e: L.LeafletMouseEvent) => {
    if (props.disabled) return
    const lng = Number(e.latlng.lng)
    const lat = Number(e.latlng.lat)
    if (Number.isNaN(lng) || Number.isNaN(lat)) return
    emit('update:modelValue', [...points.value, [lng, lat]])
  })

  setTimeout(() => map?.invalidateSize(), 300)
}

function redraw(fitBounds = false) {
  if (!map) return
  if (polyline) { polyline.remove(); polyline = null }
  if (startMarker) { startMarker.remove(); startMarker = null }
  if (endMarker) { endMarker.remove(); endMarker = null }

  if (points.value.length >= 2) {
    const latlngs: [number, number][] = points.value.map(toLatLng)
    polyline = L.polyline(latlngs, {
      color: props.color,
      weight: 4,
      opacity: 0.85,
    }).addTo(map)
    if (fitBounds) {
      map.fitBounds(polyline.getBounds(), { padding: [40, 40], maxZoom: 14 })
    }
  }

  if (points.value.length > 0) {
    const first = toLatLng(points.value[0])
    startMarker = L.circleMarker(first, {
      radius: 7,
      fillColor: '#22c55e',
      color: '#fff',
      weight: 2,
      fillOpacity: 1,
    }).addTo(map).bindPopup('Inicio')

    const last = toLatLng(points.value[points.value.length - 1])
    endMarker = L.circleMarker(last, {
      radius: 7,
      fillColor: '#ef4444',
      color: '#fff',
      weight: 2,
      fillOpacity: 1,
    }).addTo(map).bindPopup('Fin')
  }
}

watch(() => props.modelValue, () => redraw(false), { deep: true })

watch(() => props.color, () => {
  if (polyline) polyline.setStyle({ color: props.color })
})

function undo() {
  if (props.disabled || points.value.length === 0) return
  emit('update:modelValue', points.value.slice(0, -1))
}

function clear() {
  if (props.disabled) return
  emit('update:modelValue', [])
}

onMounted(async () => {
  await nextTick()
  initMap()
  redraw(true)
})

onBeforeUnmount(() => {
  if (map) {
    map.off('click')
    map.remove()
    map = null
  }
})
</script>

<template>
  <div>
    <div class="mb-2 flex items-center justify-between gap-3">
      <p class="text-xs text-[#64748b]">
        {{ points.length === 0 ? 'Hacé clic en el mapa para dibujar la trayectoria' : `${points.length} punto${points.length === 1 ? '' : 's'}` }}
      </p>
      <div class="flex shrink-0 gap-2">
        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" :disabled="disabled || points.length === 0" @click="undo">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
          Deshacer
        </button>
        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm text-[#DC2626]" :disabled="disabled || points.length === 0" @click="clear">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          Limpiar
        </button>
      </div>
    </div>
    <div ref="mapContainer" class="h-[280px] w-full overflow-hidden rounded-xl border border-[#1e293b]" />
    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#94a3b8]">
      <span class="inline-flex items-center gap-1.5"><span class="h-1 w-5 rounded" :style="{ background: color }" /> Trayectoria</span>
      <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#22c55e" /> Inicio</span>
      <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#ef4444" /> Fin</span>
    </div>
  </div>
</template>
