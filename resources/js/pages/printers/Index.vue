<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import { index as printersApiIndex } from '@/actions/App/Http/Controllers/Api/Editor/PrinterController';
import {
    index as detectedPrintersApiIndex,
    store as storePrinterFromDetection,
} from '@/actions/App/Http/Controllers/Api/Editor/PrinterDiscoveryController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useAdaptivePolling } from '@/composables/useAdaptivePolling';
import { useApi } from '@/composables/useApi';
import * as printerRoutes from '@/routes/printers';

type Printer = {
    id: number | string;
    printer_name: string;
    status?: string;
    is_online?: boolean;
    station?: {
        code?: string;
    };
    queue?: {
        pending?: number;
        processing?: number;
        failed?: number;
    };
    last_error?: string | null;
};

type DetectedPrinter = {
    id: string;
    os_identifier: string;
    printer_name: string;
    connection_type?: string | null;
    ip_address?: string | null;
    port?: number | null;
    driver_name?: string | null;
    paper_size_default?: string | null;
    status?: string | null;
    linked_printer?: {
        id: string;
        printer_code?: string | null;
        printer_name?: string | null;
    } | null;
    station?: {
        code?: string | null;
    } | null;
    last_seen_at?: string | null;
};

const { get, post } = useApi();
const printers = ref<Printer[]>([]);
const detectedPrinters = ref<DetectedPrinter[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const actionMessage = ref<string | null>(null);
const actionError = ref<string | null>(null);
const linkingDetectionId = ref<string | null>(null);
const search = ref('');
const filter = ref('all');
const lastSyncedAt = ref<string | null>(null);

const statusOptions = [
    { key: 'all', label: 'All' },
    { key: 'online', label: 'Online' },
    { key: 'offline', label: 'Offline' },
    { key: 'ready', label: 'Ready' },
    { key: 'printing', label: 'Printing' },
    { key: 'error', label: 'Error' },
];

const normalizeStatus = (printer: Printer): string => {
    if (!printer.is_online) {
        return 'offline';
    }

    return (printer.status ?? 'ready').toLowerCase();
};

const filteredPrinters = computed(() => {
    return printers.value.filter((printer) => {
        const statusMatch =
            filter.value === 'all' ||
            (filter.value === 'online'
                ? printer.is_online
                : filter.value === 'offline'
                  ? !printer.is_online
                  : normalizeStatus(printer) === filter.value);

        const haystack =
            `${printer.printer_name} ${printer.station?.code ?? ''}`.toLowerCase();

        const searchMatch =
            !search.value || haystack.includes(search.value.toLowerCase());

        return statusMatch && searchMatch;
    });
});

const summaryItems = computed(() => {
    const total = printers.value.length;
    const online = printers.value.filter((printer) => printer.is_online).length;
    const offline = total - online;
    const ready = printers.value.filter(
        (printer) => normalizeStatus(printer) === 'ready',
    ).length;
    const printing = printers.value.filter(
        (printer) => normalizeStatus(printer) === 'printing',
    ).length;
    const error = printers.value.filter(
        (printer) => normalizeStatus(printer) === 'error',
    ).length;

    const counts: Record<string, number> = {
        all: total,
        online,
        offline,
        ready,
        printing,
        error,
    };

    return statusOptions.map((option) => ({
        ...option,
        count: counts[option.key] ?? 0,
    }));
});

const pendingDetections = computed(() => {
    return detectedPrinters.value.filter((item) => !item.linked_printer);
});

const normalizeDetectionsResponse = (
    payload: unknown,
): DetectedPrinter[] => {
    if (Array.isArray(payload)) {
        return payload as DetectedPrinter[];
    }

    if (
        payload &&
        typeof payload === 'object' &&
        'data' in payload &&
        Array.isArray((payload as { data: unknown }).data)
    ) {
        return (payload as { data: DetectedPrinter[] }).data;
    }

    if (
        payload &&
        typeof payload === 'object' &&
        'id' in payload &&
        'printer_name' in payload
    ) {
        return [payload as DetectedPrinter];
    }

    return [];
};

const loadPrinters = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        const [response, detectionResponse] = await Promise.all([
            get<{ data?: Printer[] } | Printer[]>(printersApiIndex()),
            get<DetectedPrinter[] | { data: DetectedPrinter[] } | DetectedPrinter>(
                detectedPrintersApiIndex(),
            ),
        ]);
        printers.value = Array.isArray(response)
            ? response
            : (response.data ?? []);
        detectedPrinters.value = normalizeDetectionsResponse(detectionResponse);
        lastSyncedAt.value = new Date().toLocaleTimeString('id-ID');
    } finally {
        if (silent) {
            refreshing.value = false;
        } else {
            loading.value = false;
        }
    }
};

const addPrinterFromDetection = async (detection: DetectedPrinter): Promise<void> => {
    actionMessage.value = null;
    actionError.value = null;
    linkingDetectionId.value = detection.id;

    try {
        await post(storePrinterFromDetection(detection.id), {});
        await loadPrinters(true);
        actionMessage.value = `Printer ${detection.printer_name} berhasil ditambahkan.`;
    } catch (error: unknown) {
        actionError.value =
            (error as { response?: { data?: { message?: string } } })?.response
                ?.data?.message ?? 'Gagal menambahkan printer dari hasil deteksi.';
    } finally {
        linkingDetectionId.value = null;
    }
};

