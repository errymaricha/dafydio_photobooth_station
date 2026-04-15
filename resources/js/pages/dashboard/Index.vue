<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

import { index as dashboardIndex } from '@/actions/App/Http/Controllers/Api/Editor/DashboardController';
import { index as printersIndex } from '@/actions/App/Http/Controllers/Api/Editor/PrinterController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';

type Printer = {
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
const printers = ref<Printer[]>([]);
const activities = ref<ActivityItem[]>([]);
const lastSyncedAt = ref<string | null>(null);
let refreshTimer: number | null = null;

const formatAmount = (amount?: number): string => {
    if (!amount) {
        return '-';
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
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
            get<{ data?: Printer[] } | Printer[]>(printersIndex()),
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
        <div
            class="mb-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500 shadow-sm md:flex-row md:items-center md:justify-between"
        >
            <div>
                Sinkron terakhir:
                <span class="font-medium text-slate-700">
                    {{ lastSyncedAt ?? '-' }}
                </span>
            </div>

            <button
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                :disabled="refreshing"
                @click="loadData(true)"
            >
                {{ refreshing ? 'Refreshing...' : 'Refresh Data' }}
            </button>
        </div>

        <div v-if="loading" class="text-sm text-slate-500">
            Loading dashboard...
        </div>

        <template v-else>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatsCard
                    label="Sessions Today"
                    :value="dashboard?.sessions?.uploaded_today ?? 0"
                />
                <StatsCard
                    label="Print Orders Today"
                    :value="dashboard?.print_orders?.today ?? 0"
                />
                <StatsCard
                    label="Failed Jobs"
                    :value="dashboard?.print_queue?.failed ?? 0"
                />
                <StatsCard
                    label="Online Printers"
                    :value="
                        dashboard?.printers?.online ??
                        printers.filter((p) => p.is_online).length
                    "
                />
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-3">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Recent Activity</h2>
                    </div>

                    <div v-if="!activities.length">
                        <EmptyState
                            title="Belum ada aktivitas"
                            message="Aktivitas terbaru akan muncul di sini."
                        />
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="item in activities"
                            :key="item.id"
                            class="rounded-lg border border-slate-200 px-4 py-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div class="font-medium text-slate-800">
                                        {{ item.title }}
                                    </div>
                                    <div class="text-sm text-slate-500">
                                        {{ item.description }}
                                    </div>
                                </div>

                                <StatusBadge :status="item.status ?? 'info'" />
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="mb-4 text-lg font-semibold">Printer Status</h2>

                    <div v-if="!printers.length">
                        <EmptyState
                            title="Printer belum tersedia"
                            message="Tambahkan atau sinkronkan printer terlebih dulu."
                        />
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="printer in printers"
                            :key="printer.id"
                            class="rounded-lg border border-slate-200 px-4 py-3"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium">
                                        {{ printer.printer_name }}
                                    </div>
                                    <div class="text-sm text-slate-500">
                                        Queue:
                                        {{
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
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <div
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="mb-4 text-lg font-semibold">Recent Sessions</h2>

                    <div v-if="!dashboard?.recent_sessions?.length">
                        <EmptyState
                            title="Belum ada sesi"
                            message="Sesi terbaru akan muncul di sini."
                        />
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="session in dashboard.recent_sessions"
                            :key="session.id"
                            class="rounded-lg border border-slate-200 px-4 py-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div class="font-medium text-slate-800">
                                        {{ session.session_code ?? '-' }}
                                    </div>
                                    <div class="text-sm text-slate-500">
                                        Station:
                                        {{ session.station?.code ?? '-' }}
                                        • Device:
                                        {{ session.device?.code ?? '-' }}
                                    </div>
                                </div>

                                <StatusBadge
                                    :status="session.status ?? 'uploaded'"
                                />
                            </div>

                            <div class="mt-2 text-xs text-slate-500">
                                Captured:
                                {{ session.captured_count ?? 0 }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="mb-4 text-lg font-semibold">
                        Recent Print Orders
                    </h2>

                    <div v-if="!dashboard?.recent_print_orders?.length">
                        <EmptyState
                            title="Belum ada order"
                            message="Order print terbaru akan muncul di sini."
                        />
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="order in dashboard.recent_print_orders"
                            :key="order.id"
                            class="rounded-lg border border-slate-200 px-4 py-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div class="font-medium text-slate-800">
                                        {{ order.order_code ?? '-' }}
                                    </div>
                                    <div class="text-sm text-slate-500">
                                        Printer:
                                        {{ order.printer?.name ?? '-' }}
                                    </div>
                                </div>

                                <StatusBadge
                                    :status="order.status ?? 'queued'"
                                />
                            </div>

                            <div class="mt-2 text-xs text-slate-500">
                                Qty:
                                {{ order.total_qty ?? 0 }}
                                • Amount:
                                {{ formatAmount(order.total_amount) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
