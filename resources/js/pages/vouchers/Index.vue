<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import {
    libraryDeactivate,
    libraryIndex,
    libraryQuote,
    libraryStore,
    libraryUpdate,
} from '@/actions/App/Http/Controllers/Api/Editor/VoucherController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';

type DiscountType = 'percent' | 'fixed' | '';
type VoucherType = 'promo' | 'skip' | 'override' | 'free';
type VoucherStatus = 'active' | 'inactive';

type LibraryVoucherItem = {
    id: string;
    voucher_code: string;
    voucher_type: VoucherType;
    status: VoucherStatus;
    valid_from?: string | null;
    valid_until?: string | null;
    max_usage?: number | null;
    used_count?: number | null;
    discount_type?: DiscountType | null;
    discount_value?: string | null;
    max_discount_amount?: string | null;
    min_purchase_amount?: string | null;
    notes?: string | null;
};

const { get, post, patch } = useApi();

const vouchers = ref<LibraryVoucherItem[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const submitting = ref(false);
const updating = ref(false);
const deactivatingId = ref<string | null>(null);
const feedback = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const search = ref('');
const filterStatus = ref<'all' | VoucherStatus>('all');

const voucherCode = ref('');
const voucherType = ref<VoucherType>('promo');
const validFrom = ref('');
const validUntil = ref('');
const maxUsage = ref('');
const discountType = ref<DiscountType>('');
const discountValue = ref('');
const maxDiscountAmount = ref('');
const minPurchaseAmount = ref('');
const voucherNotes = ref('');

const editingId = ref<string | null>(null);
const editVoucherCode = ref('');
const editVoucherType = ref<VoucherType>('promo');
const editStatus = ref<VoucherStatus>('active');
const editValidFrom = ref('');
const editValidUntil = ref('');
const editMaxUsage = ref('');
const editDiscountType = ref<DiscountType>('');
const editDiscountValue = ref('');
const editMaxDiscountAmount = ref('');
const editMinPurchaseAmount = ref('');
const editNotes = ref('');
const quoteSubtotal = ref('');
const quoteVoucherCode = ref('');
const quoteLoading = ref(false);
const quoteResult = ref<{
    subtotal_amount: number;
    discount_amount: number;
    total_due: number;
    payment_required: boolean;
    unlock_photo: boolean;
    discount_reason: string | null;
} | null>(null);
const currencyFormatter = new Intl.NumberFormat('id-ID');

const normalizeDate = (value: string): string | null => {
    const v = value.trim();
    return /^\d{2}-\d{2}-\d{4}$/.test(v) ? v : null;
};

const toNumberOrNull = (value: unknown): number | null => {
    if (value === null || value === undefined) {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isNaN(value) ? null : value;
    }

    const v = String(value).trim();

    if (v === '') {
        return null;
    }

    const parsed = Number(v);
    return Number.isNaN(parsed) ? null : parsed;
};

const filteredVouchers = computed(() => {
    return vouchers.value.filter((voucher) => {
        const matchesStatus =
            filterStatus.value === 'all' || voucher.status === filterStatus.value;
        const haystack =
            `${voucher.voucher_code} ${voucher.voucher_type} ${voucher.discount_type ?? ''}`.toLowerCase();
        const matchesSearch =
            !search.value || haystack.includes(search.value.toLowerCase());

        return matchesStatus && matchesSearch;
    });
});

const formatCurrency = (value: number | string | null | undefined): string => {
    const numeric = Number(value ?? 0);
    return `Rp ${currencyFormatter.format(Number.isNaN(numeric) ? 0 : numeric)}`;
};

const formatDiscountLabel = (voucher: LibraryVoucherItem): string => {
    if (!voucher.discount_type || !voucher.discount_value) {
        return '-';
    }

    if (voucher.discount_type === 'percent') {
        return `${voucher.discount_value}%`;
    }

    return formatCurrency(voucher.discount_value);
};

const parseDdMmYyyy = (value?: string | null): Date | null => {
    if (!value) {
        return null;
    }

    const parts = value.split('-');
    if (parts.length !== 3) {
        return null;
    }

    const [dayRaw, monthRaw, yearRaw] = parts;
    const day = Number(dayRaw);
    const month = Number(monthRaw);
    const year = Number(yearRaw);

    if (!day || !month || !year) {
        return null;
    }

    const date = new Date(year, month - 1, day);
    return Number.isNaN(date.getTime()) ? null : date;
};

const getVoucherHealth = (voucher: LibraryVoucherItem): {
    label: string;
    className: string;
} => {
    const now = new Date();
    const startDate = parseDdMmYyyy(voucher.valid_from);
    const endDate = parseDdMmYyyy(voucher.valid_until);
    const endDateInclusive = endDate
        ? new Date(
              endDate.getFullYear(),
              endDate.getMonth(),
              endDate.getDate(),
              23,
              59,
              59,
              999,
          )
        : null;

    if (voucher.status === 'inactive') {
        return {
            label: 'Inactive',
            className: 'border-[#d8d4e7] bg-[#f1f0f5] text-[#6d6b77]',
        };
    }

    if (
        voucher.max_usage !== null &&
        voucher.max_usage !== undefined &&
        (voucher.used_count ?? 0) >= voucher.max_usage
    ) {
        return {
            label: 'Usage Full',
            className: 'border-amber-300 bg-amber-50 text-amber-700',
        };
    }

    if (startDate && startDate > now) {
        return {
            label: 'Not Started',
            className: 'border-sky-300 bg-sky-50 text-sky-700',
        };
    }

    if (endDateInclusive && endDateInclusive < now) {
        return {
            label: 'Expired',
            className: 'border-rose-300 bg-rose-50 text-rose-700',
        };
    }

    return {
        label: 'Valid',
        className: 'border-emerald-300 bg-emerald-50 text-emerald-700',
    };
};

const copyVoucherCode = async (voucherCode: string): Promise<void> => {
    try {
        await navigator.clipboard.writeText(voucherCode);
        feedback.value = `Voucher code ${voucherCode} copied.`;
        errorMessage.value = null;
    } catch {
        errorMessage.value = 'Gagal copy voucher code. Coba copy manual.';
    }
};

const useVoucherInQuote = (voucherCode: string): void => {
    quoteVoucherCode.value = voucherCode;
};

const clearQuote = (): void => {
    quoteResult.value = null;
    quoteSubtotal.value = '';
    quoteVoucherCode.value = '';
};

const loadData = async (silent = false): Promise<void> => {
    if (silent) {
        refreshing.value = true;
    } else {
        loading.value = true;
    }

    try {
        const response = await get<{ data?: LibraryVoucherItem[] }>(
            libraryIndex({ query: { search: search.value, status: filterStatus.value } }),
        );
        vouchers.value = response.data ?? [];
    } catch (error: unknown) {
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Gagal memuat master voucher.';
    } finally {
        if (silent) {
            refreshing.value = false;
        } else {
            loading.value = false;
        }
    }
};

const createVoucher = async (): Promise<void> => {
    submitting.value = true;
    feedback.value = null;
    errorMessage.value = null;

    try {
        await post(libraryStore(), {
            voucher_code: voucherCode.value.trim(),
            voucher_type: voucherType.value,
            valid_from: normalizeDate(validFrom.value),
            valid_until: normalizeDate(validUntil.value),
            max_usage: toNumberOrNull(maxUsage.value),
            discount_type: discountType.value || null,
            discount_value: toNumberOrNull(discountValue.value),
            max_discount_amount: toNumberOrNull(maxDiscountAmount.value),
            min_purchase_amount: toNumberOrNull(minPurchaseAmount.value),
            notes: voucherNotes.value.trim() || null,
        });

        voucherCode.value = '';
        validFrom.value = '';
        validUntil.value = '';
        maxUsage.value = '';
        discountType.value = '';
        discountValue.value = '';
        maxDiscountAmount.value = '';
        minPurchaseAmount.value = '';
        voucherNotes.value = '';
        feedback.value = 'Master voucher berhasil dibuat.';
        await loadData(true);
    } catch (error: unknown) {
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Gagal membuat master voucher.';
    } finally {
        submitting.value = false;
    }
};

const startEdit = (voucher: LibraryVoucherItem): void => {
    editingId.value = voucher.id;
    editVoucherCode.value = voucher.voucher_code;
    editVoucherType.value = voucher.voucher_type;
    editStatus.value = voucher.status;
    editValidFrom.value = voucher.valid_from ?? '';
    editValidUntil.value = voucher.valid_until ?? '';
    editMaxUsage.value = voucher.max_usage ? String(voucher.max_usage) : '';
    editDiscountType.value = voucher.discount_type ?? '';
    editDiscountValue.value = voucher.discount_value ? String(voucher.discount_value) : '';
    editMaxDiscountAmount.value = voucher.max_discount_amount
        ? String(voucher.max_discount_amount)
        : '';
    editMinPurchaseAmount.value = voucher.min_purchase_amount
        ? String(voucher.min_purchase_amount)
        : '';
    editNotes.value = voucher.notes ?? '';
};

const cancelEdit = (): void => {
    editingId.value = null;
};

const updateVoucherAction = async (): Promise<void> => {
    if (!editingId.value) {
        return;
    }

    updating.value = true;
    feedback.value = null;
    errorMessage.value = null;

    try {
        await patch(libraryUpdate(editingId.value), {
            voucher_code: editVoucherCode.value.trim(),
            voucher_type: editVoucherType.value,
            status: editStatus.value,
            valid_from: normalizeDate(editValidFrom.value),
            valid_until: normalizeDate(editValidUntil.value),
            max_usage: toNumberOrNull(editMaxUsage.value),
            discount_type: editDiscountType.value || null,
            discount_value: toNumberOrNull(editDiscountValue.value),
            max_discount_amount: toNumberOrNull(editMaxDiscountAmount.value),
            min_purchase_amount: toNumberOrNull(editMinPurchaseAmount.value),
            notes: editNotes.value.trim() || null,
        });

        feedback.value = 'Voucher berhasil diperbarui.';
        editingId.value = null;
        await loadData(true);
    } catch (error: unknown) {
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Gagal memperbarui voucher.';
    } finally {
        updating.value = false;
    }
};

const deactivateVoucherAction = async (voucherId: string): Promise<void> => {
    deactivatingId.value = voucherId;
    feedback.value = null;
    errorMessage.value = null;

    try {
        await post(libraryDeactivate(voucherId), {});
        feedback.value = 'Voucher berhasil dinonaktifkan.';
        await loadData(true);
    } catch (error: unknown) {
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Gagal menonaktifkan voucher.';
    } finally {
        deactivatingId.value = null;
    }
};

const runQuote = async (): Promise<void> => {
    const subtotal = toNumberOrNull(quoteSubtotal.value);

    if (subtotal === null || subtotal < 0) {
        quoteResult.value = null;
        errorMessage.value = 'Subtotal harus berupa angka 0 atau lebih.';
        return;
    }

    quoteLoading.value = true;
    feedback.value = null;
    errorMessage.value = null;

    try {
        const response = await post<{
            quote: {
                subtotal_amount: number;
                discount_amount: number;
                total_due: number;
                payment_required: boolean;
                unlock_photo: boolean;
                discount_reason: string | null;
            };
        }>(libraryQuote(), {
            subtotal_amount: subtotal,
            voucher_code: quoteVoucherCode.value.trim() || null,
        });

        quoteResult.value = response.quote;
    } catch (error: unknown) {
        quoteResult.value = null;
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Gagal menghitung quote voucher.';
    } finally {
        quoteLoading.value = false;
    }
};

onMounted(async () => {
    await loadData();
});
</script>

<template>
    <AppLayout title="Vouchers" subtitle="Master voucher before payment (DD-MM-YYYY).">
        <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
            <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <h2 class="text-lg font-semibold text-[#2f2b3dcc]">Create Master Voucher</h2>
                <p class="mt-1 text-sm text-[#6d6b77]">Tanggal pakai format DD-MM-YYYY.</p>

                <div v-if="feedback" class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">{{ feedback }}</div>
                <div v-if="errorMessage" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</div>

                <div class="mt-4 space-y-3">
                    <input v-model="voucherCode" type="text" class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Voucher code" />
                    <select v-model="voucherType" class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm">
                        <option value="promo">Promo</option>
                        <option value="skip">Skip</option>
                        <option value="override">Override</option>
                        <option value="free">Free</option>
                    </select>

                    <div class="grid gap-2 md:grid-cols-2">
                        <input v-model="validFrom" type="text" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Valid From (DD-MM-YYYY)" />
                        <input v-model="validUntil" type="text" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Valid Until (DD-MM-YYYY)" />
                    </div>

                    <input v-model="maxUsage" type="number" min="1" class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Max usage (optional)" />

                    <div class="grid gap-2 md:grid-cols-2">
                        <select v-model="discountType" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm">
                            <option value="">No Discount</option>
                            <option value="percent">Percent (%)</option>
                            <option value="fixed">Fixed Amount</option>
                        </select>
                        <input v-model="discountValue" type="number" min="0" step="0.01" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Discount value" />
                    </div>

                    <div class="grid gap-2 md:grid-cols-2">
                        <input v-model="maxDiscountAmount" type="number" min="0" step="0.01" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Max discount amount" />
                        <input v-model="minPurchaseAmount" type="number" min="0" step="0.01" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Min purchase amount" />
                    </div>

                    <textarea v-model="voucherNotes" rows="2" class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Notes" />

                    <button type="button" class="w-full rounded-lg bg-[#7367f0] px-3 py-2 text-sm font-semibold text-white hover:bg-[#685dd8] disabled:opacity-50" :disabled="submitting" @click="createVoucher">
                        {{ submitting ? 'Creating...' : 'Create Voucher' }}
                    </button>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                    <h2 class="text-lg font-semibold text-[#2f2b3dcc]">Payment Quote Simulator</h2>
                    <p class="mt-1 text-sm text-[#6d6b77]">
                        Simulasi harga akhir sebelum payment Android dimulai.
                    </p>

                    <div class="mt-4 space-y-3">
                        <input
                            v-model="quoteSubtotal"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                            placeholder="Subtotal amount (contoh: 100000)"
                        />
                        <input
                            v-model="quoteVoucherCode"
                            type="text"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                            placeholder="Voucher code (opsional)"
                        />
                        <button
                            type="button"
                            class="w-full rounded-lg bg-[#7367f0] px-3 py-2 text-sm font-semibold text-white hover:bg-[#685dd8] disabled:opacity-50"
                            :disabled="quoteLoading"
                            @click="runQuote"
                        >
                            {{ quoteLoading ? 'Calculating...' : 'Run Quote' }}
                        </button>
                        <button
                            type="button"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm font-semibold text-[#2f2b3dcc] hover:bg-[#f1f0f5]"
                            @click="clearQuote"
                        >
                            Reset Quote
                        </button>
                    </div>

                    <div
                        v-if="quoteResult"
                        class="mt-4 grid gap-2 rounded-lg border border-[#e8e6ef] bg-[#f5f5f9] p-3 text-sm text-[#2f2b3dcc]"
                    >
                        <div>Subtotal: {{ formatCurrency(quoteResult.subtotal_amount) }}</div>
                        <div>Discount: {{ formatCurrency(quoteResult.discount_amount) }}</div>
                        <div>Total Due: {{ formatCurrency(quoteResult.total_due) }}</div>
                        <div>Payment Required: {{ quoteResult.payment_required ? 'Yes' : 'No' }}</div>
                        <div>Unlock Photo: {{ quoteResult.unlock_photo ? 'Yes' : 'No' }}</div>
                        <div>Reason: {{ quoteResult.discount_reason ?? '-' }}</div>
                    </div>
                </div>

                <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <div class="mb-4 flex gap-2">
                    <input v-model="search" type="text" class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Search code/type" />
                    <select v-model="filterStatus" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button type="button" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-xs font-semibold text-[#2f2b3dcc]" @click="loadData(true)">Refresh</button>
                </div>

                <div v-if="loading" class="text-sm text-[#6d6b77]">Loading vouchers...</div>
                <div v-else-if="!filteredVouchers.length">
                    <EmptyState title="Voucher belum ada" message="Master voucher akan muncul di sini." />
                </div>
                <div v-else class="flex flex-col gap-3">
                    <div v-for="voucher in filteredVouchers" :key="voucher.id" class="rounded-lg border border-[#e8e6ef] p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="text-xs text-[#6d6b77]">
                                <div class="text-sm font-semibold text-[#2f2b3dcc]">{{ voucher.voucher_code }}</div>
                                <div>Type: {{ voucher.voucher_type }}</div>
                                <div>Valid: {{ voucher.valid_from ?? '-' }} → {{ voucher.valid_until ?? '-' }}</div>
                                <div>Usage: {{ voucher.used_count ?? 0 }} / {{ voucher.max_usage ?? '∞' }}</div>
                                <div>Discount: {{ formatDiscountLabel(voucher) }}</div>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <StatusBadge :status="voucher.status" />
                                <span
                                    class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                    :class="getVoucherHealth(voucher).className"
                                >
                                    {{ getVoucherHealth(voucher).label }}
                                </span>
                                <button type="button" class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77]" @click="copyVoucherCode(voucher.voucher_code)">Copy Code</button>
                                <button type="button" class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77]" @click="useVoucherInQuote(voucher.voucher_code)">Simulate</button>
                                <button type="button" class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77]" @click="startEdit(voucher)">Edit</button>
                                <button v-if="voucher.status === 'active'" type="button" class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77]" :disabled="deactivatingId === voucher.id" @click="deactivateVoucherAction(voucher.id)">
                                    {{ deactivatingId === voucher.id ? 'Processing...' : 'Deactivate' }}
                                </button>
                            </div>
                        </div>

                        <div v-if="editingId === voucher.id" class="mt-3 space-y-2 rounded-lg border border-[#e8e6ef] bg-[#f5f5f9] p-3">
                            <div class="grid gap-2 md:grid-cols-2">
                                <input v-model="editVoucherCode" type="text" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" />
                                <select v-model="editVoucherType" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm">
                                    <option value="promo">Promo</option>
                                    <option value="skip">Skip</option>
                                    <option value="override">Override</option>
                                    <option value="free">Free</option>
                                </select>
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <input v-model="editValidFrom" type="text" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="DD-MM-YYYY" />
                                <input v-model="editValidUntil" type="text" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="DD-MM-YYYY" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <select v-model="editStatus" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <input v-model="editMaxUsage" type="number" min="1" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Max usage" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <select v-model="editDiscountType" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm">
                                    <option value="">No Discount</option>
                                    <option value="percent">Percent (%)</option>
                                    <option value="fixed">Fixed Amount</option>
                                </select>
                                <input v-model="editDiscountValue" type="number" min="0" step="0.01" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Discount value" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <input v-model="editMaxDiscountAmount" type="number" min="0" step="0.01" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Max discount amount" />
                                <input v-model="editMinPurchaseAmount" type="number" min="0" step="0.01" class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Min purchase amount" />
                            </div>
                            <textarea v-model="editNotes" rows="2" class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm" placeholder="Notes" />
                            <div class="flex justify-end gap-2">
                                <button type="button" class="rounded-lg border border-[#d8d4e7] px-3 py-1.5 text-xs font-semibold text-[#6d6b77]" @click="cancelEdit">Cancel</button>
                                <button type="button" class="rounded-lg bg-[#7367f0] px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50" :disabled="updating" @click="updateVoucherAction">{{ updating ? 'Saving...' : 'Save' }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </AppLayout>
</template>

