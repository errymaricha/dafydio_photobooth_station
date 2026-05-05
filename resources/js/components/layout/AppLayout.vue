<script setup lang="ts">
import AppLayoutBase from '@/layouts/AppLayout.vue';

defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <AppLayoutBase>
        <div class="mb-6">
            <h1
                class="text-[1.625rem] leading-tight font-semibold text-[#2f2b3dcc] dark:text-[#f4f2ff]"
            >
                {{ title }}
            </h1>
            <p
                v-if="subtitle"
                class="mt-1 text-sm text-[#6d6b77] dark:text-[#a9a3bd]"
            >
                {{ subtitle }}
            </p>
        </div>

        <slot />
    </AppLayoutBase>
</template>
