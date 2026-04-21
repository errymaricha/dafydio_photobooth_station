<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import {
    history as showCustomerHistory,
    upsert as upsertCustomerCloudAccount,
} from '@/actions/App/Http/Controllers/Api/Editor/CustomerCloudAccountController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';
import * as clientsRoutes from '@/routes/clients';

type FileInfo = {
    file_id?: string;
    file_url?: string | null;
    file_name?: string | null;
    file_path?: string | null;
};

type SessionPhotoItem = {
    photo_id: string;
    capture_index: number;
    original_file?: FileInfo | null;
    thumbnail_file?: FileInfo | null;
    file?: FileInfo | null;
};

type RenderedOutputItem = {
    rendered_output_id: string;
    version_no: number;
    is_active: boolean;
    file?: FileInfo | null;
};

type SessionItem = {
    session_id: string;
    session_code: string;
    station_code?: string | null;
    status?: string | null;
    payment_status?: string | null;
    payment_method?: string | null;
    created_at?: string | null;
    completed_at?: string | null;
    photos: SessionPhotoItem[];
    rendered_outputs: RenderedOutputItem[];
};

type CustomerHistoryResponse = {
    customer?: {
        customer_id?: string;
        customer_whatsapp?: string;
        username?: string;
        has_cloud_password?: boolean;
    };
    summary?: {
        sessions_count?: number;
        paid_sessions_count?: number;
        photos_count?: number;
        rendered_outputs_count?: number;
    };
    sessions?: SessionItem[];
};

const props = defineProps<{
    customerWhatsapp: string;
}>();

const { get, post } = useApi();
const loading = ref(true);
const refreshing = ref(false);
const savingPassword = ref(false);
const passwordFeedback = ref<string | null>(null);
const passwordError = ref<string | null>(null);
const cloudPassword = ref('');
const cloudPasswordConfirmation = ref('');
const customer = ref<CustomerHistoryResponse['customer'] | null>(null);
const summary = ref<CustomerHistoryResponse['summary'] | null>(null);
const sessions = ref<SessionItem[]>([]);

const totalOriginalPhotos = computed(() => {
    return sessions.value.reduce(
        (count, session) => count + session.photos.length,
        0,
    );
});

async function loadDetail(silent = false): Promise<void> {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        const response = await get<CustomerHistoryResponse>(
            showCustomerHistory(props.customerWhatsapp),
        );

        customer.value = response.customer ?? null;
        summary.value = response.summary ?? null;
        sessions.value = response.sessions ?? [];
    } finally {
        if (silent) {
            refreshing.value = false;
        } else {
            loading.value = false;
        }
    }
}

function resolvePhotoUrl(photo: SessionPhotoItem): string | null {
    return (
        photo.thumbnail_file?.file_url ??
        photo.original_file?.file_url ??
        photo.file?.file_url ??
        null
    );
}

function resolveRenderedUrl(output: RenderedOutputItem): string | null {
    return output.file?.file_url ?? null;
}

function normalizeApiError(error: unknown, fallbackMessage: string): string {
    return (
        (error as { response?: { data?: { message?: string } } })?.response
            ?.data?.message ?? fallbackMessage
    );
}

async function saveCloudPassword(): Promise<void> {
    if (!customer.value?.customer_whatsapp) {
        return;
    }

    if (!cloudPassword.value.trim()) {
        passwordError.value = 'Password wajib diisi.';
        passwordFeedback.value = null;

        return;
    }

    if (!cloudPasswordConfirmation.value.trim()) {
        passwordError.value = 'Konfirmasi password wajib diisi.';
        passwordFeedback.value = null;

        return;
    }

    savingPassword.value = true;
    passwordError.value = null;
    passwordFeedback.value = null;

    try {
        await post(upsertCustomerCloudAccount(), {
            customer_whatsapp: customer.value.customer_whatsapp,
            password: cloudPassword.value,
            password_confirmation: cloudPasswordConfirmation.value,
        });

        cloudPassword.value = '';
        cloudPasswordConfirmation.value = '';
        passwordFeedback.value = 'Password cloud berhasil diperbarui.';

        await loadDetail(true);
    } catch (error: unknown) {
        passwordError.value = normalizeApiError(
            error,
            'Gagal menyimpan password cloud.',
        );
    } finally {
        savingPassword.value = false;
    }
}

onMounted(async () => {
    await loadDetail();
});
</script>

