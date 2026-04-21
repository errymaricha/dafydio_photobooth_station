<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import * as sessionApi from '@/actions/App/Http/Controllers/Api/Editor/SessionController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';
import * as sessionRoutes from '@/routes/sessions';

type SessionItem = {
    id: string;
    session_code?: string;
    name?: string;
    device_name?: string;
    device_code?: string;
    status?: string;
    created_at?: string;
    thumbnail_url?: string | null;
};

const { get } = useApi();
const sessionIndex =
    sessionApi.index ?? sessionApi.default?.index;
const sessions = ref<SessionItem[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const search = ref('');
const status = ref('all');
const lastSyncedAt = ref<string | null>(null);
let refreshTimer: number | null = null;

const statusOptions = [
    'uploaded',
    'editing',
    'ready_print',
    'queued_print',
    'failed_print',
    'printed',
];

const statusLabels: Record<string, string> = {
    all: 'All',
    uploaded: 'Uploaded',
    editing: 'Editing',
    ready_print: 'Ready',
    queued_print: 'Queued',
    failed_print: 'Failed',
    printed: 'Printed',
};

const filteredSessions = computed(() => {
    return sessions.value.filter((item) => {
        const haystack =
            `${item.id} ${item.session_code ?? ''} ${item.device_name ?? ''} ${item.device_code ?? ''}`.toLowerCase();

        const matchSearch =
            !search.value || haystack.includes(search.value.toLowerCase());

        const matchStatus =
            status.value === 'all' ||
            (item.status ?? '').toLowerCase() === status.value;

        return matchSearch && matchStatus;
    });
});

const statusSummary = computed(() => {
    return ['all', ...statusOptions].map((statusOption) => ({
        status: statusOption,
        label: statusLabels[statusOption] ?? statusOption,
        count:
            statusOption === 'all'
                ? sessions.value.length
                : sessions.value.filter((item) => item.status === statusOption)
                      .length,
    }));
});

const loadSessions = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        if (!sessionIndex) {
            throw new Error('Session index action is unavailable.');
        }

        const response = await get<{ data?: SessionItem[] } | SessionItem[]>(
            sessionIndex(),
        );

        sessions.value = Array.isArray(response)
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
    await loadSessions();

    refreshTimer = window.setInterval(() => {
        void loadSessions(true);
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
        title="Sessions"
        subtitle="Lihat hasil session device dan lanjutkan ke edit flow."
    >
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button
                v-for="item in statusSummary"
                :key="item.status"
                type="button"
                class="rounded-lg border px-3 py-2 text-xs font-semibold transition"
                :class="
                    status === item.status
                        ? 'border-[#7367f0] bg-[#7367f0] text-white'
                        : 'border-[#e8e6ef] bg-white text-[#6d6b77] hover:bg-[#f5f5f9]'
                "
                @click="status = item.status"
            >
                {{ item.label }}: {{ item.count }}
            </button>
        </div>

        <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
            <div
                class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex flex-1 flex-col gap-3 md:flex-row">
                    <input
                        v-model="search"
                        type="text"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc] md:max-w-sm"
                        placeholder="Cari session / device"
                    />

                    <select
                        v-model="status"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc] md:w-48"
                    >
                        <option value="all">All Status</option>
                        <option
                            v-for="option in statusOptions"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-3 text-xs text-[#6d6b77]">
                    <span>Synced: {{ lastSyncedAt ?? '-' }}</span>
                    <button
                        type="button"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2 font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9]"
                        :disabled="refreshing"
                        @click="loadSessions(true)"
                    >
                        {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                    </button>
                </div>
            </div>

            <div v-if="loading" class="text-sm text-[#6d6b77]">
                Loading sessions...
            </div>

            <div v-else-if="!filteredSessions.length">
                <EmptyState
                    title="Session tidak ditemukan"
                    message="Coba ganti kata kunci atau filter status."
                />
            </div>

            <div v-else class="overflow-x-auto">
                <div class="mb-3 text-xs text-[#6d6b77]">
                    Menampilkan {{ filteredSessions.length }} dari
                    {{ sessions.length }} sesi
                </div>
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-[#e8e6ef] text-[#6d6b77]">
                        <tr>
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Thumbnail</th>
                            <th class="px-4 py-3">Device</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="session in filteredSessions"
                            :key="session.id"
                            class="border-b border-[#f1f0f5]"
                        >
                            <td class="px-4 py-3 font-medium">
                                #{{ session.id }}
                            </td>
                            <td class="px-4 py-3">
                                <img
                                    v-if="session.thumbnail_url"
                                    :src="session.thumbnail_url"
                                    alt="thumbnail"
                                    class="h-14 w-14 rounded-lg object-cover"
                                />

                                <div
                                    v-else
                                    class="flex h-14 w-14 items-center justify-center rounded-lg bg-[#f5f5f9] text-xs text-[#b3b1bb]"
                                >
                                    No Image
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-medium text-[#2f2b3dcc]">
                                    {{ session.device_name ?? '-' }}
                                </div>
                                <div class="text-xs text-[#6d6b77]">
                                    {{
                                        session.session_code ??
                                        session.name ??
                                        'Session'
                                    }}
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="session.status ?? 'unknown'"
                                />
                            </td>

                            <td class="px-4 py-3 text-[#6d6b77]">
                                {{ session.created_at ?? '-' }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="sessionRoutes.show.url(session.id)"
                                    class="text-sm font-medium text-[#7367f0] hover:text-[#685dd8]"
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