const polling = useAdaptivePolling(() => loadPrinters(true), {
    activeIntervalMs: 30_000,
    idleIntervalMs: 60_000,
    autoStart: false,
});

onMounted(async () => {
    await loadPrinters();
    polling.start();
});
</script>

<template>
    <AppLayout
        title="Printers"
        subtitle="Monitor status printer dan jumlah antrean aktif."
    >
        <div class="mb-4 flex flex-wrap items-center gap-2">
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
                {{ item.label }}: {{ item.count }}
            </button>
        </div>

        <div
            v-if="actionMessage"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ actionMessage }}
        </div>

        <div
            v-if="actionError"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            {{ actionError }}
        </div>

        <div
            class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex flex-1 flex-col gap-3 md:flex-row">
                <input
                    v-model="search"
                    type="text"
                    class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm md:max-w-sm"
                    placeholder="Cari printer / station"
                />
                <select
                    v-model="filter"
                    class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm md:w-44"
                >
                    <option
                        v-for="option in statusOptions"
                        :key="option.key"
                        :value="option.key"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div class="flex items-center gap-3 text-xs text-[#6d6b77]">
                <span>Synced: {{ lastSyncedAt ?? '-' }}</span>
                <button
                    type="button"
                    class="rounded-lg border border-[#d8d4e7] px-3 py-2 font-semibold text-[#2f2b3dcc] hover:bg-[#f1f0f5]"
                    :disabled="refreshing"
                    @click="loadPrinters(true)"
                >
                    {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                </button>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-[#2f2b3dcc]">
                    Deteksi Printer OS
                </h2>
                <span class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-semibold text-[#6d6b77]">
                    {{ pendingDetections.length }} belum ditambahkan
                </span>
            </div>

            <div v-if="!pendingDetections.length" class="text-sm text-[#6d6b77]">
                Belum ada hasil deteksi baru dari print-agent.
            </div>

            <div v-else class="grid gap-3 md:grid-cols-2">
                <div
                    v-for="detection in pendingDetections"
                    :key="detection.id"
                    class="rounded-lg border border-[#e8e6ef] bg-[#f5f5f9] p-4"
                >
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-[#2f2b3dcc]">
                                {{ detection.printer_name }}
                            </p>
                            <p class="text-xs text-[#6d6b77]">
                                Station: {{ detection.station?.code ?? '-' }}
                            </p>
                        </div>
                        <StatusBadge :status="detection.status ?? 'ready'" />
                    </div>
                    <p class="text-xs text-[#6d6b77]">
                        {{ detection.connection_type ?? 'network' }}
                        <span v-if="detection.ip_address"> | {{ detection.ip_address }}:{{ detection.port ?? '-' }}</span>
                        <span v-if="detection.driver_name"> | {{ detection.driver_name }}</span>
                    </p>
                    <button
                        type="button"
                        class="mt-3 rounded-lg bg-[#7367f0] px-3 py-2 text-xs font-semibold text-white hover:bg-[#685dd8] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="linkingDetectionId === detection.id"
                        @click="addPrinterFromDetection(detection)"
                    >
                        {{
                            linkingDetectionId === detection.id
                                ? 'Menambahkan...'
                                : 'Tambahkan ke Printer'
                        }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-sm text-[#6d6b77]">
            Loading printers...
        </div>

        <div v-else-if="!filteredPrinters.length">
            <EmptyState
                title="Belum ada printer"
                message="Printer yang terdaftar akan muncul di sini."
            />
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="text-xs text-[#6d6b77] md:col-span-2 xl:col-span-3">
                Menampilkan {{ filteredPrinters.length }} dari
                {{ printers.length }} printer
            </div>

            <div
                v-for="printer in filteredPrinters"
                :key="printer.id"
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">
                            {{ printer.printer_name }}
                        </h2>
                        <p class="mt-1 text-sm text-[#6d6b77]">
                            Station: {{ printer.station?.code ?? '-' }}
                        </p>
                    </div>

                    <StatusBadge
                        :status="
                            printer.is_online
                                ? (printer.status ?? 'ready')
                                : 'offline'
                        "
                    />
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-[#f5f5f9] p-3">
                        <div class="text-xs text-[#6d6b77]">Queue</div>
                        <div class="mt-1 text-xl font-semibold">
                            {{
                                (printer.queue?.pending ?? 0) +
                                (printer.queue?.processing ?? 0)
                            }}
                        </div>
                    </div>

                    <div class="rounded-lg bg-[#f5f5f9] p-3">
                        <div class="text-xs text-[#6d6b77]">Failed Jobs</div>
                        <div class="mt-1 text-xl font-semibold">
                            {{ printer.queue?.failed ?? 0 }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between text-sm">
                    <span class="truncate text-[#6d6b77]">
                        {{ printer.last_error ?? 'Tidak ada error terbaru.' }}
                    </span>

                    <Link
                        :href="printerRoutes.show.url(printer.id)"
                        class="font-medium text-[#7367f0] hover:text-[#685dd8]"
                    >
                        Detail
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