<template>
    <AppLayout
        title="Detail Client"
        subtitle="Riwayat session, foto asli, dan hasil foto gabungan template."
    >
        <div class="space-y-6">
            <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm text-[#6d6b77]">Customer ID</div>
                        <div class="text-xl font-semibold text-[#2f2b3dcc]">
                            {{ customer?.customer_id ?? props.customerWhatsapp }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link
                            :href="clientsRoutes.index.url()"
                            class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm font-medium text-[#2f2b3dcc] hover:bg-[#f5f5f9]"
                        >
                            Kembali
                        </Link>
                        <button
                            type="button"
                            class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm font-medium text-[#2f2b3dcc] hover:bg-[#f5f5f9]"
                            :disabled="refreshing"
                            @click="loadDetail(true)"
                        >
                            {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded-lg bg-[#f5f5f9] p-3">
                        <div class="text-xs text-[#6d6b77]">Total Session</div>
                        <div class="text-lg font-semibold text-[#2f2b3dcc]">
                            {{ summary?.sessions_count ?? 0 }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-[#f5f5f9] p-3">
                        <div class="text-xs text-[#6d6b77]">Paid Session</div>
                        <div class="text-lg font-semibold text-[#2f2b3dcc]">
                            {{ summary?.paid_sessions_count ?? 0 }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-[#f5f5f9] p-3">
                        <div class="text-xs text-[#6d6b77]">Foto Booth</div>
                        <div class="text-lg font-semibold text-[#2f2b3dcc]">
                            {{ summary?.photos_count ?? totalOriginalPhotos }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-[#f5f5f9] p-3">
                        <div class="text-xs text-[#6d6b77]">Hasil Template</div>
                        <div class="text-lg font-semibold text-[#2f2b3dcc]">
                            {{ summary?.rendered_outputs_count ?? 0 }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                        Keamanan Cloud
                    </h3>
                    <p class="text-sm text-[#6d6b77]">
                        Set atau reset password histori client untuk login di photobooth cloud.
                    </p>
                </div>

                <div
                    class="mb-3 rounded-lg border border-[#e8e6ef] bg-[#f5f5f9] p-3 text-sm text-[#2f2b3dcc]"
                >
                    Status password:
                    <span
                        class="font-semibold"
                        :class="
                            customer?.has_cloud_password
                                ? 'text-[#28c76f]'
                                : 'text-[#ff9f43]'
                        "
                    >
                        {{
                            customer?.has_cloud_password
                                ? 'sudah diset'
                                : 'belum diset'
                        }}
                    </span>
                </div>

                <div
                    v-if="passwordFeedback"
                    class="mb-3 rounded-lg border border-[#c8f1da] bg-[#f0fcf5] px-3 py-2 text-sm text-[#28c76f]"
                >
                    {{ passwordFeedback }}
                </div>

                <div
                    v-if="passwordError"
                    class="mb-3 rounded-lg border border-[#ffd5d9] bg-[#fff5f5] px-3 py-2 text-sm text-[#ea5455]"
                >
                    {{ passwordError }}
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-[#2f2b3dcc]">
                            Password Baru
                        </span>
                        <input
                            v-model="cloudPassword"
                            type="password"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                            placeholder="Minimal 8 karakter, kombinasi huruf/angka/simbol"
                        />
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-[#2f2b3dcc]">
                            Konfirmasi Password
                        </span>
                        <input
                            v-model="cloudPasswordConfirmation"
                            type="password"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                            placeholder="Ulangi password"
                        />
                    </label>
                </div>

                <div class="mt-4">
                    <button
                        type="button"
                        class="rounded-lg bg-[#7367f0] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#685dd8] disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="savingPassword"
                        @click="saveCloudPassword"
                    >
                        {{ savingPassword ? 'Menyimpan...' : 'Simpan Password Cloud' }}
                    </button>
                </div>
            </div>

            <div v-if="loading" class="text-sm text-[#6d6b77]">Loading detail client...</div>

            <div v-else-if="!sessions.length">
                <EmptyState
                    title="Belum ada riwayat session"
                    message="Session pelanggan ini akan muncul setelah ada upload dari device Android."
                />
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="session in sessions"
                    :key="session.session_id"
                    class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                >
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-[#2f2b3dcc]">
                                {{ session.session_code }}
                            </div>
                            <div class="text-xs text-[#6d6b77]">
                                Station: {{ session.station_code ?? '-' }} •
                                Created: {{ session.created_at ?? '-' }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <StatusBadge :status="session.status ?? 'unknown'" />
                            <StatusBadge
                                :status="session.payment_status ?? 'pending'"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <div class="mb-2 text-sm font-semibold text-[#2f2b3dcc]">
                                Foto Booth (Original)
                            </div>
                            <div
                                v-if="!session.photos.length"
                                class="rounded-lg border border-[#e8e6ef] bg-[#f5f5f9] p-3 text-xs text-[#6d6b77]"
                            >
                                Belum ada foto original.
                            </div>
                            <div
                                v-else
                                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                            >
                                <a
                                    v-for="photo in session.photos"
                                    :key="photo.photo_id"
                                    :href="
                                        photo.original_file?.file_url ??
                                        photo.file?.file_url ??
                                        '#'
                                    "
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block overflow-hidden rounded-lg border border-[#e8e6ef]"
                                >
                                    <img
                                        v-if="resolvePhotoUrl(photo)"
                                        :src="resolvePhotoUrl(photo) ?? ''"
                                        :alt="`Photo ${photo.capture_index}`"
                                        class="h-28 w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-28 w-full items-center justify-center bg-[#f1f0f5] text-xs text-[#6d6b77]"
                                    >
                                        No Preview
                                    </div>
                                    <div class="px-2 py-1 text-xs text-[#6d6b77]">
                                        #{{ photo.capture_index }}
                                    </div>
                                </a>
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 text-sm font-semibold text-[#2f2b3dcc]">
                                Hasil Gabung Template
                            </div>
                            <div
                                v-if="!session.rendered_outputs.length"
                                class="rounded-lg border border-[#e8e6ef] bg-[#f5f5f9] p-3 text-xs text-[#6d6b77]"
                            >
                                Belum ada hasil render template.
                            </div>
                            <div
                                v-else
                                class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                            >
                                <a
                                    v-for="output in session.rendered_outputs"
                                    :key="output.rendered_output_id"
                                    :href="resolveRenderedUrl(output) ?? '#'"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block overflow-hidden rounded-lg border border-[#e8e6ef]"
                                >
                                    <img
                                        v-if="resolveRenderedUrl(output)"
                                        :src="resolveRenderedUrl(output) ?? ''"
                                        :alt="`Rendered v${output.version_no}`"
                                        class="h-36 w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-36 w-full items-center justify-center bg-[#f1f0f5] text-xs text-[#6d6b77]"
                                    >
                                        No Preview
                                    </div>
                                    <div class="px-2 py-1 text-xs text-[#6d6b77]">
                                        v{{ output.version_no }}
                                        <span
                                            v-if="output.is_active"
                                            class="font-semibold text-[#28c76f]"
                                        >
                                            • active
                                        </span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

