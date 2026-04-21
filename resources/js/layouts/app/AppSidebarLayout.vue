<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { computed } from 'vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const currentYear = computed(() => new Date().getFullYear());
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="overflow-x-hidden bg-[#f4f5fb] md:peer-data-[variant=inset]:m-0 md:peer-data-[variant=inset]:rounded-none md:peer-data-[variant=inset]:shadow-none md:peer-data-[variant=inset]:peer-data-[state=collapsed]:ml-0"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />

            <main class="flex-1 px-4 py-6 md:px-6">
                <div class="mx-auto w-full max-w-[1400px]">
                    <slot />
                </div>
            </main>

            <footer
                class="flex flex-col gap-2 px-4 pb-6 text-sm text-[#6d6b77] md:flex-row md:items-center md:justify-between md:px-6"
            >
                <div class="mx-auto flex w-full max-w-[1400px] items-center justify-between">
                    <div>&copy; {{ currentYear }} Dafydio Photobooth Admin.</div>
                    <a
                        href="https://laravel.com/docs/starter-kits#vue"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-[#7367f0] hover:underline"
                    >
                        Documentation
                    </a>
                </div>
            </footer>
        </AppContent>
    </AppShell>
</template>
