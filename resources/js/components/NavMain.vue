<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    label?: string;
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-3 py-1">
        <SidebarGroupLabel
            class="px-2 text-[0.68rem] font-semibold tracking-[0.08em] text-[#a8a6b3] uppercase dark:text-[#807891]"
        >
            {{ label ?? 'Platform' }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="h-10 rounded-md px-2 text-[#6d6b77] hover:bg-[#f4f5fb] hover:text-[#2f2b3dcc] data-[active=true]:bg-[#eeecff] data-[active=true]:font-medium data-[active=true]:text-[#7367f0] dark:text-[#a9a3bd] dark:hover:bg-[#1c1928] dark:hover:text-[#f4f2ff] dark:data-[active=true]:bg-[#241f3b] dark:data-[active=true]:text-[#a79dff] [&>a>svg]:size-4"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
