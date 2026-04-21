<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import {
    expenses as financeExpenses,
    storeExpense as financeStoreExpense,
} from '@/actions/App/Http/Controllers/Api/Editor/FinanceController';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useApi } from '@/composables/useApi';
import * as financeRoutes from '@/routes/finance';

type StationOption = {
    id: string;
    station_code: string;
    station_name: string;
};

type ExpenseRow = {
    id: string;
    expense_no: string;
    station_id: string | null;
    station_code: string | null;
    station_name: string | null;
    category_code: string;
    category_name: string;
    vendor_name: string | null;
    description: string | null;
    amount_subtotal: number;
    amount_tax: number;
    amount_total: number;
    currency_code: string;
    incurred_at: string;
    paid_at: string | null;
    payment_method: string | null;
    payment_ref: string | null;
    status: string;
    created_at: string;
};

type ExpenseResponse = {
    filters: {
        date_from: string;
        date_to: string;
        station_id: string | null;
        category_code: string | null;
        status: string | null;
        per_page: number;
    };
    options: {
        categories: Record<string, string>;
        payment_methods: Record<string, string>;
        stations: StationOption[];
    };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    rows: ExpenseRow[];
};

const { get, post } = useApi();

const today = new Date();
const defaultFrom = new Date(today);
defaultFrom.setDate(today.getDate() - 29);

const loading = ref(true);
const fetching = ref(false);
const submitting = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const dateFrom = ref(defaultFrom.toISOString().slice(0, 10));
const dateTo = ref(today.toISOString().slice(0, 10));
const stationFilter = ref('');
const categoryFilter = ref('');
const statusFilter = ref('');
const perPage = ref(20);
const currentPage = ref(1);

const rows = ref<ExpenseRow[]>([]);
const pagination = ref<ExpenseResponse['pagination']>({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});
const stations = ref<StationOption[]>([]);
const categories = ref<Record<string, string>>({});
const paymentMethods = ref<Record<string, string>>({});

const form = ref({
    incurred_at: today.toISOString().slice(0, 10),
    station_id: '',
    category_code: 'consumables_paper_ink',
    vendor_name: '',
    description: '',
    amount_subtotal: '0',
    amount_tax: '0',
    payment_method: 'cash',
    payment_ref: '',
});

const categoryOptions = computed(() =>
    Object.entries(categories.value).map(([code, name]) => ({ code, name })),
);

const paymentMethodOptions = computed(() =>
    Object.entries(paymentMethods.value).map(([code, name]) => ({ code, name })),
);

const formatApiError = (error: unknown, fallback: string): string => {
    const response = (error as {
        response?: {
            data?: {
                message?: string;
                errors?: Record<string, string[]>;
            };
        };
    })?.response?.data;

    const firstValidationError = response?.errors
        ? Object.values(response.errors).flat()[0]
        : null;

    return firstValidationError || response?.message || fallback;
};

const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 2,
    }).format(value ?? 0);
};

