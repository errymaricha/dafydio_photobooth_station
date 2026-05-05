<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { MessageCircle } from 'lucide-vue-next';

import {
    history as showCustomerHistory,
    upsert as upsertCustomerCloudAccount,
} from '@/actions/App/Http/Controllers/Api/Editor/CustomerCloudAccountController';
import { update as updateCustomerTier } from '@/actions/App/Http/Controllers/Api/Editor/CustomerTierController';
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
        tier?: string;
        active_subscription?: {
            id?: string;
            status?: string;
            package_code?: string | null;
            package_name?: string | null;
            start_at?: string | null;
            end_at?: string | null;
        } | null;
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

const { get, post, patch } = useApi();
const loading = ref(true);
const refreshing = ref(false);
const savingTier = ref(false);
const tierFeedback = ref<string | null>(null);
const tierError = ref<string | null>(null);
const savingPassword = ref(false);
const passwordFeedback = ref<string | null>(null);
const passwordError = ref<string | null>(null);
const cloudPassword = ref('');
const cloudPasswordConfirmation = ref('');
const lastSharedCloudPassword = ref('');
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

function resolveOriginalAssetUrl(photo: SessionPhotoItem): string | null {
    return photo.original_file?.file_url ?? photo.file?.file_url ?? null;
}

function resolveRenderedUrl(output: RenderedOutputItem): string | null {
    return output.file?.file_url ?? null;
}

function normalizeWhatsappNumber(value?: string | null): string {
    const digits = (value ?? '').replace(/\D/g, '');

    if (digits.startsWith('0')) {
        return `62${digits.slice(1)}`;
    }

    if (digits.startsWith('8')) {
        return `62${digits}`;
    }

    return digits;
}

function clientLoginUrl(): string {
    if (typeof window === 'undefined') {
        return '/login';
    }

    return `${window.location.origin}/login`;
}

function sessionOriginalAssetUrls(session: SessionItem): string[] {
    return session.photos
        .map((photo) => resolveOriginalAssetUrl(photo))
        .filter((url): url is string => Boolean(url));
}

function sessionRenderedAssetUrls(session: SessionItem): string[] {
    return session.rendered_outputs
        .map((output) => resolveRenderedUrl(output))
        .filter((url): url is string => Boolean(url));
}

function cloudCredentialLines(): string[] {
    if (!customer.value?.has_cloud_password) {
        return [];
    }

    const passwordForMessage =
        cloudPassword.value.trim() || lastSharedCloudPassword.value;

    return [
        '',
        'Akses Cloud Client:',
        `Url Login Client: ${clientLoginUrl()}`,
        `Username: ${customer.value.username ?? customer.value.customer_whatsapp ?? props.customerWhatsapp}`,
        `Password: ${passwordForMessage || '(password cloud sudah diset, reset password untuk dikirim otomatis)'}`,
    ];
}

function whatsappSessionAssetsUrl(session: SessionItem): string {
    const phone = normalizeWhatsappNumber(
        customer.value?.customer_whatsapp ?? props.customerWhatsapp,
    );
    const originalUrls = sessionOriginalAssetUrls(session);
    const renderedUrls = sessionRenderedAssetUrls(session);
    const message = [
        'Halo, berikut hasil Photobooth dari Dafydio Photobooth.',
        `Session: ${session.session_code}`,
        '',
        'dengan Asset foto original',
        ...(originalUrls.length
            ? originalUrls.map((url) => `- ${url}`)
            : ['- belum ada asset foto original']),
        '',
        'dan Asset foto dengan Frame',
        ...(renderedUrls.length
            ? renderedUrls.map((url) => `- ${url}`)
            : ['- belum ada asset foto dengan frame']),
        ...cloudCredentialLines(),
    ]
        .filter(Boolean)
        .join('\n');

    return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}

function normalizeApiError(error: unknown, fallbackMessage: string): string {
    return (
        (error as { response?: { data?: { message?: string } } })?.response
            ?.data?.message ?? fallbackMessage
    );
}

