<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  helper?: string
}>()
</script>

<template>
  <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)] dark:border-[#2a2636] dark:bg-[#15131f] dark:shadow-none">
    <div class="text-sm text-[#6d6b77] dark:text-[#a9a3bd]">{{ label }}</div>
    <div class="mt-2 text-3xl font-bold text-[#2f2b3dcc] dark:text-[#f4f2ff]">{{ value }}</div>
    <div v-if="helper" class="mt-1 text-xs text-[#b3b1bb] dark:text-[#817a94]">{{ helper }}</div>
  </div>
</template>
