<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import { transactions as financeTransactions } from '@/actions/App/Http/Controllers/Api/Editor/FinanceController';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useApi } from '@/composables/useApi';
import * as financeRoutes from '@/routes/finance';

type TransactionLine = {
    line_no: number;
    account_id: string;
    account_code: string;
    account_name: string;
    description: string | null;
    debit: number;
    credit: number;
};

type TransactionRow = {
    id: string;
    entry_no: string;
    entry_date: string;
    period_month: string;
    source_type: string;
    source_id: string | null;
    source_ref: string | null;
    station_id: string | null;
    station_code: string | null;
    station_name: string | null;
    customer_id: string | null;
    customer_whatsapp: string | null;
    currency_code: string;
    status: string;
    memo: string | null;
    total_debit: number;
    total_credit: number;
    is_balanced: boolean;
    lines: TransactionLine[];
    created_at: string;
};

type TransactionResponse = {
    filters: {
        date_from: string;
        date_to: string;
        station_id: string | null;
        source_type: string | null;
        status: string | null;
        per_page: number;
    };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    rows: TransactionRow[];
};

const { get } = useApi();

const today = new Date();
const defaultFrom = new Date(today);
defaultFrom.setDate(today.getDate() - 29);

const loading = ref(true);
const fetching = ref(false);
const errorMessage = ref<string | null>(null);

const dateFrom = ref(defaultFrom.toISOString().slice(0, 10));
const dateTo = ref(today.toISOString().slice(0, 10));
const stationId = ref('');
const sourceType = ref('');
const status = ref('');
const perPage = ref(20);
const currentPage = ref(1);

const pagination = ref<TransactionResponse['pagination']>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});
const rows = ref<TransactionRow[]>([]);
const expandedIds = ref<string[]>([]);

const availableStations = computed(() => {
    const map = new Map<string, { station_id: string; station_code: string | null; station_name: string | null }>();

    for (const row of rows.value) {
        if (!row.station_id) {
            continue;
        }

        map.set(row.station_id, {
            station_id: row.station_id,
            station_code: row.station_code,
            station_name: row.station_name,
        });
    }

    return [...map.values()];
});

const sourceTypeOptions = ['photo_session_payment', 'print_order_payment'];
const statusOptions = ['posted', 'draft', 'reversed'];

const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 2,
    }).format(value ?? 0);
};

