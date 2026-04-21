<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  helper?: string
}>()
</script>

<template>
  <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
    <div class="text-sm text-[#6d6b77]">{{ label }}</div>
    <div class="mt-2 text-3xl font-bold text-[#2f2b3dcc]">{{ value }}</div>
    <div v-if="helper" class="mt-1 text-xs text-[#b3b1bb]">{{ helper }}</div>
  </div>
</template>
