<script setup lang="ts">
import {
    Activity,
    CircleDollarSign,
    Printer,
    ReceiptText,
    RefreshCcw,
    ScanLine,
    UsersRound,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

import { index as dashboardIndex } from '@/actions/App/Http/Controllers/Api/Editor/DashboardController';
import { index as printersIndex } from '@/actions/App/Http/Controllers/Api/Editor/PrinterController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';

type PrinterItem = {
    id: number | string;
    printer_name: string;
    status?: string;
    is_online?: boolean;
    queue?: {
        pending?: number;
        processing?: number;
        failed?: number;
    };
};

type ActivityItem = {
    id: string | number;
    title: string;
    description: string;
    status?: string;
};

type DashboardResponse = {
    sessions?: {
        uploaded_today?: number;
        editing?: number;
        ready_print?: number;
        queued_print?: number;
        failed_print?: number;
        printed_today?: number;
    };
    print_orders?: {
        today?: number;
        queued?: number;
        printing?: number;
        failed?: number;
        printed_today?: number;
    };
    print_queue?: {
        failed?: number;
        pending?: number;
        processing?: number;
        completed_today?: number;
    };
    printers?: {
        online?: number;
        total?: number;
    };
    finance?: {
        today?: {
            revenue_amount?: number;
            expense_amount?: number;
            gross_profit_amount?: number;
            net_profit_amount?: number;
        };
        last_7_days?: Array<{
            entry_date: string;
            revenue_amount: number;
            expense_amount: number;
            gross_profit_amount: number;
            net_profit_amount: number;
        }>;
    };
    recent_sessions?: Array<{
        id: string | number;
        session_code?: string;
        status?: string;
        captured_count?: number;
        completed_at?: string | null;
        station?: {
            id?: string | number | null;
            code?: string | null;
        };
        device?: {
            id?: string | number | null;
            code?: string | null;
        };
    }>;
    recent_print_orders?: Array<{
        id: string | number;
        order_code?: string;
        status?: string;
        total_qty?: number;
        total_amount?: number;
        ordered_at?: string | null;
        session?: {
            id?: string | number | null;
            session_code?: string | null;
        };
        printer?: {
            id?: string | number | null;
            name?: string | null;
        };
    }>;
    recent_failed_jobs?: Array<{
        id: string | number;
        last_error?: string;
        session?: {
            session_code?: string;
        };
    }>;
    recent_logs?: Array<{
        id: string | number;
        message?: string;
        log_level?: string;
        print_order?: {
            order_code?: string;
        };
    }>;
};

const { get } = useApi();

const loading = ref(true);
const refreshing = ref(false);
const dashboard = ref<DashboardResponse | null>(null);
const printers = ref<PrinterItem[]>([]);
const activities = ref<ActivityItem[]>([]);
const lastSyncedAt = ref<string | null>(null);
let refreshTimer: number | null = null;

const formatAmount = (amount?: number): string => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount ?? 0);
};

const loadData = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        const [dashboardData, printerData] = await Promise.all([
            get<DashboardResponse>(dashboardIndex()),
            get<{ data?: PrinterItem[] } | PrinterItem[]>(printersIndex()),
        ]);

        dashboard.value = dashboardData;
        printers.value = Array.isArray(printerData)
            ? printerData
            : (printerData.data ?? []);

        activities.value = [
            ...(dashboardData.recent_failed_jobs ?? []).map((job) => ({
                id: `failed-${job.id}`,
                title: `Failed Job #${job.id}`,
                description:
                    job.last_error ??
                    job.session?.session_code ??
                    'Print queue error',
                status: 'failed',
            })),
            ...(dashboardData.recent_logs ?? []).map((log) => ({
                id: `log-${log.id}`,
                title: log.print_order?.order_code
                    ? `Order ${log.print_order.order_code}`
                    : 'Print log',
                description: log.message ?? '-',
                status: log.log_level ?? 'info',
            })),
        ].slice(0, 6);

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
    await loadData();

    refreshTimer = window.setInterval(() => {
        void loadData(true);
    }, 30000);
});

onBeforeUnmount(() => {
    if (refreshTimer !== null) {
        window.clearInterval(refreshTimer);
    }
});
</script>

