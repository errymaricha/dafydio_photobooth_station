<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

import {
    index as listTemplates,
    store as storeTemplate,
} from '@/actions/App/Http/Controllers/Api/Editor/TemplateController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { useApi } from '@/composables/useApi';
import * as sessionRoutes from '@/routes/sessions';
import * as templatesRoutes from '@/routes/templates';

type TemplateSlot = {
    slot_index: number;
    x?: number | null;
    y?: number | null;
    width: number;
    height: number;
};

type TemplateItem = {
    id: string;
    template_code?: string | null;
    template_name: string;
    category?: string | null;
    paper_size?: string | null;
    canvas_width?: number | null;
    canvas_height?: number | null;
    preview_url?: string | null;
    status?: string | null;
    updated_at?: string | null;
    updated_by?: { id: string; name: string } | null;
    slots: TemplateSlot[];
};

const TEMPLATE_PREFERENCE_KEY = 'photobooth.preferred_template_id';

const { get, post } = useApi();

const templates = ref<TemplateItem[]>([]);
const loading = ref(true);
const creatingTemplate = ref(false);
const showCreateForm = ref(false);
const createFeedback = ref<string | null>(null);
const createError = ref<string | null>(null);
const createForm = ref({
    template_name: '',
    template_code: '',
    category: '',
    paper_size: '',
    canvas_width: 1200,
    canvas_height: 1800,
});
const search = ref('');
const selectedCategory = ref('all');
const selectedPaperSize = ref('all');
const selectedSlotCount = ref('all');
const selectedStatus = ref('active');
const feedback = ref<string | null>(null);

const categoryOptions = computed(() => {
    const options = [
        ...new Set(
            templates.value
                .map((template) => template.category)
                .filter((value): value is string => !!value),
        ),
    ].sort((left, right) => left.localeCompare(right));

    return ['all', ...options];
});

const paperSizeOptions = computed(() => {
    const options = [
        ...new Set(
            templates.value
                .map((template) => template.paper_size)
                .filter((value): value is string => !!value),
        ),
    ].sort((left, right) => left.localeCompare(right));

    return ['all', ...options];
});

const slotCountOptions = computed(() => {
    const options = [
        ...new Set(templates.value.map((template) => template.slots.length)),
    ].sort((left, right) => left - right);

    return ['all', ...options.map((value) => value.toString())];
});

const statusOptions = [
    { value: 'active', label: 'Hanya Aktif' },
    { value: 'archived', label: 'Hanya Arsip' },
    { value: 'all', label: 'Semua Status' },
];

const filteredTemplates = computed(() => {
    return templates.value.filter((template) => {
        const haystack =
            `${template.template_name} ${template.template_code ?? ''} ${template.category ?? ''} ${template.paper_size ?? ''}`.toLowerCase();

        const matchesSearch =
            !search.value || haystack.includes(search.value.toLowerCase());
        const matchesCategory =
            selectedCategory.value === 'all' ||
            template.category === selectedCategory.value;
        const matchesPaperSize =
            selectedPaperSize.value === 'all' ||
            template.paper_size === selectedPaperSize.value;
        const matchesSlotCount =
            selectedSlotCount.value === 'all' ||
            template.slots.length === Number(selectedSlotCount.value);
        const matchesStatus =
            selectedStatus.value === 'all' ||
            (template.status ?? 'active') === selectedStatus.value;

        return (
            matchesSearch &&
            matchesCategory &&
            matchesPaperSize &&
            matchesSlotCount &&
            matchesStatus
        );
    });
});

function getSlotPreviewStyle(
    template: TemplateItem,
    slot: TemplateSlot,
): Record<string, string> {
    const canvasWidth = Math.max(template.canvas_width ?? 1, 1);
    const canvasHeight = Math.max(template.canvas_height ?? 1, 1);

    return {
        left: `${((slot.x ?? 0) / canvasWidth) * 100}%`,
        top: `${((slot.y ?? 0) / canvasHeight) * 100}%`,
        width: `${(slot.width / canvasWidth) * 100}%`,
        height: `${(slot.height / canvasHeight) * 100}%`,
    };
}

function useTemplate(template: TemplateItem): void {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(TEMPLATE_PREFERENCE_KEY, template.id);
    }

    feedback.value = `${template.template_name} siap digunakan. Lanjutkan pilih session.`;

    router.visit(
        sessionRoutes.index.url({
            query: {
                template_id: template.id,
            },
        }),
    );
}

