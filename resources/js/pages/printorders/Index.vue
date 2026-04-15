<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { index as printOrdersIndex } from '@/actions/App/Http/Controllers/Api/Editor/PrintOrderController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';
import * as printOrderRoutes from '@/routes/print-orders';

type PrintOrderItem = {
    id: string;
    order_code: string;
    status?: string;
    total_qty?: number;
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
};

const { get } = useApi();

const orders = ref<PrintOrderItem[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const search = ref('');
const status = ref('all');
const lastSyncedAt = ref<string | null>(null);
let refreshTimer: number | null = null;

const statusOptions = [
    { key: 'all', label: 'All' },
    { key: 'queued', label: 'Queued' },
    { key: 'printing', label: 'Printing' },
    { key: 'failed', label: 'Failed' },
    { key: 'created', label: 'Created' },
    { key: 'printed', label: 'Printed' },
];

const filteredOrders = computed(() => {
    return orders.value.filter((order) => {
        const haystack =
            `${order.order_code} ${order.session?.session_code ?? ''} ${order.printer?.name ?? ''}`.toLowerCase();

        const matchesSearch =
            !search.value || haystack.includes(search.value.toLowerCase());

        const matchesStatus =
            status.value === 'all' ||
            (order.status ?? '').toLowerCase() === status.value;

        return matchesSearch && matchesStatus;
    });
});

const summaryItems = computed(() => {
    return statusOptions.map((option) => ({
        ...option,
        count:
            option.key === 'all'
                ? orders.value.length
                : orders.value.filter(
                      (order) =>
                          (order.status ?? '').toLowerCase() === option.key,
                  ).length,
    }));
});

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

const loadOrders = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        const response = await get<
            { data?: PrintOrderItem[] } | PrintOrderItem[]
        >(printOrdersIndex());

        orders.value = Array.isArray(response)
            ? response
            : (response.data ?? []);
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
    await loadOrders();

    refreshTimer = window.setInterval(() => {
        void loadOrders(true);
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
        title="Print Orders"
        subtitle="Lihat order hasil render yang siap atau sedang diproses printer."
    >
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button
                v-for="item in summaryItems"
                :key="item.key"
                type="button"
                class="rounded-lg border px-3 py-2 text-xs font-semibold transition"
                :class="
                    status === item.key
                        ? 'border-blue-600 bg-blue-600 text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                "
                @click="status = item.key"
            >
                {{ item.label }}: {{ item.count }}
            </button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div
                class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex flex-1 flex-col gap-3 md:flex-row">
                    <input
                        v-model="search"
                        type="text"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm md:max-w-sm"
                        placeholder="Cari order, session, atau printer"
                    />

                    <select
                        v-model="status"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:w-48"
                    >
                        <option value="all">All Status</option>
                        <option
                            v-for="option in statusOptions"
                            :key="option.key"
                            :value="option.key"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span>Synced: {{ lastSyncedAt ?? '-' }}</span>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100"
                        :disabled="refreshing"
                        @click="loadOrders(true)"
                    >
                        {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                    </button>
                </div>
            </div>

            <div v-if="loading" class="text-sm text-slate-500">
                Loading print orders...
            </div>

            <div v-else-if="!filteredOrders.length">
                <EmptyState
                    title="Print order belum ada"
                    message="Order dari hasil render akan tampil di sini."
                />
            </div>

            <div v-else class="overflow-x-auto">
                <div class="mb-3 text-xs text-slate-500">
                    Menampilkan {{ filteredOrders.length }} dari
                    {{ orders.length }} order
                </div>
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Session</th>
                            <th class="px-4 py-3">Printer</th>
                            <th class="px-4 py-3">Qty</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Ordered</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="order in filteredOrders"
                            :key="order.id"
                            class="border-b border-slate-100"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800">
                                    {{ order.order_code }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    Total: {{ formatAmount(order.total_amount) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ order.session?.session_code ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800">
                                    {{ order.printer?.name ?? '-' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ order.printer?.status ?? 'unassigned' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ order.total_qty ?? 0 }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="order.status ?? 'unknown'"
                                />
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ order.ordered_at ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="printOrderRoutes.show.url(order.id)"
                                    class="text-sm font-medium text-blue-600 hover:text-blue-700"
                                >
                                    Detail
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