async function setCustomerTier(tier: 'regular' | 'premium'): Promise<void> {
    if (!customer.value?.customer_whatsapp) {
        return;
    }

    savingTier.value = true;
    tierFeedback.value = null;
    tierError.value = null;

    try {
        const response = await patch<{
            message?: string;
            customer_tier?: string;
        }>(updateCustomerTier(customer.value.customer_whatsapp), {
            tier,
            notes:
                tier === 'premium'
                    ? 'Set premium lokal station.'
                    : 'Kembali ke regular lokal station.',
        });

        customer.value = {
            ...customer.value,
            tier: response.customer_tier ?? tier,
            active_subscription:
                tier === 'regular' ? null : customer.value.active_subscription,
        };
        tierFeedback.value =
            response.message ??
            (tier === 'premium'
                ? 'Customer berhasil dijadikan premium lokal.'
                : 'Customer berhasil dikembalikan ke regular.');

        await loadDetail(true);
    } catch (error: unknown) {
        tierError.value = normalizeApiError(
            error,
            'Gagal memperbarui tier customer.',
        );
    } finally {
        savingTier.value = false;
    }
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
        const savedPassword = cloudPassword.value.trim();

        await post(upsertCustomerCloudAccount(), {
            customer_whatsapp: customer.value.customer_whatsapp,
            password: cloudPassword.value,
            password_confirmation: cloudPasswordConfirmation.value,
        });

        lastSharedCloudPassword.value = savedPassword;
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
            <div
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm text-[#6d6b77]">Customer ID</div>
                        <div class="text-xl font-semibold text-[#2f2b3dcc]">
                            {{
                                customer?.customer_id ?? props.customerWhatsapp
                            }}
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

            <div
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
            >
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                >
                    <div>
                        <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                            Tier Lokal Station
                        </h3>
                        <p class="text-sm text-[#6d6b77]">
                            Regular untuk customer biasa. Premium/VIP lokal bisa
                            dipakai operator untuk benefit station seperti
                            promo, bonus print, atau catatan prioritas.
                        </p>
                    </div>

                    <span
                        class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold"
                        :class="
                            (customer?.tier ?? 'regular') === 'premium'
                                ? 'bg-[#edeafd] text-[#685dd8]'
                                : 'bg-[#f1f0f5] text-[#6d6b77]'
                        "
                    >
                        {{ (customer?.tier ?? 'regular').toUpperCase() }}
                    </span>
                </div>

                <div
                    v-if="tierFeedback"
                    class="mt-4 rounded-lg border border-[#c8f1da] bg-[#f0fcf5] px-3 py-2 text-sm text-[#28c76f]"
                >
                    {{ tierFeedback }}
                </div>

                <div
                    v-if="tierError"
                    class="mt-4 rounded-lg border border-[#ffd5d9] bg-[#fff5f5] px-3 py-2 text-sm text-[#ea5455]"
                >
                    {{ tierError }}
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9] disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="
                            savingTier ||
                            (customer?.tier ?? 'regular') === 'regular'
                        "
                        @click="setCustomerTier('regular')"
                    >
                        Jadikan Regular
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-[#7367f0] px-3 py-2 text-sm font-semibold text-white hover:bg-[#685dd8] disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="savingTier || customer?.tier === 'premium'"
                        @click="setCustomerTier('premium')"
                    >
                        {{
                            savingTier ? 'Menyimpan...' : 'Jadikan Premium/VIP'
                        }}
                    </button>
                </div>

                <div
                    v-if="customer?.active_subscription"
                    class="mt-4 rounded-lg border border-[#e8e6ef] bg-[#f5f5f9] p-3 text-xs text-[#6d6b77]"
                >
                    Subscription lama aktif:
                    {{
                        customer.active_subscription.package_name ??
                        customer.active_subscription.package_code ??
                        '-'
                    }}
                    sampai
                    {{ customer.active_subscription.end_at ?? '-' }}. Menjadikan
                    customer Regular akan membatalkan subscription aktif
                    tersebut.
                </div>
            </div>

            <div
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
            >
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                        Keamanan Cloud
                    </h3>
                    <p class="text-sm text-[#6d6b77]">
                        Set atau reset password histori client untuk login di
                        photobooth cloud.
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
                        {{
                            savingPassword
                                ? 'Menyimpan...'
                                : 'Simpan Password Cloud'
                        }}
                    </button>
                </div>
            </div>

            <div v-if="loading" class="text-sm text-[#6d6b77]">
                Loading detail client...
            </div>

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
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
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
                            <a
                                v-if="
                                    sessionOriginalAssetUrls(session).length ||
                                    sessionRenderedAssetUrls(session).length
                                "
                                :href="whatsappSessionAssetsUrl(session)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                            >
                                <MessageCircle class="h-3.5 w-3.5" />
                                Kirim Semua Asset WA
                            </a>
                            <StatusBadge
                                :status="session.status ?? 'unknown'"
                            />
                            <StatusBadge
                                :status="session.payment_status ?? 'pending'"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div>
                            <div
                                class="mb-2 text-sm font-semibold text-[#2f2b3dcc]"
                            >
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
                                <div
                                    v-for="photo in session.photos"
                                    :key="photo.photo_id"
                                    class="overflow-hidden rounded-lg border border-[#e8e6ef]"
                                >
                                    <a
                                        :href="
                                            resolveOriginalAssetUrl(photo) ??
                                            '#'
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block"
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
                                    </a>
                                    <div
                                        class="space-y-2 px-2 py-2 text-xs text-[#6d6b77]"
                                    >
                                        <div>#{{ photo.capture_index }}</div>
                                        <span class="text-[#8a8794]">
                                            Original asset
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div
                                class="mb-2 text-sm font-semibold text-[#2f2b3dcc]"
                            >
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
                                <div
                                    v-for="output in session.rendered_outputs"
                                    :key="output.rendered_output_id"
                                    class="overflow-hidden rounded-lg border border-[#e8e6ef]"
                                >
                                    <a
                                        :href="
                                            resolveRenderedUrl(output) ?? '#'
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block"
                                    >
                                        <img
                                            v-if="resolveRenderedUrl(output)"
                                            :src="
                                                resolveRenderedUrl(output) ?? ''
                                            "
                                            :alt="`Rendered v${output.version_no}`"
                                            class="h-36 w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex h-36 w-full items-center justify-center bg-[#f1f0f5] text-xs text-[#6d6b77]"
                                        >
                                            No Preview
                                        </div>
                                        <div
                                            class="px-2 py-1 text-xs text-[#6d6b77]"
                                        >
                                            v{{ output.version_no }}
                                            <span
                                                v-if="output.is_active"
                                                class="font-semibold text-[#28c76f]"
                                            >
                                                • active
                                            </span>
                                        </div>
                                    </a>
                                    <div
                                        class="px-2 pb-2 text-xs text-[#8a8794]"
                                    >
                                        Frame asset
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
