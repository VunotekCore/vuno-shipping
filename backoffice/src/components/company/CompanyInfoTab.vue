<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { companyService, type Company } from '../../services/companyService'
import { useToast } from '../../composables/useToast'
import { getApiUrl } from '../../services/api'
import AppFormField from '../ui/AppFormField.vue'
import AppPhoneInput from '../ui/AppPhoneInput.vue'
import AppRucInput from '../ui/AppRucInput.vue'
import { classifyNicaraguanId } from '../../utils/validation'

const emit = defineEmits<{
  (e: 'saved'): void
}>()

const toast = useToast()

const company = ref<Company | null>(null)
const saving = ref(false)
const uploading = ref(false)
const dragover = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const form = ref({
  name: '',
  logo: '',
  ruc: '',
  address: '',
  phone: '',
  email: '',
  website: '',
})

const fieldErrors = reactive<Record<string, string>>({
  name: '', ruc: '', phone: '', email: '', website: '', address: '',
})

function clearError(key: string) { fieldErrors[key] = '' }

function validateField(key: string) {
  const v = String(form.value[key as keyof typeof form.value] ?? '')
  switch (key) {
    case 'name':
      if (!v.trim()) { fieldErrors.name = 'El nombre es requerido'; return }
      if (v.length > 150) { fieldErrors.name = 'Máximo 150 caracteres'; return }
      fieldErrors.name = ''
      break
    case 'ruc':
      if (v) {
        const { error } = classifyNicaraguanId(v)
        if (error) { fieldErrors.ruc = error; return }
      }
      fieldErrors.ruc = ''
      break
    case 'phone':
      if (v && v.replace(/\D/g, '').length !== 8) { fieldErrors.phone = 'Debe tener 8 dígitos'; return }
      fieldErrors.phone = ''
      break
    case 'email':
      if (v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { fieldErrors.email = 'Email inválido'; return }
      fieldErrors.email = ''
      break
    case 'website':
      if (v.length > 255) { fieldErrors.website = 'Máximo 255 caracteres'; return }
      fieldErrors.website = ''
      break
  }
}

const hasErrors = computed(() => Object.values(fieldErrors).some(Boolean))

function fv(key: string): string | number | null {
  const v = form.value[key as keyof typeof form.value]
  if (v == null) return null
  if (typeof v === 'string' || typeof v === 'number') return v
  return null
}

async function load() {
  try {
    const { data } = await companyService.get()
    company.value = data.data?.company ?? null
    if (company.value) {
      form.value = {
        name: company.value.name || '',
        logo: company.value.logo || '',
        ruc: company.value.ruc || '',
        address: company.value.address || '',
        phone: company.value.phone || '',
        email: company.value.email || '',
        website: company.value.website || '',
      }
    }
  } catch {
    toast.error('Error al cargar datos de la empresa')
  }
}

async function handleSave() {
  for (const key of Object.keys(fieldErrors)) validateField(key)
  if (hasErrors.value) return

  saving.value = true
  try {
    await companyService.update(form.value)
    toast.success('Empresa actualizada')
    await load()
    emit('saved')
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

function openFilePicker() { fileInput.value?.click() }

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.length) { uploadFile(input.files[0]); input.value = '' }
}

function onDrop(e: DragEvent) {
  dragover.value = false
  const file = e.dataTransfer?.files[0]
  if (file) uploadFile(file)
}

function onDragOver(e: DragEvent) { e.preventDefault(); dragover.value = true }
function onDragLeave() { dragover.value = false }

async function uploadFile(file: File) {
  const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml']
  if (!allowed.includes(file.type)) { toast.error('Formato no permitido. Usá JPG, PNG, WebP, GIF o SVG.'); return }
  if (file.size > 10 * 1024 * 1024) { toast.error('El archivo excede 10MB.'); return }

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('folder', 'company')
    const csrf = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/)?.[1] ?? ''
    const res = await fetch(`${getApiUrl()}/imagekit/upload.php`, {
      method: 'POST', body: fd, credentials: 'include',
      headers: { 'X-CSRF-Token': csrf },
    })
    const data = await res.json()
    if (data.success && data.data?.url) {
      form.value.logo = data.data.url
      toast.success('Logo subido')
    } else {
      toast.error(data.message || 'Error al subir imagen')
    }
  } catch {
    toast.error('Error de conexión al subir imagen')
  } finally {
    uploading.value = false
  }
}

function removeLogo() { form.value.logo = '' }

defineExpose({ handleSave, saving, hasErrors, form })

