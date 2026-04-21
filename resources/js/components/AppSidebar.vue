<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    CircleDollarSign,
    ClipboardList,
    FolderGit2,
    LayoutGrid,
    Printer,
    ScanLine,
    Shapes,
    Tags,
    Ticket,
    UsersRound,
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import * as clientsRoutes from '@/routes/clients';
import * as financeRoutes from '@/routes/finance';
import * as pricingRoutes from '@/routes/pricing';
import * as printLogsRoutes from '@/routes/print-logs';
import * as printOrdersRoutes from '@/routes/print-orders';
import * as printQueueRoutes from '@/routes/print-queue';
import * as printersRoutes from '@/routes/printers';
import * as sessionsRoutes from '@/routes/sessions';
import * as templatesRoutes from '@/routes/templates';
import * as vouchersRoutes from '@/routes/vouchers';
import type { NavItem } from '@/types';

type SidebarSection = {
    label: string;
    items: NavItem[];
};

const sidebarSections: SidebarSection[] = [
    {
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
        ],
    },
    {
        label: 'Operasional',
        items: [
            {
                title: 'Sessions',
                href: sessionsRoutes.index(),
                icon: ClipboardList,
            },
            {
                title: 'Templates',
                href: templatesRoutes.index(),
                icon: Shapes,
            },
            {
                title: 'Vouchers',
                href: vouchersRoutes.index(),
                icon: Ticket,
            },
            {
                title: 'Pricing',
                href: pricingRoutes.index(),
                icon: Tags,
            },
        ],
    },
    {
        label: 'Produksi Print',
        items: [
            {
                title: 'Print Orders',
                href: printOrdersRoutes.index(),
                icon: Printer,
            },
            {
                title: 'Print Queue',
                href: printQueueRoutes.index(),
                icon: ScanLine,
            },
            {
                title: 'Printers',
                href: printersRoutes.index(),
                icon: Printer,
            },
            {
                title: 'Print Logs',
                href: printLogsRoutes.index(),
                icon: ClipboardList,
            },
        ],
    },
    {
        label: 'Semi CRM',
        items: [
            {
                title: 'Clients',
                href: clientsRoutes.index(),
                icon: UsersRound,
            },
            {
                title: 'Finance',
                href: financeRoutes.index(),
                icon: CircleDollarSign,
            },
            {
                title: 'Transactions',
                href: financeRoutes.transactions(),
                icon: CircleDollarSign,
            },
            {
                title: 'Expenses',
                href: financeRoutes.expenses(),
                icon: CircleDollarSign,
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="border-r border-[#e8e6ef] bg-white"
    >
        <SidebarHeader class="border-b border-[#e8e6ef] px-3 py-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="py-2">
            <div class="pointer-events-none mx-3 mb-1 h-2 bg-gradient-to-b from-black/6 to-transparent" />
            <NavMain
                v-for="section in sidebarSections"
                :key="section.label"
                :label="section.label"
                :items="section.items"
            />
        </SidebarContent>

        <SidebarFooter class="border-t border-[#e8e6ef] px-3 pb-3">
            <NavFooter
                :items="footerNavItems"
                class="px-0 [&_[data-slot='sidebar-menu-button']]:text-[#6d6b77] [&_[data-slot='sidebar-menu-button']]:hover:bg-[#f4f5fb] [&_[data-slot='sidebar-menu-button']]:hover:text-[#2f2b3dcc]"
            />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
