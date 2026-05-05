<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import { show as showPrinter } from '@/actions/App/Http/Controllers/Api/Editor/PrinterController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useAdaptivePolling } from '@/composables/useAdaptivePolling';
import { useApi } from '@/composables/useApi';
import * as printOrderRoutes from '@/routes/print-orders';
import * as printQueueRoutes from '@/routes/print-queue';
import * as sessionsRoutes from '@/routes/sessions';

type RecentJob = {
    id: string;
    status?: string;
    priority?: number;
    attempt_count?: number;
    queued_at?: string | null;
    processed_at?: string | null;
    finished_at?: string | null;
    last_error?: string | null;
    print_order?: {
        id?: string | null;
        order_code?: string | null;
    };
    session?: {
        id?: string | null;
        session_code?: string | null;
    };
};

type PrinterDetail = {
    id: string;
    printer_code?: string | null;
    printer_name: string;
    printer_type?: string | null;
    connection_type?: string | null;
    ip_address?: string | null;
    port?: number | string | null;
    driver_name?: string | null;
    paper_size_default?: string | null;
    is_default?: boolean;
    status?: string | null;
    is_online?: boolean;
    last_seen_at?: string | null;
    last_error?: string | null;
    station?: {
        id?: string | null;
        code?: string | null;
    };
    queue?: {
        pending?: number;
        processing?: number;
        failed?: number;
    };
    recent_jobs: RecentJob[];
};

const props = defineProps<{
    printerId: string;
}>();

const { get } = useApi();

const printer = ref<PrinterDetail | null>(null);
const loading = ref(true);
const refreshing = ref(false);
const lastSyncedAt = ref<string | null>(null);

const totalTrackedJobs = computed(() => {
    return (
        (printer.value?.queue?.pending ?? 0) +
        (printer.value?.queue?.processing ?? 0) +
        (printer.value?.queue?.failed ?? 0)
    );
});

const loadPrinter = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        printer.value = await get<PrinterDetail>(showPrinter(props.printerId));
        lastSyncedAt.value = new Date().toLocaleTimeString('id-ID');
    } finally {
        if (silent) {
            refreshing.value = false;
        } else {
            loading.value = false;
        }
    }
};

const polling = useAdaptivePolling(() => loadPrinter(true), {
    activeIntervalMs: 30_000,
    idleIntervalMs: 60_000,
    autoStart: false,
});

onMounted(async () => {
    await loadPrinter();
    polling.start();
});
</script>

