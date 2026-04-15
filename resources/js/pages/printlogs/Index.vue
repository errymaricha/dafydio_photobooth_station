<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { index as listPrintLogs } from '@/actions/App/Http/Controllers/Api/Editor/PrintLogController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';
import * as printOrderRoutes from '@/routes/print-orders';
import * as sessionsRoutes from '@/routes/sessions';

type PrintLogItem = {
    id: string;
    log_level?: string;
    message?: string;
    payload?: Record<string, unknown> | null;
    created_at?: string | null;
    print_order?: {
        id?: string | null;
        order_code?: string | null;
    };
    session?: {
        id?: string | null;
        session_code?: string | null;
    };
    queue_job?: {
        id?: string | null;
        status?: string | null;
    };
    printer?: {
        id?: string | null;
        name?: string | null;
    };
};

const { get } = useApi();

const logs = ref<PrintLogItem[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const level = ref('all');
const search = ref('');
const lastSyncedAt = ref<string | null>(null);
let refreshTimer: number | null = null;

const levelOptions = [
    { key: 'all', label: 'All' },
    { key: 'info', label: 'Info' },
    { key: 'warning', label: 'Warning' },
    { key: 'error', label: 'Error' },
];

const filteredLogs = computed(() => {
    return logs.value.filter((log) => {
        const haystack =
            `${log.message ?? ''} ${log.print_order?.order_code ?? ''} ${log.session?.session_code ?? ''} ${log.printer?.name ?? ''}`.toLowerCase();

        const matchesLevel =
            level.value === 'all' ||
            (log.log_level ?? '').toLowerCase() === level.value;

        const matchesSearch =
            !search.value || haystack.includes(search.value.toLowerCase());

        return matchesLevel && matchesSearch;
    });
});

const summaryItems = computed(() => {
    return levelOptions.map((option) => ({
        ...option,
        count:
            option.key === 'all'
                ? logs.value.length
                : logs.value.filter(
                      (log) =>
                          (log.log_level ?? '').toLowerCase() === option.key,
                  ).length,
    }));
});

const loadLogs = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        logs.value = await get<PrintLogItem[]>(listPrintLogs());
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
    await loadLogs();

    refreshTimer = window.setInterval(() => {
        void loadLogs(true);
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
        title="Print Logs"
        subtitle="Telusuri jejak order, queue, dan printer untuk membantu troubleshooting."
    >
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button
                v-for="item in summaryItems"
                :key="item.key"
                type="button"
                class="rounded-lg border px-3 py-2 text-xs font-semibold transition"
                :class="
                    level === item.key
                        ? 'border-blue-600 bg-blue-600 text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                "
                @click="level = item.key"
            >
                {{ item.label }}: {{ item.count }}
            </button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">
                        Activity Logs
                    </h2>
                    <p class="text-sm text-slate-500">
                        Menampilkan event terbaru dari alur print order dan print queue.
                    </p>
                </div>

                <div class="flex flex-1 flex-col gap-3 md:flex-row lg:max-w-2xl">
                    <input
                        v-model="search"
                        type="text"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        placeholder="Cari message, order, session, atau printer"
                    />

                    <select
                        v-model="level"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:w-48"
                    >
                        <option
                            v-for="option in levelOptions"
                            :key="option.key"
                            :value="option.key"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                <span>Synced: {{ lastSyncedAt ?? '-' }}</span>
                <span>
                    Menampilkan {{ filteredLogs.length }} dari
                    {{ logs.length }} log
                </span>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100"
                    :disabled="refreshing"
                    @click="loadLogs(true)"
                >
                    {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                </button>
            </div>

            <div v-if="loading" class="text-sm text-slate-500">
                Loading print logs...
            </div>

            <div v-else-if="!filteredLogs.length">
                <EmptyState
                    title="Belum ada log"
                    message="Log terbaru akan muncul di sini ketika workflow print mulai berjalan."
                />
            </div>

            <div v-else class="flex flex-col gap-4">
                <div
                    v-for="log in filteredLogs"
                    :key="log.id"
                    class="rounded-2xl border border-slate-200 p-4"
                    :class="{
                        'border-rose-200 bg-rose-50/60':
                            (log.log_level ?? '').toLowerCase() === 'error',
                    }"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <StatusBadge :status="log.log_level ?? 'info'" />
                                <div class="font-medium text-slate-900">
                                    {{ log.message ?? 'Log entry' }}
                                </div>
                            </div>

                            <div class="grid gap-2 text-sm text-slate-500 md:grid-cols-2 xl:grid-cols-4">
                                <p>Created: {{ log.created_at ?? '-' }}</p>
                                <p>Printer: {{ log.printer?.name ?? '-' }}</p>
                                <p>Queue Job: {{ log.queue_job?.id ?? '-' }}</p>
                                <p>Status: {{ log.queue_job?.status ?? '-' }}</p>
                            </div>

                            <pre
                                v-if="log.payload"
                                class="overflow-x-auto rounded-xl bg-slate-950 px-4 py-3 text-xs leading-6 text-slate-100"
                            >{{ JSON.stringify(log.payload, null, 2) }}</pre>
                        </div>

                        <div class="flex flex-col items-start gap-2 text-sm">
                            <Link
                                v-if="log.print_order?.id"
                                :href="printOrderRoutes.show.url(log.print_order.id)"
                                class="font-medium text-blue-600 hover:text-blue-700"
                            >
                                {{ log.print_order.order_code ?? 'Open Print Order' }}
                            </Link>

                            <Link
                                v-if="log.session?.id"
                                :href="sessionsRoutes.show.url(log.session.id)"
                                class="font-medium text-slate-700 hover:text-slate-900"
                            >
                                {{ log.session.session_code ?? 'Open Session' }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