const formatDate = (value: string): string => {
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const loadExpenses = async (page = 1): Promise<void> => {
    fetching.value = true;
    errorMessage.value = null;

    try {
        const response = await get<ExpenseResponse>(
            financeExpenses({
                query: {
                    date_from: dateFrom.value,
                    date_to: dateTo.value,
                    station_id: stationFilter.value || undefined,
                    category_code: categoryFilter.value || undefined,
                    status: statusFilter.value || undefined,
                    per_page: perPage.value,
                    page,
                },
            }),
        );

        rows.value = response.rows ?? [];
        pagination.value = response.pagination;
        currentPage.value = response.pagination.current_page;
        stations.value = response.options.stations ?? [];
        categories.value = response.options.categories ?? {};
        paymentMethods.value = response.options.payment_methods ?? {};
    } catch (error: unknown) {
        errorMessage.value = formatApiError(error, 'Gagal memuat daftar expense.');
    } finally {
        loading.value = false;
        fetching.value = false;
    }
};

const submitExpense = async (): Promise<void> => {
    submitting.value = true;
    errorMessage.value = null;
    successMessage.value = null;

    try {
        await post(
            financeStoreExpense(),
            {
                incurred_at: form.value.incurred_at,
                station_id: form.value.station_id || null,
                category_code: form.value.category_code,
                vendor_name: form.value.vendor_name || null,
                description: form.value.description || null,
                amount_subtotal: Number(form.value.amount_subtotal),
                amount_tax: Number(form.value.amount_tax),
                payment_method: form.value.payment_method,
                payment_ref: form.value.payment_ref || null,
            },
        );

        successMessage.value = 'Expense berhasil disimpan.';
        form.value.vendor_name = '';
        form.value.description = '';
        form.value.amount_subtotal = '0';
        form.value.amount_tax = '0';
        form.value.payment_ref = '';

        await loadExpenses(1);
    } catch (error: unknown) {
        errorMessage.value = formatApiError(error, 'Gagal menyimpan expense.');
    } finally {
        submitting.value = false;
    }
};

const nextPage = async (): Promise<void> => {
    if (currentPage.value >= pagination.value.last_page || fetching.value) {
        return;
    }

    await loadExpenses(currentPage.value + 1);
};

const prevPage = async (): Promise<void> => {
    if (currentPage.value <= 1 || fetching.value) {
        return;
    }

    await loadExpenses(currentPage.value - 1);
};

onMounted(async () => {
    await loadExpenses();
});
</script>

<template>
    <AppLayout
        title="Finance Expenses"
        subtitle="Input biaya operasional manual dan auto-posting ke jurnal."
    >
        <div class="space-y-6">
            <div class="flex flex-wrap justify-end gap-2">
                <Link
                    :href="financeRoutes.index.url()"
                    class="rounded-lg border border-[#d8d4e7] bg-white px-4 py-2 text-sm font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9]"
                >
                    Rekap Finance
                </Link>
                <Link
                    :href="financeRoutes.transactions.url()"
                    class="rounded-lg border border-[#d8d4e7] bg-white px-4 py-2 text-sm font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9]"
                >
                    Detail Transaksi
                </Link>
            </div>

            <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <h3 class="text-base font-semibold text-[#2f2b3dcc]">Input Expense Manual</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-4">
                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[#2f2b3dcc]">Tanggal</span>
                        <input
                            v-model="form.incurred_at"
                            type="date"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                        />
                    </label>

                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[#2f2b3dcc]">Station</span>
                        <select
                            v-model="form.station_id"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                        >
                            <option value="">All / Umum</option>
                            <option v-for="station in stations" :key="station.id" :value="station.id">
                                {{ station.station_code }} - {{ station.station_name }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[#2f2b3dcc]">Kategori</span>
                        <select
                            v-model="form.category_code"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                        >
                            <option v-for="category in categoryOptions" :key="category.code" :value="category.code">
                                {{ category.name }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[#2f2b3dcc]">Metode Bayar</span>
                        <select
                            v-model="form.payment_method"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                        >
                            <option v-for="method in paymentMethodOptions" :key="method.code" :value="method.code">
                                {{ method.name }}
                            </option>
                        </select>
                    </label>

                    <label class="space-y-1 md:col-span-2">
                        <span class="text-xs font-medium text-[#2f2b3dcc]">Vendor</span>
                        <input
                            v-model="form.vendor_name"
                            type="text"
                            placeholder="Contoh: Toko Kertas Abadi"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                        />
                    </label>

                    <label class="space-y-1 md:col-span-2">
                        <span class="text-xs font-medium text-[#2f2b3dcc]">Keterangan</span>
                        <input
                            v-model="form.description"
                            type="text"
                            placeholder="Contoh: pembelian kertas 4R"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                        />
                    </label>

                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[#2f2b3dcc]">Subtotal</span>
                        <input
                            v-model="form.amount_subtotal"
                            type="number"
                            min="0"
                            step="100"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                        />
                    </label>

                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[#2f2b3dcc]">Pajak</span>
                        <input
                            v-model="form.amount_tax"
                            type="number"
                            min="0"
                            step="100"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                        />
                    </label>

                    <label class="space-y-1">
                        <span class="text-xs font-medium text-[#2f2b3dcc]">Ref Pembayaran</span>
                        <input
                            v-model="form.payment_ref"
                            type="text"
                            placeholder="opsional"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                        />
                    </label>

                    <div class="flex items-end">
                        <button
                            type="button"
                            class="w-full rounded-lg bg-[#7367f0] px-4 py-2 text-sm font-semibold text-white hover:bg-[#685dd8] disabled:opacity-50"
                            :disabled="submitting"
                            @click="submitExpense"
                        >
                            {{ submitting ? 'Menyimpan...' : 'Simpan Expense' }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="errorMessage"
                class="rounded-lg border border-[#ffd5d9] bg-[#fff5f5] px-3 py-2 text-sm text-[#ea5455]"
            >
                {{ errorMessage }}
            </div>

            <div
                v-if="successMessage"
                class="rounded-lg border border-[#c8f1da] bg-[#f0fcf5] px-3 py-2 text-sm text-[#28c76f]"
            >
                {{ successMessage }}
            </div>

            <div
                class="grid gap-3 rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)] md:grid-cols-5"
            >
                <label class="space-y-1">
                    <span class="text-xs font-medium text-[#2f2b3dcc]">Date From</span>
                    <input
                        v-model="dateFrom"
                        type="date"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                    />
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-[#2f2b3dcc]">Date To</span>
                    <input
                        v-model="dateTo"
                        type="date"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                    />
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-[#2f2b3dcc]">Station</span>
                    <select
                        v-model="stationFilter"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                    >
                        <option value="">All</option>
                        <option v-for="station in stations" :key="station.id" :value="station.id">
                            {{ station.station_code }} - {{ station.station_name }}
                        </option>
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-[#2f2b3dcc]">Kategori</span>
                    <select
                        v-model="categoryFilter"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm text-[#2f2b3dcc]"
                    >
                        <option value="">All</option>
                        <option v-for="category in categoryOptions" :key="category.code" :value="category.code">
                            {{ category.name }}
                        </option>
                    </select>
                </label>

                <div class="flex items-end">
                    <button
                        type="button"
                        class="w-full rounded-lg bg-[#7367f0] px-4 py-2 text-sm font-semibold text-white hover:bg-[#685dd8] disabled:opacity-50"
                        :disabled="fetching"
                        @click="loadExpenses(1)"
                    >
                        {{ fetching ? 'Loading...' : 'Filter' }}
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-[#2f2b3dcc]">Daftar Expense</h3>
                    <p class="text-xs text-[#6d6b77]">Total: {{ pagination.total }} data</p>
                </div>

                <div v-if="loading" class="text-sm text-[#6d6b77]">Loading expense...</div>
                <div v-else-if="!rows.length" class="text-sm text-[#6d6b77]">
                    Belum ada data expense.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-[#e8e6ef] text-[#6d6b77]">
                            <tr>
                                <th class="px-3 py-2">Expense No</th>
                                <th class="px-3 py-2">Tanggal</th>
                                <th class="px-3 py-2">Kategori</th>
                                <th class="px-3 py-2">Station</th>
                                <th class="px-3 py-2">Vendor</th>
                                <th class="px-3 py-2">Total</th>
                                <th class="px-3 py-2">Payment</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.id" class="border-b border-[#f1f0f5]">
                                <td class="px-3 py-2 font-medium text-[#2f2b3dcc]">{{ row.expense_no }}</td>
                                <td class="px-3 py-2">{{ formatDate(row.incurred_at) }}</td>
                                <td class="px-3 py-2">{{ row.category_name }}</td>
                                <td class="px-3 py-2">{{ row.station_code }} - {{ row.station_name }}</td>
                                <td class="px-3 py-2">{{ row.vendor_name || '-' }}</td>
                                <td class="px-3 py-2 text-[#ea5455]">
                                    {{ formatCurrency(row.amount_total) }}
                                </td>
                                <td class="px-3 py-2">{{ row.payment_method || '-' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full bg-[#e8f7ef] px-2 py-1 text-xs font-semibold text-[#28c76f]">
                                        {{ row.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <p class="text-xs text-[#6d6b77]">
                        Page {{ pagination.current_page }} / {{ pagination.last_page }}
                    </p>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-[#d8d4e7] px-3 py-1.5 text-xs font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9] disabled:opacity-50"
                            :disabled="pagination.current_page <= 1 || fetching"
                            @click="prevPage"
                        >
                            Prev
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-[#d8d4e7] px-3 py-1.5 text-xs font-semibold text-[#2f2b3dcc] hover:bg-[#f5f5f9] disabled:opacity-50"
                            :disabled="pagination.current_page >= pagination.last_page || fetching"
                            @click="nextPage"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
