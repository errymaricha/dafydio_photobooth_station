<script setup lang="ts">
defineProps<{
  title?: string
  message?: string
}>()
</script>

<template>
  <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
    <div class="text-lg font-semibold text-slate-800">{{ title ?? 'Belum ada data' }}</div>
    <p class="mt-2 text-sm text-slate-500">{{ message ?? 'Data akan muncul di sini saat sudah tersedia.' }}</p>
  </div>
</template>