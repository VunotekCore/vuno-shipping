<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { billingConfigService } from '../../services/billingConfigService'
import { useToast } from '../../composables/useToast'
import AppFormField from '../ui/AppFormField.vue'
import { useAuthStore } from '../../stores/auth'

const emit = defineEmits<{
  (e: 'saved'): void
}>()

const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const saving = ref(false)

const configForm = reactive({
  tax_enabled: false,
  tax_rate: 15,
  show_client_ruc: false,
  exchange_rate_usd: 36,
})

const invoiceSeq = ref<{ exists: boolean; prefix: string; current_number: number; next_preview: string }>({
  exists: true,
  prefix: 'FAC',
  current_number: 0,
  next_preview: 'FAC-000001',
})

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
    }
    const s = data.data?.invoice_sequence
    if (s) {
      invoiceSeq.value = s
    }
  } catch {
    toast.error('Error al cargar la configuración de facturación')
  }
}

async function handleSave() {
  if (!configForm.exchange_rate_usd || configForm.exchange_rate_usd <= 0) {
    toast.error('El tipo de cambio debe ser mayor a 0')
    return
  }
  saving.value = true
  try {
    const { data } = await billingConfigService.update({
      tax_enabled: configForm.tax_enabled ? 1 : 0,
      tax_rate: configForm.tax_rate,
      show_client_ruc: configForm.show_client_ruc ? 1 : 0,
      exchange_rate_usd: configForm.exchange_rate_usd,
    })
    toast.success('Configuración de facturación guardada')
    const s = data.data?.invoice_sequence
    if (s) invoiceSeq.value = s
    emit('saved')
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'Error al guardar')
  } finally {
    saving.value = false
  }
}

function goToSequences() {
  router.push('/document-sequences')
}

defineExpose({ handleSave, saving })

onMounted(() => load())
</script>

<template>
  <div class="admin-card p-6">
    <h2 class="text-sm font-semibold text-content mb-4">Parámetros de facturación</h2>

    <div class="space-y-5">
      <!-- IVA toggle -->
      <div class="flex items-center justify-between rounded-xl border border-line p-4">
        <div>
          <p class="text-sm font-medium text-content">Aplicar IVA</p>
          <p class="text-xs text-subtle mt-0.5">Agrega el impuesto al total de cada factura</p>
        </div>
        <button
          type="button"
          role="switch"
          :aria-checked="configForm.tax_enabled"
          class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
          :class="configForm.tax_enabled ? 'bg-success' : 'bg-line-strong'"
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
          :disabled="saving"
        />
      </div>

      <!-- RUC toggle -->
      <div class="flex items-center justify-between rounded-xl border border-line p-4">
        <div>
          <p class="text-sm font-medium text-content">Mostrar RUC del cliente</p>
          <p class="text-xs text-subtle mt-0.5">Imprime el RUC del cliente en la factura si fue cargado</p>
        </div>
        <button
          type="button"
          role="switch"
          :aria-checked="configForm.show_client_ruc"
          class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
          :class="configForm.show_client_ruc ? 'bg-success' : 'bg-line-strong'"
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
        :disabled="saving"
      />

      <!-- Prefijo de factura (solo lectura, fuente: document_sequence) -->
      <div class="rounded-xl border border-line p-4 space-y-2">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm font-medium text-content">Prefijo de factura</p>
            <p class="text-xs text-subtle mt-0.5">La numeración de facturas se gestiona en Secuencias.</p>
          </div>
          <button
            v-if="auth.hasPermission('document_sequence', 'read')"
            class="admin-btn admin-btn-ghost admin-btn-sm"
            @click="goToSequences"
          >
            Administrar en Secuencias
          </button>
        </div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
          <span class="inline-flex items-center rounded-lg bg-surface-raised px-2.5 py-1 font-mono text-content">
            {{ invoiceSeq.prefix || 'FAC' }}
          </span>
          <span class="text-subtle">Próxima factura:</span>
          <span class="font-mono text-primary">{{ invoiceSeq.next_preview }}</span>
        </div>
        <p v-if="!invoiceSeq.exists" class="text-xs text-danger">
          No se encontró la secuencia "invoice". Creála en Secuencias.
        </p>
      </div>
    </div>
  </div>
</template>