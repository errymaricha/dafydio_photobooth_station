<script setup lang="ts">
import Sidebar from './Sidebar.vue'
import Topbar from './Topbar.vue'

defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="min-h-screen bg-slate-100 text-slate-900">
    <div class="flex min-h-screen">
      <Sidebar />

      <div class="flex min-w-0 flex-1 flex-col">
        <Topbar />

        <main class="flex-1 p-6">
          <div class="mb-6">
            <h1 class="text-2xl font-bold">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
          </div>

          <slot />
        </main>
      </div>
    </div>
  </div>
</template>