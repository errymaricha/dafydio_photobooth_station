<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import {
    accounts as financeAccounts,
    dailyPnl as financeDailyPnl,
} from '@/actions/App/Http/Controllers/Api/Editor/FinanceController';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useApi } from '@/composables/useApi';
import * as financeRoutes from '@/routes/finance';

type FinanceAccountItem = {
    id: string;
    account_code: string;
    account_name: string;
    account_type: string;
    normal_balance: string;
    parent_id: string | null;
    level_no: number;
    is_active: boolean;
};

type DailyPnlRow = {
    entry_date: string;
    station_id: string | null;
    station_code: string | null;
    station_name: string | null;
    revenue_amount: number;
    expense_amount: number;
    gross_profit_amount: number;
    net_profit_amount: number;
};

type DailyPnlResponse = {
    filters: {
        date_from: string;
        date_to: string;
        station_id: string | null;
    };
    summary: {
        revenue_amount: number;
        expense_amount: number;
        gross_profit_amount: number;
        net_profit_amount: number;
        row_count: number;
    };
    by_station: Array<{
        station_id: string | null;
        station_code: string | null;
        station_name: string | null;
        revenue_amount: number;
        expense_amount: number;
        gross_profit_amount: number;
        net_profit_amount: number;
    }>;
    rows: DailyPnlRow[];
};

const { get } = useApi();

const loading = ref(true);
const loadingReport = ref(false);
const errorMessage = ref<string | null>(null);

const accounts = ref<FinanceAccountItem[]>([]);
const reportRows = ref<DailyPnlRow[]>([]);
const reportByStation = ref<DailyPnlResponse['by_station']>([]);
const reportSummary = ref<DailyPnlResponse['summary']>({
    revenue_amount: 0,
    expense_amount: 0,
    gross_profit_amount: 0,
    net_profit_amount: 0,
    row_count: 0,
});

const today = new Date();
const defaultFrom = new Date(today);
defaultFrom.setDate(today.getDate() - 29);

const dateFrom = ref(defaultFrom.toISOString().slice(0, 10));
const dateTo = ref(today.toISOString().slice(0, 10));
const stationId = ref<string>('');

const stationOptions = computed(() =>
    reportByStation.value.filter((item) => item.station_id),
);

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

const loadAccounts = async (): Promise<void> => {
    const response = await get<{ accounts: FinanceAccountItem[] }>(financeAccounts());
    accounts.value = response.accounts ?? [];
};

const loadReport = async (): Promise<void> => {
    loadingReport.value = true;
    errorMessage.value = null;

    try {
        const response = await get<DailyPnlResponse>(
            financeDailyPnl({
                query: {
                    date_from: dateFrom.value,
                    date_to: dateTo.value,
                    station_id: stationId.value || undefined,
                },
            }),
        );

        reportRows.value = response.rows ?? [];
        reportByStation.value = response.by_station ?? [];
        reportSummary.value = response.summary;
    } catch (error: unknown) {
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Gagal memuat laporan keuangan.';
    } finally {
        loadingReport.value = false;
    }
};

