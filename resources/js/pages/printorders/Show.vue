<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

import { show as showPrintOrder } from '@/actions/App/Http/Controllers/Api/Editor/PrintOrderController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';
import * as printQueueRoutes from '@/routes/print-queue';
import * as sessionsRoutes from '@/routes/sessions';

type PrintOrderDetail = {
    id: string;
    order_code: string;
    status?: string;
    payment_status?: string;
    total_items?: number;
    total_qty?: number;
    subtotal_amount?: number | string;
    discount_amount?: number | string;
    total_amount?: number | string;
    ordered_at?: string;
    session?: {
        id?: string | null;
        session_code?: string | null;
    };
    printer?: {
        id?: string | null;
        name?: string | null;
        status?: string | null;
    };
    items: Array<{
        id: string;
        copies?: number;
        paper_size?: string;
        status?: string;
        file_url?: string | null;
    }>;
};

const props = defineProps<{
    printOrderId: string;
}>();

const { get } = useApi();

const order = ref<PrintOrderDetail | null>(null);
const loading = ref(true);
const refreshing = ref(false);
const lastSyncedAt = ref<string | null>(null);
let refreshTimer: number | null = null;

const formatAmount = (amount?: number | string): string => {
    if (amount === null || amount === undefined || amount === '') {
        return '-';
    }

    const numeric = typeof amount === 'string' ? Number(amount) : amount;

    if (Number.isNaN(numeric)) {
        return `${amount}`;
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(numeric);
};

const loadOrder = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        order.value = await get<PrintOrderDetail>(
            showPrintOrder(props.printOrderId),
        );
        lastSyncedAt.value = new Date().toLocaleTimeString('id-ID');
    } finally {
        if (silent) {
            refreshing.value = false;
        } else {
            loading.value = false;
        }
    }
};

onMounted(async () => {
    await loadOrder();

    refreshTimer = window.setInterval(() => {
        void loadOrder(true);
    }, 20000);
});

onBeforeUnmount(() => {
    if (refreshTimer !== null) {
        window.clearInterval(refreshTimer);
    }
});
</script>

<template>
    <AppLayout
        title="Print Order Detail"
        subtitle="Lihat ringkasan order, file hasil render, dan status proses cetak."
    >
        <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-slate-500">
            <span>Synced: {{ lastSyncedAt ?? '-' }}</span>
            <button
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100"
                :disabled="refreshing"
                @click="loadOrder(true)"
            >
                {{ refreshing ? 'Refreshing...' : 'Refresh' }}
            </button>
        </div>

        <div v-if="loading" class="text-sm text-slate-500">
            Loading print order...
        </div>

        <div v-else-if="!order">
            <EmptyState
                title="Print order tidak ditemukan"
                message="Periksa kembali data order yang dipilih."
            />
        </div>

        <div v-else class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-6">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <h2
                                    class="text-xl font-semibold text-slate-900"
                                >
                                    {{ order.order_code }}
                                </h2>
                                <StatusBadge
                                    :status="order.status ?? 'unknown'"
                                />
                            </div>

                            <div
                                class="grid gap-2 text-sm text-slate-500 md:grid-cols-2"
                            >
                                <p>
                                    Payment: {{ order.payment_status ?? '-' }}
                                </p>
                                <p>Ordered: {{ order.ordered_at ?? '-' }}</p>
                                <p>Items: {{ order.total_items ?? 0 }}</p>
                                <p>Copies: {{ order.total_qty ?? 0 }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 text-right">
                            <div
                                class="text-xs tracking-wide text-slate-400 uppercase"
                            >
                                Total
                            </div>
                            <div class="mt-1 text-2xl font-semibold text-slate-900">
                                {{ formatAmount(order.total_amount) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-slate-900">
                            Order Items
                        </h3>

                        <Link
                            :href="printQueueRoutes.index.url()"
                            class="text-sm font-medium text-blue-600 hover:text-blue-700"
                        >
                            Open Queue
                        </Link>
                    </div>

                    <div v-if="!order.items.length">
                        <EmptyState
                            title="Belum ada item"
                            message="Item print akan muncul di sini."
                        />
                    </div>

                    <div v-else class="flex flex-col gap-4">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div
                                class="mb-3 flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div class="font-medium text-slate-900">
                                        Item #{{ item.id }}
                                    </div>
                                    <div class="text-sm text-slate-500">
                                        {{ item.paper_size ?? '-' }} •
                                        {{ item.copies ?? 0 }} copies
                                    </div>
                                </div>

                                <StatusBadge :status="item.status ?? 'unknown'" />
                            </div>

                            <img
                                v-if="item.file_url"
                                :src="item.file_url"
                                alt="print order item"
                                class="w-full rounded-xl border border-slate-200 object-cover"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h3 class="mb-4 text-lg font-semibold text-slate-900">
                        Routing Info
                    </h3>

                    <div class="space-y-4 text-sm text-slate-600">
                        <div>
                            <div
                                class="text-xs tracking-wide text-slate-400 uppercase"
                            >
                                Session
                            </div>
                            <div class="mt-1 font-medium text-slate-900">
                                {{ order.session?.session_code ?? '-' }}
                            </div>
                            <Link
                                v-if="order.session?.id"
                                :href="
                                    sessionsRoutes.show.url(order.session.id)
                                "
                                class="mt-1 inline-block text-sm font-medium text-blue-600 hover:text-blue-700"
                            >
                                Open Session
                            </Link>
                        </div>

                        <div>
                            <div
                                class="text-xs tracking-wide text-slate-400 uppercase"
                            >
                                Printer
                            </div>
                            <div class="mt-1 font-medium text-slate-900">
                                {{ order.printer?.name ?? 'Belum ditentukan' }}
                            </div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ order.printer?.status ?? 'unassigned' }}
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-4"
                        >
                            <div>
                                <div
                                    class="text-xs tracking-wide text-slate-400 uppercase"
                                >
                                    Subtotal
                                </div>
                                <div class="mt-1 font-medium text-slate-900">
                                    {{ formatAmount(order.subtotal_amount) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-xs tracking-wide text-slate-400 uppercase"
                                >
                                    Discount
                                </div>
                                <div class="mt-1 font-medium text-slate-900">
                                    {{ formatAmount(order.discount_amount) }}
                                </div>
                            </div>
                            <div class="col-span-2">
                                <div
                                    class="text-xs tracking-wide text-slate-400 uppercase"
                                >
                                    Total
                                </div>
                                <div class="mt-1 font-medium text-slate-900">
                                    {{ formatAmount(order.total_amount) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
