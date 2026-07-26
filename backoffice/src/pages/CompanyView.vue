<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { companyService, type Company } from '../services/companyService'
import { useToast } from '../composables/useToast'
import ImageUpload from '../components/ui/ImageUpload.vue'

const toast = useToast()

const company = ref<Company | null>(null)
const saving = ref(false)
const form = ref({
  name: '',
  logo: '',
  ruc: '',
  address: '',
  phone: '',
  email: '',
  website: '',
})

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

async function save() {
  if (!form.value.name) {
    toast.error('El nombre de la empresa es requerido')
    return
  }

  saving.value = true
  try {
    await companyService.update(form.value)
    toast.success('Empresa actualizada')
    await load()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-bold text-[#dae2fd]">Empresa</h1>
      <p class="mt-1 text-sm text-[#64748b]">Configuración general de la empresa</p>
    </div>

    <div class="admin-card max-w-2xl space-y-6">
      <!-- Logo -->
      <ImageUpload
        v-model="form.logo"
        folder="company"
        label="Logo de la empresa"
      />

      <!-- Datos generales -->
      <div class="space-y-4">
        <h3 class="text-sm font-semibold text-[#dae2fd]">Datos generales</h3>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Nombre *</label>
          <input v-model="form.name" class="admin-input" placeholder="Nombre de la empresa" required :disabled="saving" />
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">RUC / NIT</label>
          <input v-model="form.ruc" class="admin-input" placeholder="0000000000000" :disabled="saving" />
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Dirección</label>
          <textarea v-model="form.address" class="admin-input" rows="2" placeholder="Dirección completa" :disabled="saving"></textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Teléfono</label>
            <input v-model="form.phone" class="admin-input" placeholder="+505 8888-8888" :disabled="saving" />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Email</label>
            <input v-model="form.email" type="email" class="admin-input" placeholder="info@empresa.com" :disabled="saving" />
          </div>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-[#94a3b8]">Website</label>
          <input v-model="form.website" class="admin-input" placeholder="https://empresa.com" :disabled="saving" />
        </div>
      </div>

      <div class="flex justify-end border-t border-[#1e293b] pt-4">
        <button class="admin-btn admin-btn-primary" @click="save" :disabled="saving || !form.name">
          {{ saving ? 'Guardando...' : 'Guardar cambios' }}
        </button>
      </div>
    </div>
  </div>
</template>
