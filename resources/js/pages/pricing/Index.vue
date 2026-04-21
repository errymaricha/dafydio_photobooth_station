<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import {
    index as pricingIndex,
    update as pricingUpdate,
} from '@/actions/App/Http/Controllers/Api/Editor/PricingController';
import AppLayout from '@/components/layout/AppLayout.vue';
import { useApi } from '@/composables/useApi';

type PricingPayload = {
    station: {
        id: string;
        station_code: string;
        station_name: string;
    };
    pricing: {
        photobooth_price: number;
        additional_print_price: number;
        currency_code: string;
    };
    updated_at: string | null;
};

const { get, patch } = useApi();

const loading = ref(true);
const saving = ref(false);
const feedback = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const stationName = ref('-');
const stationCode = ref('-');
const lastUpdatedAt = ref<string | null>(null);
const photoboothPrice = ref('');
const additionalPrintPrice = ref('');
const additionalPrintCount = ref('0');
const currencyCode = ref('IDR');

const toNumberOrZero = (value: string): number => {
    const parsed = Number(value);

    if (Number.isNaN(parsed) || parsed < 0) {
        return 0;
    }

    return parsed;
};

const toIntegerOrZero = (value: string): number => {
    const parsed = Math.floor(Number(value));

    if (Number.isNaN(parsed) || parsed < 0) {
        return 0;
    }

    return parsed;
};

const basePriceAmount = computed(() => toNumberOrZero(photoboothPrice.value));
const additionalPrintPriceAmount = computed(() =>
    toNumberOrZero(additionalPrintPrice.value),
);
const additionalPrintCountAmount = computed(() =>
    toIntegerOrZero(additionalPrintCount.value),
);
const additionalPrintSubtotal = computed(
    () => additionalPrintPriceAmount.value * additionalPrintCountAmount.value,
);
const finalTotalAmount = computed(
    () => basePriceAmount.value + additionalPrintSubtotal.value,
);

const formatCurrency = (value: number): string => {
    const normalizedCode = (currencyCode.value.trim() || 'IDR').toUpperCase();
    const supportedCode = /^[A-Z]{3}$/.test(normalizedCode)
        ? normalizedCode
        : 'IDR';

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: supportedCode,
        maximumFractionDigits: 2,
    }).format(value);
};

const loadPricing = async (): Promise<void> => {
    loading.value = true;
    errorMessage.value = null;

    try {
        const response = await get<PricingPayload>(pricingIndex());

        stationName.value = response.station.station_name;
        stationCode.value = response.station.station_code;
        lastUpdatedAt.value = response.updated_at;
        photoboothPrice.value = String(response.pricing.photobooth_price ?? 0);
        additionalPrintPrice.value = String(
            response.pricing.additional_print_price ?? 0,
        );
        currencyCode.value = response.pricing.currency_code ?? 'IDR';
    } catch (error: unknown) {
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Gagal memuat master harga.';
    } finally {
        loading.value = false;
    }
};

const savePricing = async (): Promise<void> => {
    saving.value = true;
    feedback.value = null;
    errorMessage.value = null;

    try {
        const response = await patch<PricingPayload & { message: string }>(
            pricingUpdate(),
            {
                photobooth_price: Number(photoboothPrice.value),
                additional_print_price: Number(additionalPrintPrice.value),
                currency_code: currencyCode.value.trim().toUpperCase(),
            },
        );

        lastUpdatedAt.value = response.updated_at;
        currencyCode.value = response.pricing.currency_code;
        feedback.value = response.message;
    } catch (error: unknown) {
        errorMessage.value =
            (error as { response?: { data?: { message?: string } } })?.response?.data
                ?.message ?? 'Gagal menyimpan master harga.';
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    await loadPricing();
});
</script>

<template>
    <AppLayout title="Pricing" subtitle="Master harga photobooth untuk sinkronisasi Android.">
        <div class="mx-auto max-w-3xl space-y-4">
            <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-[#2f2b3dcc]">
                            Station Pricing
                        </h2>
                        <p class="text-sm text-[#6d6b77]">
                            Station: {{ stationName }} ({{ stationCode }})
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-xs font-semibold text-[#2f2b3dcc] hover:bg-[#f1f0f5] disabled:opacity-50"
                        :disabled="loading"
                        @click="loadPricing"
                    >
                        {{ loading ? 'Loading...' : 'Refresh' }}
                    </button>
                </div>

                <div
                    v-if="feedback"
                    class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"
                >
                    {{ feedback }}
                </div>
                <div
                    v-if="errorMessage"
                    class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ errorMessage }}
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <label class="space-y-1">
                        <span class="text-sm font-medium text-[#2f2b3dcc]">
                            Harga Photobooth
                        </span>
                        <input
                            v-model="photoboothPrice"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                            placeholder="35000"
                        />
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-[#2f2b3dcc]">
                            Harga Additional Print
                        </span>
                        <input
                            v-model="additionalPrintPrice"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                            placeholder="5000"
                        />
                    </label>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-[180px_1fr]">
                    <label class="space-y-1">
                        <span class="text-sm font-medium text-[#2f2b3dcc]">
                            Currency
                        </span>
                        <input
                            v-model="currencyCode"
                            type="text"
                            maxlength="10"
                            class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm uppercase"
                            placeholder="IDR"
                        />
                    </label>

                    <div class="flex items-end">
                        <button
                            type="button"
                            class="w-full rounded-lg bg-[#7367f0] px-3 py-2 text-sm font-semibold text-white hover:bg-[#685dd8] disabled:opacity-50 md:w-auto"
                            :disabled="saving || loading"
                            @click="savePricing"
                        >
                            {{ saving ? 'Saving...' : 'Simpan Master Harga' }}
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-[#e8e6ef] bg-[#f5f5f9] p-4">
                    <h3 class="text-sm font-semibold text-[#2f2b3dcc]">
                        Preview Final Amount
                    </h3>
                    <p class="mt-1 text-xs text-[#6d6b77]">
                        Simulasi cepat untuk operator sebelum Android request payment.
                    </p>

                    <div class="mt-3 grid gap-3 md:grid-cols-[220px_1fr]">
                        <label class="space-y-1">
                            <span class="text-sm font-medium text-[#2f2b3dcc]">
                                Additional Print Count
                            </span>
                            <input
                                v-model="additionalPrintCount"
                                type="number"
                                min="0"
                                step="1"
                                class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                                placeholder="0"
                            />
                        </label>

                        <div class="grid gap-1 rounded-lg border border-[#e8e6ef] bg-white p-3 text-sm text-[#2f2b3dcc]">
                            <div>
                                Base Photobooth: {{ formatCurrency(basePriceAmount) }}
                            </div>
                            <div>
                                Additional Print:
                                {{ additionalPrintCountAmount }} x
                                {{ formatCurrency(additionalPrintPriceAmount) }} =
                                {{ formatCurrency(additionalPrintSubtotal) }}
                            </div>
                            <div class="pt-1 text-base font-semibold text-[#2f2b3dcc]">
                                Total: {{ formatCurrency(finalTotalAmount) }}
                            </div>
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-xs text-[#6d6b77]">
                    Last update:
                    {{ lastUpdatedAt ? new Date(lastUpdatedAt).toLocaleString('id-ID') : '-' }}
                </p>
            </div>
        </div>
    </AppLayout>
</template>

