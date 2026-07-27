<script setup lang="ts">
import { ref, watch } from 'vue'
import { useToast } from '../../composables/useToast'
import { getApiUrl } from '../../services/api'

const props = defineProps<{
  modelValue: string
  folder?: string
  label?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  uploaded: [data: { url: string; fileId: string }]
  deleted: []
}>()

const toast = useToast()

const preview = ref(props.modelValue)
const uploading = ref(false)
const dragover = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

watch(() => props.modelValue, (val) => {
  preview.value = val
})

function openFilePicker() {
  fileInput.value?.click()
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.length) {
    uploadFile(input.files[0])
    input.value = ''
  }
}

function onDrop(e: DragEvent) {
  dragover.value = false
  const file = e.dataTransfer?.files[0]
  if (file) uploadFile(file)
}

function onDragOver(e: DragEvent) {
  e.preventDefault()
  dragover.value = true
}

function onDragLeave() {
  dragover.value = false
}

async function uploadFile(file: File) {
  const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml']
  if (!allowed.includes(file.type)) {
    toast.error('Formato no permitido. Usá JPG, PNG, WebP, GIF o SVG.')
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    toast.error('El archivo excede 10MB.')
    return
  }

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('folder', props.folder || 'uploads')

    const csrf = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/)?.[1] ?? ''

    const res = await fetch(`${getApiUrl()}/imagekit/upload.php`, {
      method: 'POST',
      body: fd,
      credentials: 'include',
      headers: { 'X-CSRF-Token': csrf },
    })

    const data = await res.json()

    if (data.success && data.data?.url) {
      preview.value = data.data.url
      emit('update:modelValue', data.data.url)
      emit('uploaded', { url: data.data.url, fileId: data.data.fileId })
      toast.success('Imagen subida')
    } else {
      toast.error(data.message || 'Error al subir imagen')
    }
  } catch {
    toast.error('Error de conexión al subir imagen')
  } finally {
    uploading.value = false
  }
}

function removeImage() {
  preview.value = ''
  emit('update:modelValue', '')
  emit('deleted')
}
</script>

<template>
  <div>
    <label v-if="label" class="mb-1.5 block text-sm font-medium text-[#94a3b8]">{{ label }}</label>

    <div
      v-if="!preview"
      class="relative flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 transition-colors"
      :class="dragover ? 'border-[#69dca4] bg-[#69dca4]/5' : 'border-[#1e293b] hover:border-[#334155]'"
      role="button"
      tabindex="0"
      aria-label="Subir imagen"
      @click="openFilePicker"
      @keydown.enter="openFilePicker"
      @keydown.space.prevent="openFilePicker"
      @drop.prevent="onDrop"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
    >
      <input
        ref="fileInput"
        type="file"
        accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
        class="hidden"
        @change="onFileChange"
      />

      <div v-if="uploading" class="flex flex-col items-center gap-2">
        <svg class="h-8 w-8 animate-spin text-[#69dca4]" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <span class="text-sm text-[#94a3b8]">Subiendo...</span>
      </div>

      <div v-else class="flex flex-col items-center gap-2">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-[#64748b]">
          <rect width="18" height="18" x="3" y="3" rx="2" ry="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
        </svg>
        <span class="text-sm text-[#94a3b8]">
          <span class="font-medium text-[#69dca4]">Click para subir</span> o arrastrá una imagen
        </span>
        <span class="text-xs text-[#64748b]">JPG, PNG, WebP, GIF, SVG — Max 10MB</span>
      </div>
    </div>

    <div v-else class="relative overflow-hidden rounded-lg">
      <img :src="preview" :alt="label || 'Vista previa'" class="h-40 w-full object-cover" />
      <div class="absolute inset-0 flex items-center justify-center gap-2 bg-black/50 opacity-0 transition-opacity hover:opacity-100">
        <button type="button" @click.stop="openFilePicker" class="rounded-lg bg-white/20 p-2 text-white backdrop-blur-sm transition-colors hover:bg-white/30" aria-label="Cambiar imagen">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button type="button" @click.stop="removeImage" class="rounded-lg bg-[#DC2626]/80 p-2 text-white backdrop-blur-sm transition-colors hover:bg-[#DC2626]" aria-label="Eliminar imagen">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
        </button>
      </div>
      <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" class="hidden" @change="onFileChange" />
    </div>
  </div>
</template>
