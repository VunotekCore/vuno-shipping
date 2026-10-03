<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { billingConfigService } from '../services/billingConfigService'
import { companyService, type Company } from '../services/companyService'
import { useToast } from '../composables/useToast'
import AppFormField from '../components/ui/AppFormField.vue'

const toast = useToast()
const savingConfig = ref(false)

const configForm = reactive({
  tax_enabled: false,
  tax_rate: 15,
  show_client_ruc: false,
  exchange_rate_usd: 36,
  invoice_prefix: 'FAC',
})

const company = ref<Company | null>(null)
const savingCompany = ref(false)

const companyForm = reactive({
  name: '',
  ruc: '',
  address: '',
  phone: '',
  email: '',
})

const companyFieldErrors = reactive<Record<string, string>>({
  name: '', ruc: '', phone: '', email: '', address: '',
})

function clearCompanyError(key: string) { companyFieldErrors[key] = '' }

function validateCompany(key: string) {
  const v = String(companyForm[key as keyof typeof companyForm] ?? '')
  switch (key) {
    case 'name':
      if (!v.trim()) { companyFieldErrors.name = 'El nombre es requerido'; return }
      if (v.length > 150) { companyFieldErrors.name = 'Máximo 150 caracteres'; return }
      companyFieldErrors.name = ''
      break
    case 'ruc':
      if (v.length > 30) { companyFieldErrors.ruc = 'Máximo 30 caracteres'; return }
      companyFieldErrors.ruc = ''
      break
    case 'phone':
      if (v && v.replace(/\D/g, '').length < 7) { companyFieldErrors.phone = 'Teléfono inválido'; return }
      companyFieldErrors.phone = ''
      break
    case 'email':
      if (v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { companyFieldErrors.email = 'Email inválido'; return }
      companyFieldErrors.email = ''
      break
  }
}

const hasCompanyErrors = computed(() => Object.values(companyFieldErrors).some(Boolean))

const taxEnabled = computed(() => configForm.tax_enabled)

async function load() {
  try {
    const { data } = await billingConfigService.get()
    const c = data.data?.config
    if (c) {
      configForm.tax_enabled = !!c.tax_enabled
      configForm.tax_rate = c.tax_rate
      configForm.show_client_ruc = !!c.show_client_ruc
      configForm.exchange_rate_usd = c.exchange_rate_usd
      configForm.invoice_prefix = c.invoice_prefix
    }
    const res = await companyService.get()
    company.value = res.data.data?.company ?? null
    if (company.value) {
      companyForm.name = company.value.name || ''
      companyForm.ruc = company.value.ruc || ''
      companyForm.address = company.value.address || ''
      companyForm.phone = company.value.phone || ''
      companyForm.email = company.value.email || ''
    }
  } catch {
    toast.error('Error al cargar la configuración')
  }
}

async function saveConfig() {
  if (!configForm.exchange_rate_usd || configForm.exchange_rate_usd <= 0) {
    toast.error('El tipo de cambio debe ser mayor a 0')
    return
  }
  savingConfig.value = true
  try {
    await billingConfigService.update({
      tax_enabled: configForm.tax_enabled ? 1 : 0,
      tax_rate: configForm.tax_rate,
      show_client_ruc: configForm.show_client_ruc ? 1 : 0,
      exchange_rate_usd: configForm.exchange_rate_usd,
      invoice_prefix: configForm.invoice_prefix || 'FAC',
    })
    toast.success('Configuración de facturación guardada')
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al guardar')
  } finally {
    savingConfig.value = false
  }
}

async function saveCompany() {
  for (const key of Object.keys(companyFieldErrors)) validateCompany(key)
  if (hasCompanyErrors.value) return

  savingCompany.value = true
  try {
    await companyService.update({
      name: companyForm.name,
      ruc: companyForm.ruc,
      address: companyForm.address,
      phone: companyForm.phone,
      email: companyForm.email,
    })
    toast.success('Datos de empresa actualizados')
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al guardar')
  } finally {
    savingCompany.value = false
  }
}

function fv(key: string): string | number | null {
  const v = companyForm[key as keyof typeof companyForm]
  if (v == null) return null
  return typeof v === 'string' || typeof v === 'number' ? v : null
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-bold text-[#dae2fd]">Configuración de facturación</h1>
        <p class="mt-1 text-sm text-[#64748b]">Impuestos, tipo de cambio y datos de la empresa en la factura</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Billing config -->
      <div class="admin-card p-6">
        <h2 class="text-sm font-semibold text-[#dae2fd] mb-4">Parámetros de facturación</h2>

        <div class="space-y-5">
          <!-- IVA toggle -->
          <div class="flex items-center justify-between rounded-xl border border-[#1e293b] p-4">
            <div>
              <p class="text-sm font-medium text-[#dae2fd]">Aplicar IVA</p>
              <p class="text-xs text-[#64748b] mt-0.5">Agrega el impuesto al total de cada factura</p>
            </div>
            <button
              type="button"
              role="switch"
              :aria-checked="configForm.tax_enabled"
              class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
              :class="configForm.tax_enabled ? 'bg-[#22c55e]' : 'bg-[#334155]'"
              @click="configForm.tax_enabled = !configForm.tax_enabled"
            >
              <span
                class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition-all"
                :class="configForm.tax_enabled ? 'left-[22px]' : 'left-0.5'"
              />
            </button>
          </div>

          <div v-if="taxEnabled">
            <AppFormField
              :model-value="configForm.tax_rate"
              @update:model-value="(v: string | number | null) => configForm.tax_rate = Number(v ?? 15)"
              label="Tasa de IVA (%)"
              type="number"
              placeholder="15"
              :disabled="savingConfig"
            />
          </div>

          <!-- RUC toggle -->
          <div class="flex items-center justify-between rounded-xl border border-[#1e293b] p-4">
            <div>
              <p class="text-sm font-medium text-[#dae2fd]">Mostrar RUC del cliente</p>
              <p class="text-xs text-[#64748b] mt-0.5">Imprime el RUC del cliente en la factura si fue cargado</p>
            </div>
            <button
              type="button"
              role="switch"
              :aria-checked="configForm.show_client_ruc"
              class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
              :class="configForm.show_client_ruc ? 'bg-[#22c55e]' : 'bg-[#334155]'"
              @click="configForm.show_client_ruc = !configForm.show_client_ruc"
            >
              <span
                class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition-all"
                :class="configForm.show_client_ruc ? 'left-[22px]' : 'left-0.5'"
              />
            </button>
          </div>

          <AppFormField
            :model-value="configForm.exchange_rate_usd"
            @update:model-value="(v: string | number | null) => configForm.exchange_rate_usd = Number(v ?? 36)"
            label="Tipo de cambio (C$ por USD)"
            type="number"
            :step="0.01"
            placeholder="36.00"
            helper="Usado al cobrar facturas en córdobas"
            :disabled="savingConfig"
          />

          <AppFormField
            :model-value="configForm.invoice_prefix"
            @update:model-value="(v: string | number | null) => configForm.invoice_prefix = String(v ?? 'FAC')"
            label="Prefijo de factura"
            placeholder="FAC"
            :maxlength="10"
            helper="Ej: FAC-000001"
            :disabled="savingConfig"
          />

          <button
            class="admin-btn admin-btn-primary w-full"
            :disabled="savingConfig"
            @click="saveConfig"
          >
            {{ savingConfig ? 'Guardando...' : 'Guardar configuración' }}
          </button>
        </div>
      </div>

      <!-- Company info -->
      <div class="admin-card p-6">
        <h2 class="text-sm font-semibold text-[#dae2fd] mb-4">Empresa (aparece en la factura impresa)</h2>

        <div class="space-y-4">
          <AppFormField
            :model-value="fv('name')"
            @update:model-value="(v: string | number | null) => { companyForm.name = String(v ?? ''); clearCompanyError('name') }"
            label="Nombre de la empresa"
            placeholder="Shipping Agency"
            :required="true"
            :disabled="savingCompany"
            :error="companyFieldErrors.name"
            @blur="validateCompany('name')"
            @input="clearCompanyError('name')"
          />
          <AppFormField
            :model-value="fv('ruc')"
            @update:model-value="(v: string | number | null) => { companyForm.ruc = String(v ?? ''); clearCompanyError('ruc') }"
            label="RUC"
            placeholder="J00000001"
            :disabled="savingCompany"
            :maxlength="30"
            :error="companyFieldErrors.ruc"
            @blur="validateCompany('ruc')"
          />
          <AppFormField
            :model-value="fv('address')"
            @update:model-value="(v: string | number | null) => { companyForm.address = String(v ?? ''); clearCompanyError('address') }"
            label="Dirección"
            type="textarea"
            :rows="2"
            placeholder="Dirección de la empresa"
            :disabled="savingCompany"
            :maxlength="500"
            :error="companyFieldErrors.address"
            @blur="validateCompany('address')"
          />
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <AppFormField
              :model-value="fv('phone')"
              @update:model-value="(v: string | number | null) => { companyForm.phone = String(v ?? ''); clearCompanyError('phone') }"
              label="Teléfono"
              placeholder="1234-5678"
              :disabled="savingCompany"
              :error="companyFieldErrors.phone"
              @blur="validateCompany('phone')"
            />
            <AppFormField
              :model-value="fv('email')"
              @update:model-value="(v: string | number | null) => { companyForm.email = String(v ?? ''); clearCompanyError('email') }"
              label="Email de contacto"
              type="email"
              placeholder="info@empresa.com"
              :disabled="savingCompany"
              :error="companyFieldErrors.email"
              @blur="validateCompany('email')"
            />
          </div>

          <button
            class="admin-btn admin-btn-primary w-full"
            :disabled="savingCompany || hasCompanyErrors"
            @click="saveCompany"
          >
            {{ savingCompany ? 'Guardando...' : 'Guardar empresa' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>