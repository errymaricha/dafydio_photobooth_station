<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { index as printersApiIndex } from '@/actions/App/Http/Controllers/Api/Editor/PrinterController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
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

const { get } = useApi();
const printers = ref<Printer[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const search = ref('');
const filter = ref('all');
const lastSyncedAt = ref<string | null>(null);
let refreshTimer: number | null = null;

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

const loadPrinters = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        const response = await get<{ data?: Printer[] } | Printer[]>(
            printersApiIndex(),
        );
        printers.value = Array.isArray(response)
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
    await loadPrinters();

    refreshTimer = window.setInterval(() => {
        void loadPrinters(true);
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