const formatDate = (value: string): string => {
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const isExpanded = (id: string): boolean => {
    return expandedIds.value.includes(id);
};

const toggleExpanded = (id: string): void => {
    if (isExpanded(id)) {
        expandedIds.value = expandedIds.value.filter((entryId) => entryId !== id);
        return;
    }

    expandedIds.value = [...expandedIds.value, id];
};

const loadTransactions = async (page = 1): Promise<void> => {
    fetching.value = true;
    errorMessage.value = null;

    try {
        const response = await get<TransactionResponse>(
            financeTransactions({
                query: {
                    date_from: dateFrom.value,
                    date_to: dateTo.value,
                    station_id: stationId.value || undefined,
                    source_type: sourceType.value || undefined,
                    status: status.value || undefined,
                    per_page: perPage.value,
                    page,
                },
            }),
        );

        rows.value = response.rows ?? [];
        pagination.value = response.pagination;
        currentPage.value = response.pagination.current_page;
        expandedIds.value = [];
    } catch (error: unknown) {
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Gagal memuat transaksi finance.';
    } finally {
        loading.value = false;
        fetching.value = false;
    }
};

const nextPage = async (): Promise<void> => {
    if (currentPage.value >= pagination.value.last_page || fetching.value) {
        return;
    }

    await loadTransactions(currentPage.value + 1);
};

const prevPage = async (): Promise<void> => {
    if (currentPage.value <= 1 || fetching.value) {
        return;
    }

    await loadTransactions(currentPage.value - 1);
};

onMounted(async () => {
    await loadTransactions();
});
</script>

<template>
    <AppLayout
        title="Finance Transactions"
        subtitle="Detail jurnal transaksi untuk audit trail pendapatan dan biaya."
    >
        <div class="space-y-4">
            <div class="flex justify-end">
                <Link
                    :href="financeRoutes.index.url()"
                    class="rounded-lg border border-[#d8d4e7] px-4 py-2 text-sm font-semibold text-[#2f2b3dcc] hover:bg-[#f1f0f5]"
                >
                    Kembali ke Rekap Finance
                </Link>
            </div>

            <div class="grid gap-3 rounded-xl border border-[#e8e6ef] bg-white p-4 shadow-[0_2px_10px_rgba(47,43,61,0.06)] md:grid-cols-6">
                <label class="space-y-1">
                    <span class="text-xs font-medium text-[#2f2b3dcc]">Date From</span>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                    />
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-[#2f2b3dcc]">Date To</span>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                    />
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-[#2f2b3dcc]">Station</span>
                    <select
                        v-model="stationId"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                    >
                        <option value="">All Stations</option>
                        <option
                            v-for="station in availableStations"
                            :key="station.station_id"
                            :value="station.station_id"
                        >
                            {{ station.station_code }} - {{ station.station_name }}
                        </option>
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-[#2f2b3dcc]">Source Type</span>
                    <select
                        v-model="sourceType"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                    >
                        <option value="">All</option>
                        <option v-for="item in sourceTypeOptions" :key="item" :value="item">
                            {{ item }}
                        </option>
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-[#2f2b3dcc]">Status</span>
                    <select
                        v-model="status"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                    >
                        <option value="">All</option>
                        <option v-for="item in statusOptions" :key="item" :value="item">
                            {{ item }}
                        </option>
                    </select>
                </label>

                <div class="flex items-end">
                    <button
                        type="button"
                        class="w-full rounded-lg bg-[#7367f0] px-4 py-2 text-sm font-semibold text-white hover:bg-[#685dd8] disabled:opacity-50"
                        :disabled="fetching"
                        @click="loadTransactions(1)"
                    >
                        {{ fetching ? 'Loading...' : 'Filter' }}
                    </button>
                </div>
            </div>

            <div
                v-if="errorMessage"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            >
                {{ errorMessage }}
            </div>

            <div class="rounded-xl border border-[#e8e6ef] bg-white p-4 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-[#2f2b3dcc]">
                        Jurnal Transaksi
                    </h3>
                    <p class="text-xs text-[#6d6b77]">
                        Total: {{ pagination.total }} transaksi
                    </p>
                </div>

                <div v-if="loading" class="text-sm text-[#6d6b77]">
                    Loading transaksi...
                </div>

                <div v-else-if="!rows.length" class="text-sm text-[#6d6b77]">
                    Belum ada transaksi untuk filter ini.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-[#e8e6ef] text-[#6d6b77]">
                            <tr>
                                <th class="px-3 py-2">Entry No</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Source</th>
                                <th class="px-3 py-2">Station</th>
                                <th class="px-3 py-2">Debit</th>
                                <th class="px-3 py-2">Credit</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 text-right">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in rows" :key="row.id">
                                <tr class="border-b border-[#f1f0f5]">
                                    <td class="px-3 py-2 font-medium text-[#2f2b3dcc]">
                                        {{ row.entry_no }}
                                    </td>
                                    <td class="px-3 py-2">{{ formatDate(row.entry_date) }}</td>
                                    <td class="px-3 py-2">
                                        <div>{{ row.source_type }}</div>
                                        <div class="text-xs text-[#6d6b77]">{{ row.source_ref ?? '-' }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ row.station_code }} - {{ row.station_name }}
                                    </td>
                                    <td class="px-3 py-2 text-emerald-700">
                                        {{ formatCurrency(row.total_debit) }}
                                    </td>
                                    <td class="px-3 py-2 text-[#685dd8]">
                                        {{ formatCurrency(row.total_credit) }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-semibold"
                                            :class="
                                                row.is_balanced
                                                    ? 'bg-[#e8f7ef] text-[#28c76f]'
                                                    : 'bg-[#ffe9ea] text-[#ea5455]'
                                            "
                                        >
                                            {{ row.status }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button
                                            type="button"
                                            class="text-sm font-medium text-[#7367f0] hover:text-[#685dd8]"
                                            @click="toggleExpanded(row.id)"
                                        >
                                            {{ isExpanded(row.id) ? 'Tutup' : 'Lihat' }}
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="isExpanded(row.id)" class="border-b border-[#f1f0f5] bg-[#f5f5f9]">
                                    <td colspan="8" class="px-3 py-3">
                                        <div class="mb-2 text-xs text-[#6d6b77]">
                                            Memo: {{ row.memo || '-' }} | Customer:
                                            {{ row.customer_whatsapp || '-' }}
                                        </div>
                                        <table class="w-full text-left text-xs">
                                            <thead class="text-[#6d6b77]">
                                                <tr>
                                                    <th class="py-1">Line</th>
                                                    <th class="py-1">Account</th>
                                                    <th class="py-1">Description</th>
                                                    <th class="py-1">Debit</th>
                                                    <th class="py-1">Credit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="line in row.lines"
                                                    :key="`${row.id}-${line.line_no}`"
                                                    class="border-t border-[#e8e6ef]"
                                                >
                                                    <td class="py-1">{{ line.line_no }}</td>
                                                    <td class="py-1">
                                                        {{ line.account_code }} - {{ line.account_name }}
                                                    </td>
                                                    <td class="py-1">{{ line.description || '-' }}</td>
                                                    <td class="py-1 text-emerald-700">
                                                        {{ formatCurrency(line.debit) }}
                                                    </td>
                                                    <td class="py-1 text-[#685dd8]">
                                                        {{ formatCurrency(line.credit) }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <p class="text-xs text-[#6d6b77]">
                        Page {{ pagination.current_page }} / {{ pagination.last_page }}
                    </p>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-[#d8d4e7] px-3 py-1 text-xs font-semibold text-[#2f2b3dcc] hover:bg-[#f1f0f5] disabled:opacity-50"
                            :disabled="pagination.current_page <= 1 || fetching"
                            @click="prevPage"
                        >
                            Prev
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-[#d8d4e7] px-3 py-1 text-xs font-semibold text-[#2f2b3dcc] hover:bg-[#f1f0f5] disabled:opacity-50"
                            :disabled="pagination.current_page >= pagination.last_page || fetching"
                            @click="nextPage"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

