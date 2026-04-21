<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  status: string
}>()

const classes = computed(() => {
  const status = props.status?.toLowerCase()

  if (['success', 'completed', 'online', 'done', 'ready', 'printed', 'ready_print'].includes(status)) {
    return 'bg-[#e8f7ef] text-[#28c76f]'
  }

  if (['pending', 'queued', 'processing', 'warning', 'editing', 'printing', 'queued_print', 'created'].includes(status)) {
    return 'bg-[#fff1e3] text-[#ff9f43]'
  }

  if (['failed', 'error', 'offline', 'failed_print'].includes(status)) {
    return 'bg-[#ffe9ea] text-[#ea5455]'
  }

  return 'bg-[#f1f0f5] text-[#6d6b77]'
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize"
    :class="classes"
  >
    {{ status?.replaceAll('_', ' ') ?? 'unknown' }}
  </span>
</template>
