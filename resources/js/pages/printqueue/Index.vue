<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import {
    indexJobs as printQueueJobsIndex,
    retry as retryPrintQueueJob,
    summary as printQueueSummary,
} from '@/actions/App/Http/Controllers/Api/Editor/PrintQueueController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useAdaptivePolling } from '@/composables/useAdaptivePolling';
import { useApi } from '@/composables/useApi';

type QueueJob = {
    id: number | string;
    status?: string;
    queued_at?: string;
    last_error?: string | null;
    print_order?: {
        order_code?: string;
    };
    session?: {
        session_code?: string;
    };
    printer?: {
        name?: string;
    };
};

type QueueSummary = {
    pending?: number;
    processing?: number;
    failed?: number;
    completed?: number;
};

const { get, post } = useApi();

const jobs = ref<QueueJob[]>([]);
const summary = ref<QueueSummary>({});
const loading = ref(true);
const refreshing = ref(false);
const filter = ref('all');
const search = ref('');
const lastSyncedAt = ref<string | null>(null);

const filterOptions = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'processing', label: 'Processing' },
    { key: 'failed', label: 'Failed' },
    { key: 'completed', label: 'Completed' },
];

const summaryItems = computed(() => {
    const totalJobs = jobs.value.length;

    return filterOptions.map((option) => {
        if (option.key === 'all') {
            return { ...option, count: totalJobs };
        }

        return {
            ...option,
            count: summary.value[
                option.key as keyof QueueSummary
            ] as number | undefined,
        };
    });
});

const filteredJobs = computed(() => {
    return jobs.value.filter((job) => {
        const matchesFilter =
            filter.value === 'all' ||
            (job.status ?? '').toLowerCase() === filter.value;

        const haystack =
            `${job.id} ${job.printer?.name ?? ''} ${job.print_order?.order_code ?? ''} ${job.session?.session_code ?? ''}`.toLowerCase();

        const matchesSearch =
            !search.value || haystack.includes(search.value.toLowerCase());

        return matchesFilter && matchesSearch;
    });
});

const loadData = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        const [jobsData, summaryData] = await Promise.all([
            get<{ data?: QueueJob[] } | QueueJob[]>(printQueueJobsIndex()),
            get<QueueSummary>(printQueueSummary()),
        ]);

        jobs.value = Array.isArray(jobsData) ? jobsData : (jobsData.data ?? []);
        summary.value = summaryData;
        lastSyncedAt.value = new Date().toLocaleTimeString('id-ID');
    } finally {
        if (silent) {
            refreshing.value = false;
        } else {
            loading.value = false;
        }
    }
};

const retryJob = async (jobId: number | string): Promise<void> => {
    await post(retryPrintQueueJob(`${jobId}`));
    await loadData(true);
};

const polling = useAdaptivePolling(() => loadData(true), {
    activeIntervalMs: () =>
        (summary.value.pending ?? 0) + (summary.value.processing ?? 0) > 0
            ? 10_000
            : 30_000,
    idleIntervalMs: 60_000,
    autoStart: false,
});

onMounted(async () => {
    await loadData();
    polling.start();
});
</script>

<template>
    <AppLayout
        title="Print Queue"
        subtitle="Monitor antrean print dan retry job yang gagal."
    >
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
            >
                <div class="text-sm text-[#6d6b77]">Pending</div>
                <div class="mt-2 text-3xl font-bold">
                    {{ summary.pending ?? 0 }}
                </div>
            </div>

            <div
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
            >
                <div class="text-sm text-[#6d6b77]">Processing</div>
                <div class="mt-2 text-3xl font-bold">
                    {{ summary.processing ?? 0 }}
                </div>
            </div>

            <div
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
            >
                <div class="text-sm text-[#6d6b77]">Failed</div>
                <div class="mt-2 text-3xl font-bold">
                    {{ summary.failed ?? 0 }}
                </div>
            </div>

            <div
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
            >
                <div class="text-sm text-[#6d6b77]">Completed</div>
                <div class="mt-2 text-3xl font-bold">
                    {{ summary.completed ?? 0 }}
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button
                v-for="item in summaryItems"
                :key="item.key"
                type="button"
                class="rounded-lg border px-3 py-2 text-xs font-semibold transition"
                :class="
                    filter === item.key
                        ? 'border-[#7367f0] bg-[#7367f0] text-white'
                        : 'border-[#e8e6ef] bg-white text-[#6d6b77] hover:bg-[#f5f5f9]'
                "
                @click="filter = item.key"
            >
                {{ item.label }}: {{ item.count ?? 0 }}
            </button>
        </div>

        <div
            class="mt-6 rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
        >
            <div
                class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex flex-1 flex-col gap-3 md:flex-row">
                    <h2 class="text-lg font-semibold">Queue Jobs</h2>
                    <input
                        v-model="search"
                        type="text"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm md:max-w-sm"
                        placeholder="Cari job / order / session / printer"
                    />
                </div>

                <div class="flex items-center gap-3">
                    <select
                        v-model="filter"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                    >
                        <option
                            v-for="option in filterOptions"
                            :key="option.key"
                            :value="option.key"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-xs font-semibold text-[#2f2b3dcc] hover:bg-[#f1f0f5]"
                        :disabled="refreshing"
                        @click="loadData(true)"
                    >
                        {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                    </button>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-[#6d6b77]">
                <span>Synced: {{ lastSyncedAt ?? '-' }}</span>
                <span>
                    Menampilkan {{ filteredJobs.length }} dari
                    {{ jobs.length }} job
                </span>
            </div>

            <div v-if="loading" class="text-sm text-[#6d6b77]">
                Loading queue...
            </div>

            <div v-else-if="!filteredJobs.length">
                <EmptyState
                    title="Queue kosong"
                    message="Belum ada job pada filter ini."
                />
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-[#e8e6ef] text-[#6d6b77]">
                        <tr>
                            <th class="px-4 py-3">Job</th>
                            <th class="px-4 py-3">Order / Session</th>
                            <th class="px-4 py-3">Printer</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Error</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="job in filteredJobs"
                            :key="job.id"
                            class="border-b border-[#f1f0f5]"
                            :class="{
                                'bg-rose-50/60':
                                    (job.status ?? '').toLowerCase() ===
                                    'failed',
                            }"
                        >
                            <td class="px-4 py-3 font-medium text-[#2f2b3dcc]">
                                #{{ job.id }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-[#2f2b3dcc]">
                                    {{ job.print_order?.order_code ?? '-' }}
                                </div>
                                <div class="text-xs text-[#6d6b77]">
                                    {{ job.session?.session_code ?? '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                {{ job.printer?.name ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="job.status ?? 'unknown'"
                                />
                            </td>
                            <td class="max-w-[220px] px-4 py-3 text-xs text-[#6d6b77]">
                                {{ job.last_error ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-[#6d6b77]">
                                {{ job.queued_at ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    v-if="
                                        (job.status ?? '').toLowerCase() ===
                                        'failed'
                                    "
                                    type="button"
                                    class="rounded-lg bg-[#7367f0] px-3 py-2 text-xs font-medium text-white hover:bg-[#685dd8]"
                                    @click="retryJob(job.id)"
                                >
                                    Retry
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

