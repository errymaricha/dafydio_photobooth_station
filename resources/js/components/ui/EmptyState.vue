<script setup lang="ts">
defineProps<{
  title?: string
  message?: string
}>()
</script>

<template>
  <div class="rounded-xl border border-dashed border-[#d8d4e7] bg-[#fcfcff] p-10 text-center">
    <div class="text-lg font-semibold text-[#2f2b3dcc]">{{ title ?? 'Belum ada data' }}</div>
    <p class="mt-2 text-sm text-[#6d6b77]">{{ message ?? 'Data akan muncul di sini saat sudah tersedia.' }}</p>
  </div>
</template>
