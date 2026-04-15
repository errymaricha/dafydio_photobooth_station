<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  helper?: string
}>()
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="text-sm text-slate-500">{{ label }}</div>
    <div class="mt-2 text-3xl font-bold text-slate-900">{{ value }}</div>
    <div v-if="helper" class="mt-1 text-xs text-slate-400">{{ helper }}</div>
  </div>
</template>