<template>
    <AppLayout
        title="Dashboard"
        subtitle="Ringkasan operasional photobooth dan printer."
    >
        <div class="space-y-6">
            <div
                class="flex flex-col gap-3 rounded-xl border border-[#e8e6ef] bg-white p-4 text-sm text-[#6d6b77] shadow-[0_2px_10px_rgba(47,43,61,0.06)] md:flex-row md:items-center md:justify-between"
            >
                <div>
                    Sinkron terakhir:
                    <span class="font-medium text-[#2f2b3dcc]">
                        {{ lastSyncedAt ?? '-' }}
                    </span>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-[#d8d4e7] px-3 py-2 text-xs font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9]"
                    :disabled="refreshing"
                    @click="loadData(true)"
                >
                    <RefreshCcw class="h-3.5 w-3.5" />
                    {{ refreshing ? 'Refreshing...' : 'Refresh Data' }}
                </button>
            </div>

            <div v-if="loading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="placeholder in 8"
                    :key="placeholder"
                    class="h-28 animate-pulse rounded-xl border border-[#e8e6ef] bg-white"
                />
            </div>

            <template v-else>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-[#6d6b77]">Sessions Today</p>
                            <span class="rounded-lg bg-[#eeecff] p-2 text-[#7367f0]">
                                <UsersRound class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-[#2f2b3dcc]">
                            {{ dashboard?.sessions?.uploaded_today ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-[#6d6b77]">Print Orders</p>
                            <span class="rounded-lg bg-[#e8f7ef] p-2 text-[#28c76f]">
                                <ReceiptText class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-[#2f2b3dcc]">
                            {{ dashboard?.print_orders?.today ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-[#6d6b77]">Failed Jobs</p>
                            <span class="rounded-lg bg-[#ffe9ea] p-2 text-[#ea5455]">
                                <ScanLine class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-[#2f2b3dcc]">
                            {{ dashboard?.print_queue?.failed ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-[#6d6b77]">Online Printers</p>
                            <span class="rounded-lg bg-[#fff4e5] p-2 text-[#ff9f43]">
                                <Printer class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-[#2f2b3dcc]">
                            {{
                                dashboard?.printers?.online ??
                                printers.filter((p) => p.is_online).length
                            }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-[#6d6b77]">Revenue</p>
                            <CircleDollarSign class="h-4 w-4 text-[#28c76f]" />
                        </div>
                        <p class="mt-3 text-lg font-semibold text-[#2f2b3dcc]">
                            {{ formatAmount(dashboard?.finance?.today?.revenue_amount) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-[#6d6b77]">Expense</p>
                            <CircleDollarSign class="h-4 w-4 text-[#ea5455]" />
                        </div>
                        <p class="mt-3 text-lg font-semibold text-[#2f2b3dcc]">
                            {{ formatAmount(dashboard?.finance?.today?.expense_amount) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-[#6d6b77]">Gross Profit</p>
                            <Activity class="h-4 w-4 text-[#00cfe8]" />
                        </div>
                        <p class="mt-3 text-lg font-semibold text-[#2f2b3dcc]">
                            {{ formatAmount(dashboard?.finance?.today?.gross_profit_amount) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-[#6d6b77]">Net Profit</p>
                            <Activity class="h-4 w-4 text-[#7367f0]" />
                        </div>
                        <p class="mt-3 text-lg font-semibold text-[#2f2b3dcc]">
                            {{ formatAmount(dashboard?.finance?.today?.net_profit_amount) }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 xl:col-span-2">
                        <h2 class="mb-4 text-base font-semibold text-[#2f2b3dcc]">Recent Activity</h2>
                        <div v-if="!activities.length">
                            <EmptyState
                                title="Belum ada aktivitas"
                                message="Aktivitas terbaru akan muncul di sini."
                            />
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="item in activities"
                                :key="item.id"
                                class="rounded-lg border border-[#e8e6ef] px-4 py-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-[#2f2b3dcc]">
                                            {{ item.title }}
                                        </div>
                                        <div class="text-sm text-[#6d6b77]">
                                            {{ item.description }}
                                        </div>
                                    </div>
                                    <StatusBadge :status="item.status ?? 'info'" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-5">
                        <h2 class="mb-4 text-base font-semibold text-[#2f2b3dcc]">Printer Status</h2>
                        <div v-if="!printers.length">
                            <EmptyState
                                title="Printer belum tersedia"
                                message="Tambahkan atau sinkronkan printer terlebih dulu."
                            />
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="printer in printers"
                                :key="printer.id"
                                class="rounded-lg border border-[#e8e6ef] px-4 py-3"
                            >
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-[#2f2b3dcc]">
                                            {{ printer.printer_name }}
                                        </div>
                                        <div class="text-xs text-[#6d6b77]">
                                            Queue {{
                                                (printer.queue?.pending ?? 0) +
                                                (printer.queue?.processing ?? 0)
                                            }}
                                        </div>
                                    </div>
                                    <StatusBadge
                                        :status="
                                            printer.is_online
                                                ? (printer.status ?? 'ready')
                                                : 'offline'
                                        "
                                    />
                                </div>
                                <div class="h-1.5 rounded-full bg-[#f1f0f5]">
                                    <div
                                        class="h-1.5 rounded-full"
                                        :class="printer.is_online ? 'bg-[#28c76f]' : 'bg-[#ea5455]'"
                                        :style="{ width: printer.is_online ? '78%' : '34%' }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-5">
                        <h2 class="mb-4 text-base font-semibold text-[#2f2b3dcc]">Recent Sessions</h2>
                        <div v-if="!dashboard?.recent_sessions?.length">
                            <EmptyState
                                title="Belum ada sesi"
                                message="Sesi terbaru akan muncul di sini."
                            />
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="session in dashboard.recent_sessions"
                                :key="session.id"
                                class="rounded-lg border border-[#e8e6ef] px-4 py-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-[#2f2b3dcc]">
                                            {{ session.session_code ?? '-' }}
                                        </div>
                                        <div class="text-sm text-[#6d6b77]">
                                            Station {{ session.station?.code ?? '-' }} - Device
                                            {{ session.device?.code ?? '-' }}
                                        </div>
                                    </div>
                                    <StatusBadge :status="session.status ?? 'uploaded'" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-5">
                        <h2 class="mb-4 text-base font-semibold text-[#2f2b3dcc]">Recent Print Orders</h2>
                        <div v-if="!dashboard?.recent_print_orders?.length">
                            <EmptyState
                                title="Belum ada order"
                                message="Order print terbaru akan muncul di sini."
                            />
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="order in dashboard.recent_print_orders"
                                :key="order.id"
                                class="rounded-lg border border-[#e8e6ef] px-4 py-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-[#2f2b3dcc]">
                                            {{ order.order_code ?? '-' }}
                                        </div>
                                        <div class="text-sm text-[#6d6b77]">
                                            Printer {{ order.printer?.name ?? '-' }} - Qty
                                            {{ order.total_qty ?? 0 }}
                                        </div>
                                    </div>
                                    <StatusBadge :status="order.status ?? 'queued'" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-[#e8e6ef] bg-white p-5">
                    <h2 class="mb-4 text-base font-semibold text-[#2f2b3dcc]">Finance Last 7 Days</h2>
                    <div v-if="!dashboard?.finance?.last_7_days?.length">
                        <EmptyState
                            title="Belum ada data finance"
                            message="Transaksi yang sudah diposting jurnal akan tampil di sini."
                        />
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-[#e8e6ef] text-[#6d6b77]">
                                <tr>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">Revenue</th>
                                    <th class="px-3 py-2">Expense</th>
                                    <th class="px-3 py-2">Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in dashboard.finance.last_7_days"
                                    :key="item.entry_date"
                                    class="border-b border-[#f1f0f5]"
                                >
                                    <td class="px-3 py-2">
                                        {{
                                            new Date(item.entry_date).toLocaleDateString(
                                                'id-ID',
                                                {
                                                    day: '2-digit',
                                                    month: 'short',
                                                    year: 'numeric',
                                                },
                                            )
                                        }}
                                    </td>
                                    <td class="px-3 py-2 text-[#28c76f]">
                                        {{ formatAmount(item.revenue_amount) }}
                                    </td>
                                    <td class="px-3 py-2 text-[#ea5455]">
                                        {{ formatAmount(item.expense_amount) }}
                                    </td>
                                    <td class="px-3 py-2 font-medium text-[#2f2b3dcc]">
                                        {{ formatAmount(item.net_profit_amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
