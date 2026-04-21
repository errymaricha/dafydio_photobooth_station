<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { dashboard } from '@/routes';
import * as clients from '@/routes/clients';
import * as pricing from '@/routes/pricing';
import * as printLogs from '@/routes/print-logs';
import * as printOrders from '@/routes/print-orders';
import * as printQueue from '@/routes/print-queue';
import * as printers from '@/routes/printers';
import * as sessions from '@/routes/sessions';
import * as templates from '@/routes/templates';
import * as vouchers from '@/routes/vouchers';

const page = usePage();

const items = [
    { label: 'Dashboard', href: dashboard.url() },
    { label: 'Sessions', href: sessions.index.url() },
    { label: 'Daftar Client', href: clients.index.url() },
    { label: 'Templates', href: templates.index.url() },
    { label: 'Print Queue', href: printQueue.index.url() },
    { label: 'Printers', href: printers.index.url() },
    { label: 'Print Orders', href: printOrders.index.url() },
    { label: 'Print Logs', href: printLogs.index.url() },
    { label: 'Pricing', href: pricing.index.url() },
    { label: 'Vouchers', href: vouchers.index.url() },
];

const currentPath = computed(() => page.url);
const isActive = (href: string) => currentPath.value.startsWith(href);
</script>

<template>
    <aside
        class="hidden w-64 shrink-0 border-r border-[#e8e6ef] bg-white lg:block"
    >
        <div class="border-b border-[#e8e6ef] px-6 py-5">
            <div class="text-lg font-semibold">Photobooth Admin</div>
            <div class="text-sm text-[#6d6b77]">Editor Panel</div>
        </div>

        <nav class="space-y-1 p-4">
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                :class="
                    isActive(item.href)
                        ? 'bg-[#edeafd] text-[#685dd8]'
                        : 'text-[#6d6b77] hover:bg-[#f1f0f5] hover:text-[#2f2b3dcc]'
                "
            >
                {{ item.label }}
            </Link>
        </nav>
    </aside>
</template>
