<script setup lang="ts">
import { ref, watch } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { profileService } from '../../services/profileService'
import AppModal from './AppModal.vue'

const props = defineProps<{
  visible: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const auth = useAuthStore()

const name = ref('')
const email = ref('')
const currentPassword = ref('')
const newPassword = ref('')
const showPasswordSection = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')

watch(() => props.visible, (v) => {
  if (v) {
    name.value = auth.user?.name ?? ''
    email.value = auth.user?.email ?? ''
    currentPassword.value = ''
    newPassword.value = ''
    showPasswordSection.value = false
    error.value = ''
    success.value = ''
  }
})

async function handleSave() {
  error.value = ''
  success.value = ''
  saving.value = true

  try {
    const payload: Record<string, string> = {}

    if (name.value !== auth.user?.name) {
      payload.name = name.value
    }
    if (email.value !== auth.user?.email) {
      payload.email = email.value
    }
    if (showPasswordSection.value && newPassword.value) {
      payload.current_password = currentPassword.value
      payload.new_password = newPassword.value
    }

    if (Object.keys(payload).length === 0) {
      error.value = 'No hay cambios para guardar'
      saving.value = false
      return
    }

    if (payload.new_password && !payload.current_password) {
      error.value = 'La contraseña actual es requerida para cambiar la contraseña'
      saving.value = false
      return
    }

    const { data } = await profileService.update(payload)

    if (data.success && data.data?.user) {
      auth.setUser(data.data.user)
      success.value = 'Perfil actualizado correctamente'
      currentPassword.value = ''
      newPassword.value = ''
      showPasswordSection.value = false
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err?.response?.data?.message || 'Error al actualizar el perfil'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal :visible="visible" title="Editar perfil" size="sm" @close="emit('close')">
    <form class="space-y-4" @submit.prevent="handleSave">
      <!-- Error -->
      <div v-if="error" class="flex items-center gap-2 rounded-lg border border-danger/20 bg-danger/10 px-3 py-2.5 text-sm text-danger">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span>{{ error }}</span>
      </div>

      <!-- Success -->
      <div v-if="success" class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/10 px-3 py-2.5 text-sm text-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0">
          <polyline points="20 6 9 17 4 12" />
        </svg>
        <span>{{ success }}</span>
      </div>

      <!-- Name -->
      <div>
        <label for="profile-name" class="mb-1.5 block text-sm font-medium text-muted">Nombre</label>
        <input
          id="profile-name"
          v-model="name"
          type="text"
          required
          class="admin-input"
          :disabled="saving"
        />
      </div>

      <!-- Email -->
      <div>
        <label for="profile-email" class="mb-1.5 block text-sm font-medium text-muted">Email</label>
        <input
          id="profile-email"
          v-model="email"
          type="email"
          required
          class="admin-input"
          :disabled="saving"
        />
      </div>

      <!-- Password section toggle -->
      <div>
        <button
          type="button"
          class="flex items-center gap-2 text-sm text-subtle transition-colors hover:text-muted"
          @click="showPasswordSection = !showPasswordSection"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" :class="{ 'rotate-90': showPasswordSection }" class="transition-transform">
            <polyline points="9 18 15 12 9 6" />
          </svg>
          <span>Cambiar contraseña</span>
        </button>
      </div>

      <!-- Password fields -->
      <Transition name="fade">
        <div v-if="showPasswordSection" class="space-y-4">
          <div>
            <label for="profile-current-pw" class="mb-1.5 block text-sm font-medium text-muted">Contraseña actual</label>
            <input
              id="profile-current-pw"
              v-model="currentPassword"
              type="password"
              autocomplete="current-password"
              class="admin-input"
              :disabled="saving"
            />
          </div>
          <div>
            <label for="profile-new-pw" class="mb-1.5 block text-sm font-medium text-muted">Nueva contraseña</label>
            <input
              id="profile-new-pw"
              v-model="newPassword"
              type="password"
              autocomplete="new-password"
              class="admin-input"
              :disabled="saving"
            />
          </div>
        </div>
      </Transition>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <button class="admin-btn admin-btn-ghost" @click="emit('close')" :disabled="saving">Cancelar</button>
        <button
          class="admin-btn admin-btn-primary"
          @click="handleSave"
          :disabled="saving || !name || !email"
        >
          <svg v-if="saving" class="animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" stroke-dasharray="31.4159265" stroke-dashoffset="10" />
          </svg>
          <span>{{ saving ? 'Guardando...' : 'Guardar cambios' }}</span>
        </button>
      </div>
    </template>
  </AppModal>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease, max-height 0.15s ease;
  overflow: hidden;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