onMounted(() => load())
</script>

<template>
  <div class="admin-card">
    <div class="admin-card-header">
      <h3 class="text-sm font-semibold text-[#dae2fd]">Datos de la empresa</h3>
    </div>
    <div class="admin-card-body">
      <div class="flex flex-col gap-6 lg:flex-row">
        <!-- Logo -->
        <div class="shrink-0 lg:w-48">
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Logo</label>
          <div
            v-if="!form.logo"
            class="relative flex h-24 w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed transition-colors lg:h-32 lg:w-48"
            :class="dragover ? 'border-[#69dca4] bg-[#69dca4]/5' : 'border-[#1e293b] hover:border-[#334155]'"
            @click="openFilePicker" @drop.prevent="onDrop" @dragover="onDragOver" @dragleave="onDragLeave"
          >
            <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" class="hidden" @change="onFileChange" />
            <template v-if="uploading">
              <svg class="h-6 w-6 animate-spin text-[#69dca4]" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
              <span class="text-xs text-[#64748b]">Subiendo...</span>
            </template>
            <template v-else>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="text-[#64748b]"><rect width="18" height="18" x="3" y="3" rx="2" ry="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" /></svg>
              <span class="text-center text-xs text-[#64748b]">Subir logo</span>
            </template>
          </div>
          <div v-else class="group relative h-24 w-full overflow-hidden rounded-lg lg:h-32 lg:w-48">
            <img :src="form.logo" alt="Logo" class="h-full w-full object-contain" />
            <div class="absolute inset-0 flex items-center justify-center gap-1.5 bg-black/60 opacity-0 transition-opacity group-hover:opacity-100">
              <button type="button" @click.stop="openFilePicker" class="rounded-md bg-white/20 p-1.5 text-white backdrop-blur-sm transition-colors hover:bg-white/30" title="Cambiar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button type="button" @click.stop="removeLogo" class="rounded-md bg-[#DC2626]/80 p-1.5 text-white backdrop-blur-sm transition-colors hover:bg-[#DC2626]" title="Eliminar">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
              </button>
            </div>
            <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" class="hidden" @change="onFileChange" />
          </div>
        </div>

        <!-- Form fields -->
        <div class="flex-1 space-y-4">
          <AppFormField
            :model-value="fv('name')"
            @update:model-value="(v: string | number | null) => { form.name = String(v ?? ''); clearError('name') }"
            label="Nombre de la empresa"
            placeholder="Shipping Agency"
            :required="true"
            :disabled="saving"
            :error="fieldErrors.name"
            @blur="validateField('name')"
            @input="clearError('name')"
          />
          <AppRucInput
            :model-value="fv('ruc')"
            @update:model-value="(v: string) => { form.ruc = v; clearError('ruc') }"
            label="RUC"
            helper="Cédula (persona natural) o RUC (persona jurídica)"
            :required="false"
            :disabled="saving"
            :error="fieldErrors.ruc"
            @blur="validateField('ruc')"
          />
          <AppFormField
            :model-value="fv('address')"
            @update:model-value="(v: string | number | null) => { form.address = String(v ?? ''); clearError('address') }"
            label="Dirección"
            type="textarea"
            :rows="2"
            placeholder="Dirección de la empresa"
            :disabled="saving"
            :maxlength="500"
            :error="fieldErrors.address"
            @blur="validateField('address')"
          />
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <AppPhoneInput
              :model-value="fv('phone')"
              @update:model-value="(v: string | number | null) => { form.phone = String(v ?? ''); clearError('phone') }"
              label="Teléfono"
              placeholder="8888 8888"
              :disabled="saving"
              :error="fieldErrors.phone"
              @blur="validateField('phone')"
              @input="clearError('phone')"
            />
            <AppFormField
              :model-value="fv('email')"
              @update:model-value="(v: string | number | null) => { form.email = String(v ?? ''); clearError('email') }"
              label="Email de contacto"
              type="email"
              placeholder="info@empresa.com"
              :disabled="saving"
              :error="fieldErrors.email"
              @blur="validateField('email')"
              @input="clearError('email')"
            />
          </div>
          <AppFormField
            :model-value="fv('website')"
            @update:model-value="(v: string | number | null) => { form.website = String(v ?? ''); clearError('website') }"
            label="Sitio web"
            placeholder="https://ejemplo.com"
            :disabled="saving"
            :maxlength="255"
            :error="fieldErrors.website"
            @blur="validateField('website')"
            @input="clearError('website')"
          />
        </div>
      </div>
    </div>
  </div>
</template>