<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  status: string
}>()

const classes = computed(() => {
  const status = props.status?.toLowerCase()

  if (['success', 'completed', 'online', 'done', 'ready', 'printed', 'ready_print'].includes(status)) {
    return 'bg-green-100 text-green-700'
  }

  if (['pending', 'queued', 'processing', 'warning', 'editing', 'printing', 'queued_print', 'created'].includes(status)) {
    return 'bg-amber-100 text-amber-700'
  }

  if (['failed', 'error', 'offline', 'failed_print'].includes(status)) {
    return 'bg-red-100 text-red-700'
  }

  return 'bg-slate-100 text-slate-700'
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
