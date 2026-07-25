<script setup lang="ts">
defineProps<{
  modelValue: boolean
  disabled?: boolean
}>()

defineEmits<{
  'update:modelValue': [value: boolean]
}>()
</script>

<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue"
    :disabled="disabled"
    :class="[
      'admin-toggle relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#69dca4]/50 disabled:cursor-not-allowed disabled:opacity-50',
      modelValue ? 'bg-[#69dca4]' : 'bg-[#2a3038]'
    ]"
    @click="$emit('update:modelValue', !modelValue)"
  >
    <span
      :class="[
        'pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow-lg transition-transform duration-200',
        modelValue ? 'translate-x-6' : 'translate-x-1'
      ]"
    />
  </button>
</template>
