<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import CompanyInfoTab from '../components/company/CompanyInfoTab.vue'
import BillingConfigTab from '../components/company/BillingConfigTab.vue'
import PageHeader from '../components/ui/PageHeader.vue'

const auth = useAuthStore()

type Tab = 'empresa' | 'facturacion'

const activeTab = ref<Tab>('empresa')
const companyTabRef = ref<InstanceType<typeof CompanyInfoTab> | null>(null)
const billingTabRef = ref<InstanceType<typeof BillingConfigTab> | null>(null)

const canViewCompany = auth.hasPermission('company', 'read')
const canViewBilling = auth.hasPermission('billing_config', 'read')

if (canViewBilling && !canViewCompany) {
  activeTab.value = 'facturacion'
}

function handleSave() {
  if (activeTab.value === 'empresa' && companyTabRef.value) {
    companyTabRef.value.handleSave()
    return
  }
  if (activeTab.value === 'facturacion' && billingTabRef.value) {
    billingTabRef.value.handleSave()
    return
  }
}

onMounted(() => {
  // ensure activeTab is valid
  if (activeTab.value === 'empresa' && !canViewCompany) {
    activeTab.value = 'facturacion'
  }
  if (activeTab.value === 'facturacion' && !canViewBilling) {
    activeTab.value = 'empresa'
  }
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader title="Empresa" subtitle="Configuración general de la empresa y facturación">
      <template #actions>
        <button
          v-if="(activeTab === 'empresa' && companyTabRef?.hasErrors === false) || (activeTab === 'facturacion')"
          class="admin-btn admin-btn-primary sm:w-auto w-full"
          :disabled="(activeTab === 'empresa' && (companyTabRef?.saving || companyTabRef?.hasErrors)) || (activeTab === 'facturacion' && billingTabRef?.saving)"
          @click="handleSave"
        >
          <svg v-if="(activeTab === 'empresa' && !companyTabRef?.saving) || (activeTab === 'facturacion' && !billingTabRef?.saving)" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          {{ ((activeTab === 'empresa' && companyTabRef?.saving) || (activeTab === 'facturacion' && billingTabRef?.saving)) ? 'Guardando...' : 'Guardar' }}
        </button>
      </template>
    </PageHeader>

    <!-- Tabs -->
    <div class="inline-flex items-center gap-1 rounded-lg bg-surface-sunken p-1 shadow-inner">
      <button
        v-if="canViewCompany"
        :class="['rounded-lg px-4 py-2 text-sm font-medium transition-all', activeTab === 'empresa' ? 'bg-primary/15 text-primary' : 'text-subtle hover:text-muted']"
        @click="activeTab = 'empresa'"
      >
        Datos de la empresa
      </button>
      <button
        v-if="canViewBilling"
        :class="['rounded-lg px-4 py-2 text-sm font-medium transition-all', activeTab === 'facturacion' ? 'bg-primary/15 text-primary' : 'text-subtle hover:text-muted']"
        @click="activeTab = 'facturacion'"
      >
        Facturación
      </button>
    </div>

    <CompanyInfoTab v-if="activeTab === 'empresa' && canViewCompany" ref="companyTabRef" />
    <BillingConfigTab v-if="activeTab === 'facturacion' && canViewBilling" ref="billingTabRef" />
  </div>
</template>
