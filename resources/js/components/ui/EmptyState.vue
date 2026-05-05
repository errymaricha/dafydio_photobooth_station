<script setup lang="ts">
defineProps<{
  title?: string
  message?: string
}>()
</script>

<template>
  <div class="rounded-xl border border-dashed border-[#d8d4e7] bg-[#fcfcff] p-10 text-center dark:border-[#3a334a] dark:bg-[#15131f]">
    <div class="text-lg font-semibold text-[#2f2b3dcc] dark:text-[#f4f2ff]">{{ title ?? 'Belum ada data' }}</div>
    <p class="mt-2 text-sm text-[#6d6b77] dark:text-[#a9a3bd]">{{ message ?? 'Data akan muncul di sini saat sudah tersedia.' }}</p>
  </div>
</template>