onMounted(async () => {
    loading.value = true;

    try {
        await Promise.all([loadAccounts(), loadReport()]);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout
        title="Finance"
        subtitle="Rekap pendapatan, pengeluaran, dan profit harian per station (accrual-lite)."
    >
        <div class="space-y-6">
            <div class="flex flex-wrap justify-end gap-2">
                <Link
                    :href="financeRoutes.expenses.url()"
                    class="rounded-lg border border-[#d8d4e7] bg-white px-4 py-2 text-sm font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9]"
                >
                    Input Expense
                </Link>
                <Link
                    :href="financeRoutes.transactions.url()"
                    class="rounded-lg border border-[#d8d4e7] bg-white px-4 py-2 text-sm font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9]"
                >
                    Lihat Detail Transaksi
                </Link>
            </div>

            <div
                class="grid gap-3 rounded-xl border border-[#e8e6ef] bg-white p-4 shadow-[0_2px_10px_rgba(47,43,61,0.06)] md:grid-cols-[1fr_1fr_1fr_auto]"
            >
                <label class="space-y-1">
                    <span class="text-sm font-medium text-[#2f2b3dcc]">Date From</span>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                    />
                </label>

                <label class="space-y-1">
                    <span class="text-sm font-medium text-[#2f2b3dcc]">Date To</span>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                    />
                </label>

                <label class="space-y-1">
                    <span class="text-sm font-medium text-[#2f2b3dcc]">Station</span>
                    <select
                        v-model="stationId"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                    >
                        <option value="">All Stations</option>
                        <option
                            v-for="station in stationOptions"
                            :key="station.station_id || station.station_code || 'unknown'"
                            :value="station.station_id || ''"
                        >
                            {{ station.station_code }} - {{ station.station_name }}
                        </option>
                    </select>
                </label>

                <div class="flex items-end">
                    <button
                        type="button"
                        class="w-full rounded-lg bg-[#7367f0] px-4 py-2 text-sm font-semibold text-white hover:bg-[#685dd8] disabled:opacity-50 md:w-auto"
                        :disabled="loadingReport"
                        @click="loadReport"
                    >
                        {{ loadingReport ? 'Loading...' : 'Refresh Report' }}
                    </button>
                </div>
            </div>

            <div
                v-if="errorMessage"
                class="rounded-lg border border-[#ffd5d9] bg-[#fff5f5] px-3 py-2 text-sm text-[#ea5455]"
            >
                {{ errorMessage }}
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#6d6b77]">
                        Revenue
                    </p>
                    <p class="mt-1 text-lg font-semibold text-[#28c76f]">
                        {{ formatCurrency(reportSummary.revenue_amount) }}
                    </p>
                </div>
                <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#6d6b77]">
                        Expense
                    </p>
                    <p class="mt-1 text-lg font-semibold text-[#ea5455]">
                        {{ formatCurrency(reportSummary.expense_amount) }}
                    </p>
                </div>
                <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#6d6b77]">
                        Gross Profit
                    </p>
                    <p class="mt-1 text-lg font-semibold text-[#00cfe8]">
                        {{ formatCurrency(reportSummary.gross_profit_amount) }}
                    </p>
                </div>
                <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#6d6b77]">
                        Net Profit
                    </p>
                    <p class="mt-1 text-lg font-semibold text-[#7367f0]">
                        {{ formatCurrency(reportSummary.net_profit_amount) }}
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                <h3 class="text-sm font-semibold text-[#2f2b3dcc]">Daily P&L Rows</h3>
                <p class="mt-1 text-xs text-[#6d6b77]">
                    Total rows: {{ reportSummary.row_count }}
                </p>

                <div v-if="loading && !reportRows.length" class="mt-3 text-sm text-[#6d6b77]">
                    Loading finance report...
                </div>

                <div v-else-if="!reportRows.length" class="mt-3 text-sm text-[#6d6b77]">
                    Belum ada data jurnal untuk filter ini.
                </div>

                <div v-else class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-[#e8e6ef] text-[#6d6b77]">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Station</th>
                                <th class="px-3 py-2">Revenue</th>
                                <th class="px-3 py-2">Expense</th>
                                <th class="px-3 py-2">Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in reportRows"
                                :key="`${row.entry_date}-${row.station_id || 'all'}`"
                                class="border-b border-[#f1f0f5]"
                            >
                                <td class="px-3 py-2">{{ formatDate(row.entry_date) }}</td>
                                <td class="px-3 py-2">
                                    {{ row.station_code }} - {{ row.station_name }}
                                </td>
                                <td class="px-3 py-2 text-[#28c76f]">
                                    {{ formatCurrency(row.revenue_amount) }}
                                </td>
                                <td class="px-3 py-2 text-[#ea5455]">
                                    {{ formatCurrency(row.expense_amount) }}
                                </td>
                                <td class="px-3 py-2 font-medium text-[#2f2b3dcc]">
                                    {{ formatCurrency(row.net_profit_amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                <h3 class="text-sm font-semibold text-[#2f2b3dcc]">
                    Chart of Accounts (COA)
                </h3>
                <p class="mt-1 text-xs text-[#6d6b77]">
                    Total akun aktif/nonaktif: {{ accounts.length }}
                </p>

                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-[#e8e6ef] text-[#6d6b77]">
                            <tr>
                                <th class="px-3 py-2">Code</th>
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Normal</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="account in accounts"
                                :key="account.id"
                                class="border-b border-[#f1f0f5]"
                            >
                                <td class="px-3 py-2 font-medium text-[#2f2b3dcc]">
                                    {{ account.account_code }}
                                </td>
                                <td class="px-3 py-2">{{ account.account_name }}</td>
                                <td class="px-3 py-2 uppercase">
                                    {{ account.account_type }}
                                </td>
                                <td class="px-3 py-2 uppercase">
                                    {{ account.normal_balance }}
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-semibold"
                                        :class="
                                            account.is_active
                                                ? 'bg-[#e8f7ef] text-[#28c76f]'
                                                : 'bg-[#f1f0f5] text-[#6d6b77]'
                                        "
                                    >
                                        {{ account.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

