<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import { index as listClients } from '@/actions/App/Http/Controllers/Api/Editor/CustomerCloudAccountController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { useApi } from '@/composables/useApi';
import * as clientsRoutes from '@/routes/clients';

type ClientItem = {
    customer_id: string;
    customer_whatsapp: string;
    username: string;
    tier?: string;
    has_cloud_password: boolean;
    account_status?: string | null;
    password_set_at?: string | null;
    sessions_count: number;
    paid_sessions_count: number;
    captured_photos_count: number;
    latest_session_at?: string | null;
};

const { get } = useApi();
const loading = ref(true);
const refreshing = ref(false);
const errorMessage = ref<string | null>(null);
const search = ref('');
const clients = ref<ClientItem[]>([]);

const filteredClients = computed(() => {
    return clients.value.filter((client) => {
        const haystack =
            `${client.customer_id} ${client.customer_whatsapp} ${client.username}`.toLowerCase();

        return !search.value || haystack.includes(search.value.toLowerCase());
    });
});

async function loadClients(silent = false): Promise<void> {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        errorMessage.value = null;
        const response = await get<{ customers?: ClientItem[] }>(listClients());
        clients.value = response.customers ?? [];
    } catch (error: unknown) {
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response
                ?.data?.message ?? 'Gagal memuat daftar client.';
    } finally {
        if (silent) {
            refreshing.value = false;
        } else {
            loading.value = false;
        }
    }
}

onMounted(async () => {
    await loadClients();
});
</script>

<template>
    <AppLayout
        title="Daftar Client"
        subtitle="Monitoring pelanggan berdasarkan nomor WhatsApp dan jumlah session photobooth."
    >
        <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
            <div
                class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <input
                    v-model="search"
                    type="text"
                    class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc] md:max-w-sm"
                    placeholder="Cari no WA / customer id"
                />

                <button
                    type="button"
                    class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9]"
                    :disabled="refreshing"
                    @click="loadClients(true)"
                >
                    {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                </button>
            </div>

            <div v-if="loading" class="text-sm text-[#6d6b77]">Loading clients...</div>

            <div
                v-else-if="errorMessage"
                class="rounded-lg border border-[#ffd5d9] bg-[#fff5f5] px-3 py-2 text-sm text-[#ea5455]"
            >
                {{ errorMessage }}
            </div>

            <div v-else-if="!filteredClients.length">
                <EmptyState
                    title="Belum ada client"
                    message="Client akan muncul setelah Android mengirim session dengan customer WhatsApp."
                />
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-[#e8e6ef] text-[#6d6b77]">
                        <tr>
                            <th class="px-4 py-3">Customer ID</th>
                            <th class="px-4 py-3">Session</th>
                            <th class="px-4 py-3">Paid</th>
                            <th class="px-4 py-3">Foto Booth</th>
                            <th class="px-4 py-3">Tier</th>
                            <th class="px-4 py-3">Cloud Password</th>
                            <th class="px-4 py-3">Last Session</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="client in filteredClients"
                            :key="client.customer_id"
                            class="border-b border-[#f1f0f5]"
                        >
                            <td class="px-4 py-3">
                                <div class="font-semibold text-[#2f2b3dcc]">
                                    {{ client.customer_id }}
                                </div>
                                <div class="text-xs text-[#6d6b77]">
                                    Username: {{ client.username }}
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ client.sessions_count }}</td>
                            <td class="px-4 py-3">{{ client.paid_sessions_count }}</td>
                            <td class="px-4 py-3">{{ client.captured_photos_count }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-[#f1f0f5] px-2 py-1 text-xs font-semibold text-[#6d6b77]">
                                    {{ client.tier ?? 'regular' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="
                                        client.has_cloud_password
                                            ? 'bg-[#e8f7ef] text-[#28c76f]'
                                            : 'bg-[#fff1e3] text-[#ff9f43]'
                                    "
                                >
                                    {{
                                        client.has_cloud_password
                                            ? 'Sudah diset'
                                            : 'Belum diset'
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-[#6d6b77]">
                                {{ client.latest_session_at ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="
                                        clientsRoutes.show.url(
                                            client.customer_whatsapp,
                                        )
                                    "
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