async function createTemplate(): Promise<void> {
    createFeedback.value = null;
    createError.value = null;
    creatingTemplate.value = true;

    try {
        const payload = {
            template_name: createForm.value.template_name,
            template_code: createForm.value.template_code || undefined,
            category: createForm.value.category || undefined,
            paper_size: createForm.value.paper_size || undefined,
            canvas_width: Number(createForm.value.canvas_width),
            canvas_height: Number(createForm.value.canvas_height),
        };

        const created = await post<TemplateItem>(storeTemplate(), payload);
        templates.value = [created, ...templates.value];
        showCreateForm.value = false;
        createForm.value = {
            template_name: '',
            template_code: '',
            category: '',
            paper_size: '',
            canvas_width: 1200,
            canvas_height: 1800,
        };
        createFeedback.value = 'Template baru berhasil dibuat.';

        router.visit(templatesRoutes.show.url(created.id));
    } catch (error: unknown) {
        const response = (error as { response?: { data?: { message?: string } } })
            ?.response?.data;
        createError.value = response?.message ?? 'Gagal membuat template.';
    } finally {
        creatingTemplate.value = false;
    }
}

onMounted(async () => {
    try {
        templates.value = await get<TemplateItem[]>(listTemplates(), {
            status: selectedStatus.value,
        });
    } finally {
        loading.value = false;
    }
});