<template>
    <AppLayout
        title="Printer Detail"
        subtitle="Pantau identitas printer, kondisi koneksi, dan job terbaru."
    >
        <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-[#6d6b77]">
            <span>Synced: {{ lastSyncedAt ?? '-' }}</span>
            <button
                type="button"
                class="rounded-lg border border-[#d8d4e7] px-3 py-2 font-semibold text-[#2f2b3dcc] hover:bg-[#f1f0f5]"
                :disabled="refreshing"
                @click="loadPrinter(true)"
            >
                {{ refreshing ? 'Refreshing...' : 'Refresh' }}
            </button>
        </div>

        <div v-if="loading" class="text-sm text-[#6d6b77]">
            Loading printer detail...
        </div>

        <div v-else-if="!printer">
            <EmptyState
                title="Printer tidak ditemukan"
                message="Periksa kembali printer yang dipilih dari halaman daftar printer."
            />
        </div>

        <div v-else class="space-y-6">
            <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <h2 class="text-2xl font-semibold text-[#2f2b3dcc]">
                                {{ printer.printer_name }}
                            </h2>
                            <StatusBadge :status="printer.status ?? 'unknown'" />
                        </div>

                        <div class="grid gap-2 text-sm text-[#6d6b77] md:grid-cols-2 xl:grid-cols-4">
                            <p>Code: {{ printer.printer_code ?? '-' }}</p>
                            <p>Station: {{ printer.station?.code ?? '-' }}</p>
                            <p>Type: {{ printer.printer_type ?? '-' }}</p>
                            <p>Driver: {{ printer.driver_name ?? '-' }}</p>
                            <p>Connection: {{ printer.connection_type ?? '-' }}</p>
                            <p>IP: {{ printer.ip_address ?? '-' }}</p>
                            <p>Port: {{ printer.port ?? '-' }}</p>
                            <p>Paper: {{ printer.paper_size_default ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-[#f5f5f9] px-4 py-3 text-sm text-[#6d6b77]">
                        <div class="font-medium text-[#2f2b3dcc]">
                            {{ printer.is_online ? 'Printer online' : 'Printer offline' }}
                        </div>
                        <div class="mt-1">
                            Last seen: {{ printer.last_seen_at ?? 'belum ada heartbeat' }}
                        </div>
                        <div v-if="printer.is_default" class="mt-2 text-xs font-medium text-[#685dd8]">
                            Printer default untuk station ini
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatsCard label="Pending Queue" :value="printer.queue?.pending ?? 0" />
                <StatsCard label="Processing" :value="printer.queue?.processing ?? 0" />
                <StatsCard label="Failed Queue" :value="printer.queue?.failed ?? 0" />
                <StatsCard label="Tracked Jobs" :value="totalTrackedJobs" />
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                                Recent Jobs
                            </h3>
                            <p class="text-sm text-[#6d6b77]">
                                Riwayat antrean terbaru yang pernah diarahkan ke printer ini.
                            </p>
                        </div>

                        <Link
                            :href="printQueueRoutes.index.url()"
                            class="text-sm font-medium text-[#7367f0] hover:text-[#685dd8]"
                        >
                            Open Queue
                        </Link>
                    </div>

                    <div class="mb-4 text-xs text-[#6d6b77]">
                        Menampilkan {{ printer.recent_jobs.length }} job terbaru
                    </div>

                    <div v-if="!printer.recent_jobs.length">
                        <EmptyState
                            title="Belum ada job"
                            message="Job print terbaru akan tampil di sini setelah printer dipakai."
                        />
                    </div>

                    <div v-else class="flex flex-col gap-4">
                        <div
                            v-for="job in printer.recent_jobs"
                            :key="job.id"
                            class="rounded-2xl border border-[#e8e6ef] p-4"
                            :class="{
                                'border-rose-200 bg-rose-50/60':
                                    (job.status ?? '').toLowerCase() ===
                                    'failed',
                            }"
                        >
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <div class="font-medium text-[#2f2b3dcc]">
                                            Job #{{ job.id }}
                                        </div>
                                        <StatusBadge :status="job.status ?? 'unknown'" />
                                    </div>

                                    <div class="grid gap-2 text-sm text-[#6d6b77] md:grid-cols-2">
                                        <p>Priority: {{ job.priority ?? 0 }}</p>
                                        <p>Attempts: {{ job.attempt_count ?? 0 }}</p>
                                        <p>Queued: {{ job.queued_at ?? '-' }}</p>
                                        <p>Finished: {{ job.finished_at ?? '-' }}</p>
                                    </div>

                                    <div v-if="job.last_error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">
                                        {{ job.last_error }}
                                    </div>
                                </div>

                                <div class="flex flex-col items-start gap-2 text-sm">
                                    <Link
                                        v-if="job.print_order?.id"
                                        :href="printOrderRoutes.show.url(job.print_order.id)"
                                        class="font-medium text-[#7367f0] hover:text-[#685dd8]"
                                    >
                                        {{ job.print_order.order_code ?? 'Open Print Order' }}
                                    </Link>

                                    <Link
                                        v-if="job.session?.id"
                                        :href="sessionsRoutes.show.url(job.session.id)"
                                        class="font-medium text-[#2f2b3dcc] hover:text-[#2f2b3dcc]"
                                    >
                                        {{ job.session.session_code ?? 'Open Session' }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                        <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                            Connection Summary
                        </h3>

                        <div class="mt-4 space-y-3 text-sm text-[#6d6b77]">
                            <div class="rounded-xl bg-[#f5f5f9] p-4">
                                <div class="text-xs uppercase tracking-wide text-[#b3b1bb]">
                                    Online State
                                </div>
                                <div class="mt-1 font-medium text-[#2f2b3dcc]">
                                    {{ printer.is_online ? 'Heartbeat aktif' : 'Tidak aktif' }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-[#f5f5f9] p-4">
                                <div class="text-xs uppercase tracking-wide text-[#b3b1bb]">
                                    Default Media
                                </div>
                                <div class="mt-1 font-medium text-[#2f2b3dcc]">
                                    {{ printer.paper_size_default ?? '-' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                        <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                            Last Error
                        </h3>

                        <div v-if="printer.last_error" class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ printer.last_error }}
                        </div>

                        <div v-else class="mt-4 rounded-xl bg-[#f5f5f9] px-4 py-3 text-sm text-[#6d6b77]">
                            Belum ada error terakhir yang tersimpan untuk printer ini.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

