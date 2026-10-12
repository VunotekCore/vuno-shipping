<script setup lang="ts">
import { useRouter, type RouteLocationRaw } from 'vue-router'

const props = defineProps<{
  title?: string
  subtitle?: string
  eyebrow?: string
  backTo?: RouteLocationRaw
}>()

const router = useRouter()
function goBack() {
  if (props.backTo) router.push(props.backTo)
  else router.back()
}
</script>

<template>
  <div class="admin-page-header">
    <div class="flex min-w-0 items-start gap-3">
      <button
        v-if="backTo !== undefined"
        type="button"
        class="admin-btn admin-btn-ghost admin-btn-sm shrink-0"
        title="Volver"
        aria-label="Volver"
        @click="goBack()"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
      </button>
      <div class="min-w-0">
        <p v-if="eyebrow" class="admin-page-eyebrow mb-1.5">{{ eyebrow }}</p>
        <h1 class="admin-page-title">
          <slot name="title">{{ title }}</slot>
        </h1>
        <p v-if="subtitle || $slots.subtitle" class="admin-page-subtitle">
          <slot name="subtitle">{{ subtitle }}</slot>
        </p>
      </div>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 max-sm:w-full">
      <slot name="actions" />
    </div>
  </div>
</template>