watch(selectedStatus, async () => {
    loading.value = true;
    try {
        templates.value = await get<TemplateItem[]>(listTemplates(), {
            status: selectedStatus.value,
        });
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout
        title="Templates"
        subtitle="Lihat template aktif yang bisa dipakai editor untuk menyusun hasil photobooth."
    >
        <div
            v-if="feedback"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ feedback }}
        </div>

        <div class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-[#2f2b3dcc]">
                        Template Library
                    </h2>
                    <p class="text-sm text-[#6d6b77]">
                        Koleksi layout aktif berdasarkan ukuran canvas dan jumlah slot foto.
                    </p>
                </div>

                <div class="flex w-full flex-col gap-2 md:max-w-sm">
                    <input
                        v-model="search"
                        type="text"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                        placeholder="Cari template, kode, kategori, atau paper size"
                    />
                    <button
                        type="button"
                        class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                        @click="showCreateForm = !showCreateForm"
                    >
                        {{ showCreateForm ? 'Tutup Form Template' : 'Tambah Template' }}
                    </button>
                </div>
            </div>

            <div
                v-if="createFeedback"
                class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ createFeedback }}
            </div>

            <div
                v-if="createError"
                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ createError }}
            </div>

            <div
                v-if="showCreateForm"
                class="mb-4 grid gap-3 rounded-xl border border-[#e8e6ef] bg-[#f5f5f9] p-4 md:grid-cols-2"
            >
                <label class="space-y-1 text-xs text-[#6d6b77]">
                    <span>Nama Template</span>
                    <input
                        v-model="createForm.template_name"
                        type="text"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                        placeholder="Template Photostrip 2 Slot"
                    />
                </label>
                <label class="space-y-1 text-xs text-[#6d6b77]">
                    <span>Kode Template (opsional)</span>
                    <input
                        v-model="createForm.template_code"
                        type="text"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                        placeholder="TPL-2SLOT"
                    />
                </label>
                <label class="space-y-1 text-xs text-[#6d6b77]">
                    <span>Category</span>
                    <input
                        v-model="createForm.category"
                        type="text"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                        placeholder="photostrip"
                    />
                </label>
                <label class="space-y-1 text-xs text-[#6d6b77]">
                    <span>Paper Size</span>
                    <input
                        v-model="createForm.paper_size"
                        type="text"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                        placeholder="4R"
                    />
                </label>
                <label class="space-y-1 text-xs text-[#6d6b77]">
                    <span>Canvas Width</span>
                    <input
                        v-model.number="createForm.canvas_width"
                        type="number"
                        min="1"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                    />
                </label>
                <label class="space-y-1 text-xs text-[#6d6b77]">
                    <span>Canvas Height</span>
                    <input
                        v-model.number="createForm.canvas_height"
                        type="number"
                        min="1"
                        class="w-full rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                    />
                </label>
                <div class="md:col-span-2">
                    <button
                        type="button"
                        class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="creatingTemplate || !createForm.template_name"
                        @click="createTemplate"
                    >
                        {{ creatingTemplate ? 'Membuat Template...' : 'Simpan Template Baru' }}
                    </button>
                </div>
            </div>

            <div class="mb-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                <select
                    v-model="selectedCategory"
                    class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                >
                    <option
                        v-for="option in categoryOptions"
                        :key="option"
                        :value="option"
                    >
                        {{
                            option === 'all'
                                ? 'Semua Category'
                                : option
                        }}
                    </option>
                </select>

                <select
                    v-model="selectedPaperSize"
                    class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                >
                    <option
                        v-for="option in paperSizeOptions"
                        :key="option"
                        :value="option"
                    >
                        {{
                            option === 'all'
                                ? 'Semua Paper Size'
                                : option
                        }}
                    </option>
                </select>

                <select
                    v-model="selectedSlotCount"
                    class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                >
                    <option
                        v-for="option in slotCountOptions"
                        :key="option"
                        :value="option"
                    >
                        {{
                            option === 'all'
                                ? 'Semua Jumlah Slot'
                                : `${option} Slot`
                        }}
                    </option>
                </select>

                <select
                    v-model="selectedStatus"
                    class="rounded-lg border border-[#d8d4e7] px-3 py-2 text-sm"
                >
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div v-if="loading" class="text-sm text-[#6d6b77]">
                Loading templates...
            </div>

            <div v-else-if="!filteredTemplates.length">
                <EmptyState
                    title="Template belum tersedia"
                    message="Template aktif akan tampil di sini begitu data backend siap."
                />
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="template in filteredTemplates"
                    :key="template.id"
                    class="group rounded-lg border border-[#e8e6ef] bg-white p-4 shadow-[0_2px_10px_rgba(47,43,61,0.06)] transition hover:-translate-y-0.5 hover:border-[#9f96f5] hover:shadow-md"
                >
                    <div
                        class="relative flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg bg-[#f1f0f5]"
                    >
                        <img
                            v-if="template.preview_url"
                            :src="template.preview_url"
                            :alt="template.template_name"
                            class="absolute inset-0 h-full w-full object-cover opacity-25"
                        />

                        <div
                            v-if="!template.slots.length"
                            class="px-4 text-center text-sm text-[#b3b1bb]"
                        >
                            Preview belum tersedia
                        </div>

                        <div
                            v-else
                            class="relative h-full w-full"
                        >
                            <div
                                v-for="slot in template.slots"
                                :key="slot.slot_index"
                                class="absolute flex items-center justify-center rounded-lg border border-[#9f96f5] bg-[#edeafd] text-[11px] font-semibold text-[#685dd8]"
                                :style="getSlotPreviewStyle(template, slot)"
                            >
                                {{ slot.slot_index }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-[#2f2b3dcc] group-hover:text-[#685dd8]">
                                    {{ template.template_name }}
                                </h3>
                                <p class="text-xs text-[#6d6b77]">
                                    {{ template.template_code ?? 'Tanpa kode' }}
                                </p>
                            </div>

                            <div class="flex flex-col items-end gap-2 text-right">
                                <div class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77]">
                                    {{ template.paper_size ?? '-' }}
                                </div>
                                <div
                                    class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                    :class="
                                        (template.status ?? 'active') === 'archived'
                                            ? 'bg-[#fff1e3] text-[#ff9f43]'
                                            : 'bg-[#e8f7ef] text-[#28c76f]'
                                    "
                                >
                                    {{ (template.status ?? 'active') === 'archived' ? 'Archived' : 'Active' }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 text-sm">
                            <div class="rounded-lg bg-[#f5f5f9] p-3">
                                <div class="text-xs text-[#b3b1bb]">Category</div>
                                <div class="mt-1 font-medium text-[#2f2b3dcc]">
                                    {{ template.category ?? '-' }}
                                </div>
                            </div>

                            <div class="rounded-lg bg-[#f5f5f9] p-3">
                                <div class="text-xs text-[#b3b1bb]">Canvas</div>
                                <div class="mt-1 font-medium text-[#2f2b3dcc]">
                                    {{ template.canvas_width ?? 0 }}x{{ template.canvas_height ?? 0 }}
                                </div>
                            </div>

                            <div class="rounded-lg bg-[#f5f5f9] p-3">
                                <div class="text-xs text-[#b3b1bb]">Slots</div>
                                <div class="mt-1 font-medium text-[#2f2b3dcc]">
                                    {{ template.slots.length }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <Link
                                :href="templatesRoutes.show.url(template.id)"
                                class="inline-flex rounded-lg border border-[#d8d4e7] px-3 py-2 text-xs font-semibold text-[#2f2b3dcc] hover:bg-[#f1f0f5]"
                            >
                                Detail
                            </Link>
                            <button
                                type="button"
                                class="inline-flex rounded-lg bg-[#7367f0] px-3 py-2 text-xs font-semibold text-white hover:bg-[#685dd8]"
                                @click="useTemplate(template)"
                            >
                                Gunakan Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


