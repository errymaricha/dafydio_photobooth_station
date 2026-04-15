<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import {
    destroy as destroyTemplate,
    duplicate as duplicateTemplate,
    destroySlot as destroyTemplateSlot,
    show as showTemplate,
    uploadOverlay as uploadTemplateOverlay,
    qrPreview as qrPreviewTemplate,
    storeSlot as storeTemplateSlot,
    update as updateTemplate,
    updateSlots as updateTemplateSlots,
} from '@/actions/App/Http/Controllers/Api/Editor/TemplateController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatsCard from '@/components/ui/StatsCard.vue';
import { useApi } from '@/composables/useApi';
import * as sessionRoutes from '@/routes/sessions';
import * as templatesRoutes from '@/routes/templates';

type TemplateSlot = {
    slot_index: number;
    x?: number | null;
    y?: number | null;
    width: number;
    height: number;
    rotation?: number | null;
    border_radius?: number | null;
};

type EditableTemplateSlot = {
    slot_index: number;
    x: number;
    y: number;
    width: number;
    height: number;
    rotation: number;
    border_radius: number;
};

type TemplateDetail = {
    id: string;
    template_code?: string | null;
    template_name: string;
    category?: string | null;
    paper_size?: string | null;
    canvas_width?: number | null;
    canvas_height?: number | null;
    preview_url?: string | null;
    overlay_url?: string | null;
    config?: Record<string, unknown> | null;
    status?: string | null;
    updated_at?: string | null;
    created_by?: { id: string; name: string } | null;
    updated_by?: { id: string; name: string } | null;
    slots: TemplateSlot[];
};

type DynamicLayer = {
    id: string;
    type: 'text' | 'qr';
    label?: string;
    text?: string;
    qr_data?: string;
    x?: number;
    y?: number;
    width?: number;
    height?: number;
    font_size?: number;
    color?: string;
    align?: 'left' | 'center' | 'right';
    opacity?: number;
    enabled?: boolean;
    padding?: number;
    bg_color?: string;
};

const props = defineProps<{
    templateId: string;
}>();

const { get, post, patch, del } = useApi();

const TEMPLATE_PREFERENCE_KEY = 'photobooth.preferred_template_id';

const template = ref<TemplateDetail | null>(null);
const loading = ref(true);
const savingLayout = ref(false);
const savingSlot = ref(false);
const savingTemplate = ref(false);
const uploadingOverlay = ref(false);
const savingLayers = ref(false);
const selectedSlotIndex = ref<number | null>(null);
const activeTemplateId = ref<string | null>(null);
const slotDrafts = ref<EditableTemplateSlot[]>([]);
const layoutFeedback = ref<string | null>(null);
const layoutError = ref<string | null>(null);
const templateFeedback = ref<string | null>(null);
const templateError = ref<string | null>(null);
const isEditingName = ref(false);
const nameDraft = ref('');
const deleteConfirmName = ref('');
const deleteReason = ref('');
const showDeletePanel = ref(false);
const slotPhotoUrls = ref<Record<number, string>>({});
const customOverlayUrl = ref<string | null>(null);
const overlayOpacity = ref(70);
const overlayEnabled = ref(true);
const snapEnabled = ref(true);
const snapStep = ref(10);
const lockAspectRatio = ref(true);
const dynamicLayers = ref<DynamicLayer[]>([]);
const qrPreviewUrls = ref<Record<string, string>>({});
const qrPreviewKeys = ref<Record<string, string>>({});
const resizeDragState = ref<{
    slotIndex: number;
    pointerId: number;
    handle: 'nw' | 'ne' | 'se' | 'sw';
    startClientX: number;
    startClientY: number;
    startX: number;
    startY: number;
    startWidth: number;
    startHeight: number;
    canvasWidthPx: number;
    canvasHeightPx: number;
} | null>(null);
const layerDragState = ref<{
    layerId: string;
    pointerId: number;
    startClientX: number;
    startClientY: number;
    startX: number;
    startY: number;
    canvasWidthPx: number;
    canvasHeightPx: number;
} | null>(null);
const layoutDragState = ref<{
    slotIndex: number;
    pointerId: number;
    startClientX: number;
    startClientY: number;
    startX: number;
    startY: number;
    canvasWidthPx: number;
    canvasHeightPx: number;
} | null>(null);
const generatedObjectUrls = new Set<string>();

const sortedSlots = computed(() => {
    return [...slotDrafts.value].sort(
        (left, right) => left.slot_index - right.slot_index,
    );
});

const selectedSlot = computed(() => {
    return (
        sortedSlots.value.find(
            (slot) => slot.slot_index === selectedSlotIndex.value,
        ) ?? null
    );
});

const isActiveTemplate = computed(() => {
    if (!template.value || !activeTemplateId.value) {
        return false;
    }

    return template.value.id === activeTemplateId.value;
});

const overlayImageUrl = computed(() => {
    return (
        customOverlayUrl.value ??
        template.value?.overlay_url ??
        template.value?.preview_url ??
        null
    );
});

const dynamicLayerPreview = computed(() => {
    return dynamicLayers.value;
});

const templateStatusLabel = computed(() => {
    return template.value?.status === 'archived' ? 'Archived' : 'Active';
});

const updatedByLabel = computed(() => {
    if (!template.value?.updated_by?.name) {
        return null;
    }

    return template.value.updated_by.name;
});

const updatedAtLabel = computed(() => {
    return template.value?.updated_at ?? null;
});

const selectedSlotAspectRatio = computed(() => {
    if (!selectedSlot.value) {
        return null;
    }

    const height = Math.max(selectedSlot.value.height, 1);

    return selectedSlot.value.width / height;
});

const hasLayoutChanges = computed(() => {
    if (!template.value) {
        return false;
    }

    const normalizedOriginalSlots = template.value.slots
        .map((slot) => clampSlotDraft(normalizeSlotDraft(slot)))
        .sort((left, right) => left.slot_index - right.slot_index);

    return (
        JSON.stringify(normalizedOriginalSlots) !==
        JSON.stringify(sortedSlots.value)
    );
});

function getCanvasSize(): { width: number; height: number } {
    return {
        width: Math.max(template.value?.canvas_width ?? 1, 1),
        height: Math.max(template.value?.canvas_height ?? 1, 1),
    };
}

function normalizeSlotDraft(slot: TemplateSlot): EditableTemplateSlot {
    return {
        slot_index: slot.slot_index,
        x: Math.max(Math.round(slot.x ?? 0), 0),
        y: Math.max(Math.round(slot.y ?? 0), 0),
        width: Math.max(Math.round(slot.width), 1),
        height: Math.max(Math.round(slot.height), 1),
        rotation: Number((slot.rotation ?? 0).toString()),
        border_radius: Math.max(Math.round(slot.border_radius ?? 0), 0),
    };
}

function clampSlotDraft(slot: EditableTemplateSlot): EditableTemplateSlot {
    const canvas = getCanvasSize();
    const width = Math.max(1, Math.min(Math.round(slot.width), canvas.width));
    const height = Math.max(1, Math.min(Math.round(slot.height), canvas.height));

    return {
        ...slot,
        x: Math.max(0, Math.min(Math.round(slot.x), canvas.width - width)),
        y: Math.max(0, Math.min(Math.round(slot.y), canvas.height - height)),
        width,
        height,
        rotation: Number((slot.rotation ?? 0).toFixed(2)),
        border_radius: Math.max(0, Math.round(slot.border_radius ?? 0)),
    };
}

function syncSlotDrafts(): void {
    slotDrafts.value = (template.value?.slots ?? [])
        .map((slot) => clampSlotDraft(normalizeSlotDraft(slot)))
        .sort((left, right) => left.slot_index - right.slot_index);
}

function createLayerId(): string {
    return `layer_${Date.now()}_${Math.random().toString(16).slice(2, 8)}`;
}

function initializeDynamicLayers(): void {
    const config = template.value?.config ?? {};
    const layers = (config as { dynamic_layers?: DynamicLayer[] }).dynamic_layers;

              dynamicLayers.value = Array.isArray(layers)
        ? layers.map((layer) => ({
              id: layer.id ?? createLayerId(),
              type: layer.type,
              label: layer.label ?? '',
              text: layer.text ?? '',
              qr_data: layer.qr_data ?? '',
              x: layer.x ?? 40,
              y: layer.y ?? 40,
              width: layer.width ?? 160,
              height: layer.height ?? 160,
              font_size: layer.font_size ?? 36,
              color: layer.color ?? '#111827',
              align: layer.align ?? 'left',
              opacity: layer.opacity ?? 100,
              enabled: layer.enabled ?? true,
              padding: layer.padding ?? 0,
              bg_color: layer.bg_color ?? '#ffffff',
          }))
        : [];
}

function addTextLayer(): void {
    dynamicLayers.value = [
        ...dynamicLayers.value,
        {
            id: createLayerId(),
            type: 'text',
            label: 'Label',
            text: 'Event Name',
            x: 60,
            y: 60,
            font_size: 36,
            color: '#111827',
            align: 'left',
            opacity: 100,
            enabled: true,
            padding: 0,
            bg_color: '#ffffff',
        },
    ];
}

function addQrLayer(): void {
    dynamicLayers.value = [
        ...dynamicLayers.value,
        {
            id: createLayerId(),
            type: 'qr',
            label: 'QR',
            qr_data: 'https://photobooth.local/session',
            x: 60,
            y: 120,
            width: 160,
            height: 160,
            opacity: 100,
            enabled: true,
            padding: 8,
            bg_color: '#ffffff',
        },
    ];
}

function removeLayer(layerId: string): void {
    dynamicLayers.value = dynamicLayers.value.filter((layer) => layer.id !== layerId);
}

function insertLayerToken(layer: DynamicLayer, token: string): void {
    if (layer.type === 'text') {
        layer.text = `${layer.text ?? ''}${token}`;

        return;
    }

    layer.qr_data = `${layer.qr_data ?? ''}${token}`;
}

function getQrPreviewKey(layer: DynamicLayer): string {
    return [
        layer.qr_data ?? '',
        layer.width ?? '',
        layer.height ?? '',
        layer.padding ?? '',
        layer.bg_color ?? '',
    ].join('|');
}

async function refreshQrPreview(layer: DynamicLayer): Promise<void> {
    if (layer.type !== 'qr') {
        return;
    }

    if (!layer.qr_data) {
        qrPreviewUrls.value = {
            ...qrPreviewUrls.value,
            [layer.id]: '',
        };
        return;
    }

    const size = layer.width ?? layer.height ?? 160;

    try {
        const response = await get<{ data_url: string }>(qrPreviewTemplate(), {
            data: layer.qr_data,
            size,
            padding: layer.padding ?? 0,
            bg_color: layer.bg_color ?? '#ffffff',
        });

        qrPreviewUrls.value = {
            ...qrPreviewUrls.value,
            [layer.id]: response.data_url,
        };
    } catch (error: unknown) {
        qrPreviewUrls.value = {
            ...qrPreviewUrls.value,
            [layer.id]: '',
        };
    }
}

async function saveDynamicLayers(): Promise<void> {
    if (!template.value) {
        return;
    }

    templateFeedback.value = null;
    templateError.value = null;
    savingLayers.value = true;

    try {
        const nextConfig = {
            ...(template.value.config ?? {}),
            dynamic_layers: dynamicLayers.value,
        };

        const updated = await patch<TemplateDetail>(
            updateTemplate(template.value.id),
            {
                config_json: nextConfig,
            },
        );

        template.value = updated;
        initializeDynamicLayers();
        templateFeedback.value = 'Layer template berhasil disimpan.';
    } catch (error: unknown) {
        templateError.value = normalizeApiError(
            error,
            'Gagal menyimpan layer template.',
        );
    } finally {
        savingLayers.value = false;
    }
}

function applySnapValue(value: number): number {
    if (!snapEnabled.value) {
        return value;
    }

    const step = Math.max(1, snapStep.value);

    return Math.round(value / step) * step;
}

function updateSlotDraft(
    slotIndex: number,
    patch: Partial<Omit<EditableTemplateSlot, 'slot_index'>>,
    options: { respectAspect?: boolean } = {},
): void {
    layoutFeedback.value = null;
    layoutError.value = null;
    const shouldRespectAspect = options.respectAspect ?? true;

    slotDrafts.value = slotDrafts.value.map((slot) => {
        if (slot.slot_index !== slotIndex) {
            return slot;
        }

        const nextSlot: EditableTemplateSlot = {
            ...slot,
            ...patch,
        };
        const aspectRatio =
            shouldRespectAspect &&
            lockAspectRatio.value &&
            selectedSlotIndex.value === slotIndex
                ? selectedSlotAspectRatio.value
                : null;

        if (aspectRatio) {
            if (patch.width !== undefined && patch.height === undefined) {
                nextSlot.height = Math.round(nextSlot.width / aspectRatio);
            } else if (patch.height !== undefined && patch.width === undefined) {
                nextSlot.width = Math.round(nextSlot.height * aspectRatio);
            } else if (patch.width !== undefined && patch.height !== undefined) {
                nextSlot.height = Math.round(nextSlot.width / aspectRatio);
            }
        }

        return clampSlotDraft({
            ...nextSlot,
            x: applySnapValue(nextSlot.x),
            y: applySnapValue(nextSlot.y),
            width: applySnapValue(nextSlot.width),
            height: applySnapValue(nextSlot.height),
        });
    });
}

function clampSlotValue(raw: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, raw));
}

function nudgeSelectedSlot(axis: 'x' | 'y', delta: number): void {
    if (!selectedSlot.value) {
        return;
    }

    if (axis === 'x') {
        updateSlotDraft(selectedSlot.value.slot_index, {
            x: selectedSlot.value.x + delta,
        });

        return;
    }

    updateSlotDraft(selectedSlot.value.slot_index, {
        y: selectedSlot.value.y + delta,
    });
}

function resizeSelectedSlot(deltaWidth: number, deltaHeight: number): void {
    if (!selectedSlot.value) {
        return;
    }

    if (lockAspectRatio.value && selectedSlotAspectRatio.value) {
        updateSlotDraft(selectedSlot.value.slot_index, {
            width: selectedSlot.value.width + deltaWidth,
        });

        return;
    }

    updateSlotDraft(selectedSlot.value.slot_index, {
        width: selectedSlot.value.width + deltaWidth,
        height: selectedSlot.value.height + deltaHeight,
    });
}

function startResizeDrag(
    slot: EditableTemplateSlot,
    handle: 'nw' | 'ne' | 'se' | 'sw',
    event: PointerEvent,
): void {
    const target = event.currentTarget as HTMLElement | null;
    const canvas = target?.closest('[data-template-canvas]');

    if (!target || !canvas) {
        return;
    }

    selectedSlotIndex.value = slot.slot_index;
    target.setPointerCapture(event.pointerId);

    const canvasRect = canvas.getBoundingClientRect();

    resizeDragState.value = {
        slotIndex: slot.slot_index,
        pointerId: event.pointerId,
        handle,
        startClientX: event.clientX,
        startClientY: event.clientY,
        startX: slot.x,
        startY: slot.y,
        startWidth: slot.width,
        startHeight: slot.height,
        canvasWidthPx: Math.max(canvasRect.width, 1),
        canvasHeightPx: Math.max(canvasRect.height, 1),
    };
}

function applyAspectResize(
    handle: 'nw' | 'ne' | 'se' | 'sw',
    aspectRatio: number,
    proposed: { x: number; y: number; width: number; height: number },
    start: { x: number; y: number; width: number; height: number },
): { x: number; y: number; width: number; height: number } {
    const widthDelta = Math.abs(proposed.width - start.width);
    const heightDelta = Math.abs(proposed.height - start.height);
    const useWidth = widthDelta >= heightDelta;

    let width = proposed.width;
    let height = proposed.height;

    if (useWidth) {
        height = Math.max(1, Math.round(width / aspectRatio));
    } else {
        width = Math.max(1, Math.round(height * aspectRatio));
    }

    let x = proposed.x;
    let y = proposed.y;

    if (handle.includes('w')) {
        x = start.x + (start.width - width);
    }

    if (handle.includes('n')) {
        y = start.y + (start.height - height);
    }

    return { x, y, width, height };
}

function moveResizeDrag(event: PointerEvent): void {
    if (
        !resizeDragState.value ||
        resizeDragState.value.pointerId !== event.pointerId
    ) {
        return;
    }

    const canvas = getCanvasSize();
    const deltaX = event.clientX - resizeDragState.value.startClientX;
    const deltaY = event.clientY - resizeDragState.value.startClientY;
    const deltaCanvasX = Math.round(
        (deltaX / resizeDragState.value.canvasWidthPx) * canvas.width,
    );
    const deltaCanvasY = Math.round(
        (deltaY / resizeDragState.value.canvasHeightPx) * canvas.height,
    );

    const start = {
        x: resizeDragState.value.startX,
        y: resizeDragState.value.startY,
        width: resizeDragState.value.startWidth,
        height: resizeDragState.value.startHeight,
    };

    let proposed = {
        x: start.x,
        y: start.y,
        width: start.width,
        height: start.height,
    };

    if (resizeDragState.value.handle.includes('e')) {
        proposed.width = start.width + deltaCanvasX;
    }
    if (resizeDragState.value.handle.includes('s')) {
        proposed.height = start.height + deltaCanvasY;
    }
    if (resizeDragState.value.handle.includes('w')) {
        proposed.width = start.width - deltaCanvasX;
        proposed.x = start.x + deltaCanvasX;
    }
    if (resizeDragState.value.handle.includes('n')) {
        proposed.height = start.height - deltaCanvasY;
        proposed.y = start.y + deltaCanvasY;
    }

    if (lockAspectRatio.value && selectedSlotAspectRatio.value) {
        proposed = applyAspectResize(
            resizeDragState.value.handle,
            selectedSlotAspectRatio.value,
            proposed,
            start,
        );
    }

    updateSlotDraft(
        resizeDragState.value.slotIndex,
        {
            x: proposed.x,
            y: proposed.y,
            width: proposed.width,
            height: proposed.height,
        },
        { respectAspect: false },
    );
}

function stopResizeDrag(event: PointerEvent): void {
    if (
        !resizeDragState.value ||
        resizeDragState.value.pointerId !== event.pointerId
    ) {
        return;
    }

    const target = event.currentTarget as HTMLElement | null;

    if (target?.hasPointerCapture(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }

    resizeDragState.value = null;
}

function startSlotDrag(slot: EditableTemplateSlot, event: PointerEvent): void {
    const target = event.currentTarget as HTMLElement | null;
    const canvas = target?.parentElement;

    if (!target || !canvas) {
        return;
    }

    selectedSlotIndex.value = slot.slot_index;
    target.setPointerCapture(event.pointerId);

    const canvasRect = canvas.getBoundingClientRect();

    layoutDragState.value = {
        slotIndex: slot.slot_index,
        pointerId: event.pointerId,
        startClientX: event.clientX,
        startClientY: event.clientY,
        startX: slot.x,
        startY: slot.y,
        canvasWidthPx: Math.max(canvasRect.width, 1),
        canvasHeightPx: Math.max(canvasRect.height, 1),
    };
}

function isSlotDragging(slotIndex: number): boolean {
    return layoutDragState.value?.slotIndex === slotIndex;
}

function moveSlotDrag(event: PointerEvent): void {
    if (
        !layoutDragState.value ||
        layoutDragState.value.pointerId !== event.pointerId
    ) {
        return;
    }

    const canvas = getCanvasSize();
    const deltaX = event.clientX - layoutDragState.value.startClientX;
    const deltaY = event.clientY - layoutDragState.value.startClientY;
    const slot = slotDrafts.value.find(
        (item) => item.slot_index === layoutDragState.value?.slotIndex,
    );

    if (!slot) {
        return;
    }

    updateSlotDraft(layoutDragState.value.slotIndex, {
        x: clampSlotValue(
            layoutDragState.value.startX +
                Math.round((deltaX / layoutDragState.value.canvasWidthPx) * canvas.width),
            0,
            canvas.width - slot.width,
        ),
        y: clampSlotValue(
            layoutDragState.value.startY +
                Math.round((deltaY / layoutDragState.value.canvasHeightPx) * canvas.height),
            0,
            canvas.height - slot.height,
        ),
    });
}

function stopSlotDrag(event: PointerEvent): void {
    if (
        !layoutDragState.value ||
        layoutDragState.value.pointerId !== event.pointerId
    ) {
        return;
    }

    const target = event.currentTarget as HTMLElement | null;

    if (target?.hasPointerCapture(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }

    layoutDragState.value = null;
}

function normalizeApiError(error: unknown, fallbackMessage: string): string {
    const response = (error as { response?: { data?: unknown } })?.response
        ?.data as
        | { message?: string; errors?: Record<string, string[]> }
        | undefined;

    if (!response) {
        return fallbackMessage;
    }

    if (response.message) {
        return response.message;
    }

    const firstError = Object.values(response.errors ?? {})[0]?.[0];

    return firstError ?? fallbackMessage;
}

async function saveTemplateName(): Promise<void> {
    if (!template.value) {
        return;
    }

    const trimmedName = nameDraft.value.trim();

    if (!trimmedName) {
        templateError.value = 'Nama template tidak boleh kosong.';
        return;
    }

    templateFeedback.value = null;
    templateError.value = null;
    savingTemplate.value = true;

    try {
        const updated = await patch<TemplateDetail>(
            updateTemplate(template.value.id),
            {
                template_name: trimmedName,
            },
        );

        template.value = updated;
        nameDraft.value = updated.template_name;
        isEditingName.value = false;
        templateFeedback.value = 'Nama template berhasil diperbarui.';
    } catch (error: unknown) {
        templateError.value = normalizeApiError(
            error,
            'Gagal memperbarui nama template.',
        );
    } finally {
        savingTemplate.value = false;
    }
}

function startNameEdit(): void {
    if (!template.value) {
        return;
    }

    nameDraft.value = template.value.template_name;
    isEditingName.value = true;
}

function cancelNameEdit(): void {
    isEditingName.value = false;
    nameDraft.value = template.value?.template_name ?? '';
}

async function toggleArchive(): Promise<void> {
    if (!template.value) {
        return;
    }

    templateFeedback.value = null;
    templateError.value = null;
    savingTemplate.value = true;

    try {
        const nextStatus =
            template.value.status === 'archived' ? 'active' : 'archived';
        const updated = await patch<TemplateDetail>(
            updateTemplate(template.value.id),
            { status: nextStatus },
        );

        template.value = updated;
        templateFeedback.value =
            nextStatus === 'archived'
                ? 'Template diarsipkan.'
                : 'Template diaktifkan kembali.';
    } catch (error: unknown) {
        templateError.value = normalizeApiError(
            error,
            'Gagal mengubah status template.',
        );
    } finally {
        savingTemplate.value = false;
    }
}

async function duplicateTemplateNow(): Promise<void> {
    if (!template.value) {
        return;
    }

    templateFeedback.value = null;
    templateError.value = null;
    savingTemplate.value = true;

    try {
        const duplicated = await post<TemplateDetail>(
            duplicateTemplate(template.value.id),
        );

        templateFeedback.value = 'Template berhasil diduplikasi.';
        router.visit(templatesRoutes.show.url(duplicated.id));
    } catch (error: unknown) {
        templateError.value = normalizeApiError(
            error,
            'Gagal menduplikasi template.',
        );
    } finally {
        savingTemplate.value = false;
    }
}

async function deleteTemplate(): Promise<void> {
    if (!template.value) {
        return;
    }

    if (deleteConfirmName.value !== template.value.template_name) {
        templateError.value = 'Nama konfirmasi tidak sesuai.';
        return;
    }

    templateFeedback.value = null;
    templateError.value = null;
    savingTemplate.value = true;

    try {
        await del(destroyTemplate(template.value.id), {
            reason: deleteReason.value,
        });

        templateFeedback.value = 'Template berhasil dihapus.';
        router.visit(templatesRoutes.index.url());
    } catch (error: unknown) {
        templateError.value = normalizeApiError(
            error,
            'Gagal menghapus template.',
        );
    } finally {
        savingTemplate.value = false;
    }
}

async function saveSlotLayout(): Promise<void> {
    if (!template.value || !slotDrafts.value.length) {
        return;
    }

    layoutFeedback.value = null;
    layoutError.value = null;
    savingLayout.value = true;

    try {
        const updatedTemplate = await post<TemplateDetail>(
            updateTemplateSlots(template.value.id),
            {
                slots: slotDrafts.value.map((slot) => ({
                    slot_index: slot.slot_index,
                    x: slot.x,
                    y: slot.y,
                    width: slot.width,
                    height: slot.height,
                    rotation: slot.rotation,
                    border_radius: slot.border_radius,
                })),
            },
        );

        template.value = updatedTemplate;
        syncSlotDrafts();
        layoutFeedback.value = 'Layout slot berhasil disimpan.';
    } catch (error: unknown) {
        layoutError.value = normalizeApiError(
            error,
            'Gagal menyimpan layout slot.',
        );
    } finally {
        savingLayout.value = false;
    }
}

function resetSlotLayoutDraft(): void {
    syncSlotDrafts();
    layoutFeedback.value = 'Perubahan layout dikembalikan ke data terakhir.';
    layoutError.value = null;
}

async function addTemplateSlot(): Promise<void> {
    if (!template.value) {
        return;
    }

    layoutFeedback.value = null;
    layoutError.value = null;
    savingSlot.value = true;

    try {
        const updatedTemplate = await post<TemplateDetail>(
            storeTemplateSlot(template.value.id),
        );

        template.value = updatedTemplate;
        syncSlotDrafts();
        selectedSlotIndex.value =
            sortedSlots.value[sortedSlots.value.length - 1]?.slot_index ?? null;
        layoutFeedback.value = 'Slot baru ditambahkan.';
    } catch (error: unknown) {
        layoutError.value = normalizeApiError(
            error,
            'Gagal menambah slot.',
        );
    } finally {
        savingSlot.value = false;
    }
}

async function removeSelectedSlot(): Promise<void> {
    if (!template.value || !selectedSlot.value) {
        return;
    }

    if (!window.confirm('Hapus slot ini?')) {
        return;
    }

    layoutFeedback.value = null;
    layoutError.value = null;
    savingSlot.value = true;

    try {
        const updatedTemplate = await del<TemplateDetail>(
            destroyTemplateSlot({
                template: template.value.id,
                slotIndex: selectedSlot.value.slot_index,
            }),
        );

        template.value = updatedTemplate;
        syncSlotDrafts();
        layoutFeedback.value = 'Slot berhasil dihapus.';
    } catch (error: unknown) {
        layoutError.value = normalizeApiError(
            error,
            'Gagal menghapus slot.',
        );
    } finally {
        savingSlot.value = false;
    }
}

function getSlotPreviewStyle(slot: EditableTemplateSlot): Record<string, string> {
    const canvasWidth = Math.max(template.value?.canvas_width ?? 1, 1);
    const canvasHeight = Math.max(template.value?.canvas_height ?? 1, 1);

    return {
        left: `${((slot.x ?? 0) / canvasWidth) * 100}%`,
        top: `${((slot.y ?? 0) / canvasHeight) * 100}%`,
        width: `${(slot.width / canvasWidth) * 100}%`,
        height: `${(slot.height / canvasHeight) * 100}%`,
        borderRadius: `${Math.max(slot.border_radius ?? 8, 0)}px`,
        transform: `rotate(${slot.rotation ?? 0}deg)`,
    };
}

function getLayerStyle(layer: DynamicLayer): Record<string, string> {
    const canvasWidth = Math.max(template.value?.canvas_width ?? 1, 1);
    const canvasHeight = Math.max(template.value?.canvas_height ?? 1, 1);
    const width = layer.width ?? 0;
    const height = layer.height ?? 0;

    return {
        left: `${((layer.x ?? 0) / canvasWidth) * 100}%`,
        top: `${((layer.y ?? 0) / canvasHeight) * 100}%`,
        width: width ? `${(width / canvasWidth) * 100}%` : 'auto',
        height: height ? `${(height / canvasHeight) * 100}%` : 'auto',
        fontSize: layer.font_size ? `${layer.font_size}px` : '16px',
        color: layer.color ?? '#111827',
        opacity: layer.opacity ? `${layer.opacity / 100}` : '1',
        textAlign: layer.align ?? 'left',
        backgroundColor: layer.bg_color ?? 'transparent',
        padding: layer.padding ? `${layer.padding}px` : undefined,
    };
}

function startLayerDrag(layer: DynamicLayer, event: PointerEvent): void {
    const target = event.currentTarget as HTMLElement | null;
    const canvas = target?.closest('[data-template-canvas]') as HTMLElement | null;

    if (!target || !canvas) {
        return;
    }

    target.setPointerCapture(event.pointerId);

    const canvasRect = canvas.getBoundingClientRect();

    layerDragState.value = {
        layerId: layer.id,
        pointerId: event.pointerId,
        startClientX: event.clientX,
        startClientY: event.clientY,
        startX: layer.x ?? 0,
        startY: layer.y ?? 0,
        canvasWidthPx: Math.max(canvasRect.width, 1),
        canvasHeightPx: Math.max(canvasRect.height, 1),
    };
}

function moveLayerDrag(event: PointerEvent): void {
    if (
        !layerDragState.value ||
        layerDragState.value.pointerId !== event.pointerId
    ) {
        return;
    }

    const layer = dynamicLayers.value.find(
        (item) => item.id === layerDragState.value?.layerId,
    );

    if (!layer) {
        return;
    }

    const canvas = getCanvasSize();
    const deltaX = event.clientX - layerDragState.value.startClientX;
    const deltaY = event.clientY - layerDragState.value.startClientY;
    const width = layer.width ?? 0;
    const height = layer.height ?? 0;
    const maxX = Math.max(0, canvas.width - width);
    const maxY = Math.max(0, canvas.height - height);

    layer.x = clampSlotValue(
        layerDragState.value.startX +
            Math.round((deltaX / layerDragState.value.canvasWidthPx) * canvas.width),
        0,
        maxX,
    );
    layer.y = clampSlotValue(
        layerDragState.value.startY +
            Math.round((deltaY / layerDragState.value.canvasHeightPx) * canvas.height),
        0,
        maxY,
    );
}

function stopLayerDrag(event: PointerEvent): void {
    if (
        !layerDragState.value ||
        layerDragState.value.pointerId !== event.pointerId
    ) {
        return;
    }

    const target = event.currentTarget as HTMLElement | null;

    if (target?.hasPointerCapture(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }

    layerDragState.value = null;
}

function useTemplate(): void {
    if (!template.value) {
        return;
    }

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(TEMPLATE_PREFERENCE_KEY, template.value.id);
    }
    activeTemplateId.value = template.value.id;

    router.visit(
        sessionRoutes.index.url({
            query: {
                template_id: template.value.id,
            },
        }),
    );
}

function markTemplateAsActive(): void {
    if (!template.value || typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(TEMPLATE_PREFERENCE_KEY, template.value.id);
    activeTemplateId.value = template.value.id;
}

function syncActiveTemplateIndicator(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const urlTemplateId = new URLSearchParams(window.location.search).get(
        'template_id',
    );
    activeTemplateId.value =
        urlTemplateId ??
        window.localStorage.getItem(TEMPLATE_PREFERENCE_KEY);
}

function setSlotPhotoFile(slotIndex: number, file: File): void {
    if (!file.type.startsWith('image/')) {
        return;
    }

    const previousUrl = slotPhotoUrls.value[slotIndex];
    if (previousUrl && generatedObjectUrls.has(previousUrl)) {
        URL.revokeObjectURL(previousUrl);
        generatedObjectUrls.delete(previousUrl);
    }

    const nextUrl = URL.createObjectURL(file);
    generatedObjectUrls.add(nextUrl);
    slotPhotoUrls.value = {
        ...slotPhotoUrls.value,
        [slotIndex]: nextUrl,
    };
}

function onSlotPhotoSelected(
    slotIndex: number,
    event: Event,
): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];

    if (!file) {
        return;
    }

    setSlotPhotoFile(slotIndex, file);
}

function clearSlotPhoto(slotIndex: number): void {
    const currentUrl = slotPhotoUrls.value[slotIndex];

    if (currentUrl && generatedObjectUrls.has(currentUrl)) {
        URL.revokeObjectURL(currentUrl);
        generatedObjectUrls.delete(currentUrl);
    }

    const nextPhotoUrls = { ...slotPhotoUrls.value };
    delete nextPhotoUrls[slotIndex];
    slotPhotoUrls.value = nextPhotoUrls;
}

function clearAllSlotPhotos(): void {
    Object.keys(slotPhotoUrls.value).forEach((slotKey) => {
        clearSlotPhoto(Number(slotKey));
    });
}

async function setCustomOverlayFile(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];

    if (!file || file.type !== 'image/png') {
        return;
    }

    if (input) {
        input.value = '';
    }

    if (customOverlayUrl.value && generatedObjectUrls.has(customOverlayUrl.value)) {
        URL.revokeObjectURL(customOverlayUrl.value);
        generatedObjectUrls.delete(customOverlayUrl.value);
    }

    const nextUrl = URL.createObjectURL(file);
    generatedObjectUrls.add(nextUrl);
    customOverlayUrl.value = nextUrl;

    if (!template.value) {
        return;
    }

    templateFeedback.value = null;
    templateError.value = null;
    uploadingOverlay.value = true;

    try {
        const formData = new FormData();
        formData.append('overlay', file);

        const updatedTemplate = await post<TemplateDetail>(
            uploadTemplateOverlay(template.value.id),
            formData,
        );

        template.value = updatedTemplate;
        customOverlayUrl.value = updatedTemplate.overlay_url ?? null;
        overlayEnabled.value = true;
        templateFeedback.value = 'Overlay template berhasil disimpan.';
    } catch (error: unknown) {
        templateError.value = normalizeApiError(
            error,
            'Gagal menyimpan overlay template.',
        );
    } finally {
        uploadingOverlay.value = false;
    }
}

function clearCustomOverlay(): void {
    if (customOverlayUrl.value && generatedObjectUrls.has(customOverlayUrl.value)) {
        URL.revokeObjectURL(customOverlayUrl.value);
        generatedObjectUrls.delete(customOverlayUrl.value);
    }

    customOverlayUrl.value = null;
}

watch(
    sortedSlots,
    (slots) => {
        if (!slots.length) {
            selectedSlotIndex.value = null;

            return;
        }

        const hasCurrentSelection = slots.some(
            (slot) => slot.slot_index === selectedSlotIndex.value,
        );

        if (!hasCurrentSelection) {
            selectedSlotIndex.value = slots[0].slot_index;
        }
    },
    { immediate: true },
);

watch(
    dynamicLayers,
    (layers) => {
        layers.forEach((layer) => {
            if (layer.type !== 'qr' || layer.enabled === false) {
                return;
            }

            const key = getQrPreviewKey(layer);

            if (qrPreviewKeys.value[layer.id] === key) {
                return;
            }

            qrPreviewKeys.value = {
                ...qrPreviewKeys.value,
                [layer.id]: key,
            };

            refreshQrPreview(layer);
        });
    },
    { deep: true },
);

onMounted(async () => {
    try {
        template.value = await get<TemplateDetail>(showTemplate(props.templateId));
        nameDraft.value = template.value.template_name;
        syncSlotDrafts();
        initializeDynamicLayers();
        syncActiveTemplateIndicator();
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => {
    layoutDragState.value = null;
    resizeDragState.value = null;
    generatedObjectUrls.forEach((url) => URL.revokeObjectURL(url));
    generatedObjectUrls.clear();
});
</script>

<template>
    <AppLayout
        title="Template Detail"
        subtitle="Lihat dimensi canvas dan posisi slot yang akan dipakai dalam proses editing."
    >
        <div v-if="loading" class="text-sm text-slate-500">
            Loading template detail...
        </div>

        <div v-else-if="!template">
            <EmptyState
                title="Template tidak ditemukan"
                message="Periksa kembali template yang dipilih dari library."
            />
        </div>

        <div v-else class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-3">
                            <div v-if="!isEditingName" class="flex items-center gap-2">
                                <h2 class="text-2xl font-semibold text-slate-900">
                                    {{ template.template_name }}
                                </h2>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="startNameEdit"
                                >
                                    Edit Nama
                                </button>
                            </div>
                            <div v-else class="flex flex-wrap items-center gap-2">
                                <input
                                    v-model="nameDraft"
                                    type="text"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                />
                                <button
                                    type="button"
                                    class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="savingTemplate"
                                    @click="saveTemplateName"
                                >
                                    Simpan
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="cancelNameEdit"
                                >
                                    Batal
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-2 text-sm text-slate-500 md:grid-cols-2 xl:grid-cols-4">
                            <p>Code: {{ template.template_code ?? '-' }}</p>
                            <p>Category: {{ template.category ?? '-' }}</p>
                            <p>Paper Size: {{ template.paper_size ?? '-' }}</p>
                            <p>
                                Canvas:
                                {{ template.canvas_width ?? 0 }}x{{ template.canvas_height ?? 0 }}
                            </p>
                            <p>Status: {{ templateStatusLabel }}</p>
                            <p v-if="updatedByLabel">Updated by: {{ updatedByLabel }}</p>
                            <p v-if="updatedAtLabel">Updated at: {{ updatedAtLabel }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            class="rounded-lg border px-3 py-2 text-xs font-semibold"
                            :class="
                                isActiveTemplate
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                                    : 'border-slate-300 bg-slate-50 text-slate-600'
                            "
                        >
                            {{
                                isActiveTemplate
                                    ? 'Template aktif dari library'
                                    : 'Template belum aktif'
                            }}
                        </div>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            @click="markTemplateAsActive"
                        >
                            Tandai Aktif
                        </button>
                        <Link
                            :href="templatesRoutes.index.url()"
                            class="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        >
                            Kembali
                        </Link>
                        <button
                            type="button"
                            class="inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700"
                            @click="useTemplate"
                        >
                            Gunakan Template
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="savingTemplate"
                            @click="toggleArchive"
                        >
                            {{ template?.status === 'archived' ? 'Aktifkan' : 'Arsipkan' }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="savingTemplate"
                            @click="duplicateTemplateNow"
                        >
                            Duplicate
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-red-300 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50"
                            @click="showDeletePanel = !showDeletePanel"
                        >
                            Hapus Template
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!hasLayoutChanges || savingLayout"
                            @click="resetSlotLayoutDraft"
                        >
                            Reset Draft
                        </button>
                        <button
                            type="button"
                            class="inline-flex rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!hasLayoutChanges || savingLayout"
                            @click="saveSlotLayout"
                        >
                            {{ savingLayout ? 'Menyimpan...' : 'Simpan Layout Slot' }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="templateFeedback"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ templateFeedback }}
            </div>

            <div
                v-if="templateError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ templateError }}
            </div>

            <div
                v-if="showDeletePanel"
                class="rounded-xl border border-red-200 bg-red-50/70 p-4 text-sm text-red-800"
            >
                <div class="font-semibold text-red-900">
                    Hapus template ini?
                </div>
                <p class="mt-1 text-xs text-red-700">
                    Ketik nama template untuk konfirmasi penghapusan permanen.
                </p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <label class="space-y-1 text-xs text-red-700">
                        <span>Konfirmasi Nama</span>
                        <input
                            v-model="deleteConfirmName"
                            type="text"
                            class="w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-sm"
                            :placeholder="template?.template_name"
                        />
                    </label>
                    <label class="space-y-1 text-xs text-red-700">
                        <span>Alasan (opsional)</span>
                        <input
                            v-model="deleteReason"
                            type="text"
                            class="w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-sm"
                            placeholder="Duplikat / tidak digunakan"
                        />
                    </label>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="savingTemplate"
                        @click="deleteTemplate"
                    >
                        Hapus Permanen
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100"
                        @click="showDeletePanel = false"
                    >
                        Batal
                    </button>
                </div>
            </div>

            <div
                v-if="layoutFeedback"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ layoutFeedback }}
            </div>

            <div
                v-if="layoutError"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                {{ layoutError }}
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatsCard label="Total Slots" :value="sortedSlots.length" />
                <StatsCard label="Canvas Width" :value="template.canvas_width ?? 0" />
                <StatsCard label="Canvas Height" :value="template.canvas_height ?? 0" />
                <StatsCard label="Paper Size" :value="template.paper_size ?? '-'" />
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">
                                Canvas Preview
                            </h3>
                            <p class="text-sm text-slate-500">
                                Posisi slot divisualkan relatif terhadap dimensi canvas template.
                            </p>
                            <p class="text-xs text-blue-600">
                                Drag kotak slot di canvas untuk atur letak slot foto.
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-600">
                                <label class="inline-flex items-center gap-2">
                                    <input
                                        v-model="snapEnabled"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    Snap grid
                                </label>
                                <select
                                    v-model.number="snapStep"
                                    class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs"
                                    :disabled="!snapEnabled"
                                >
                                    <option :value="5">Grid 5px</option>
                                    <option :value="10">Grid 10px</option>
                                    <option :value="20">Grid 20px</option>
                                    <option :value="40">Grid 40px</option>
                                </select>
                                <label class="inline-flex items-center gap-2">
                                    <input
                                        v-model="lockAspectRatio"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    Lock ratio
                                </label>
                            </div>
                        </div>
                    </div>

                    <div
                        class="relative mx-auto flex min-h-96 w-full max-w-3xl items-center justify-center overflow-hidden rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6"
                    >
                        <img
                            v-if="template.preview_url"
                            :src="template.preview_url"
                            :alt="template.template_name"
                            class="absolute inset-0 h-full w-full object-cover opacity-20"
                        />

                        <div
                            data-template-canvas
                            class="relative w-full rounded-2xl border border-slate-300 bg-white/80 shadow-inner"
                            :style="{
                                aspectRatio: `${template.canvas_width ?? 4} / ${template.canvas_height ?? 3}`,
                            }"
                        >
                            <div
                                v-for="slot in sortedSlots"
                                :key="slot.slot_index"
                                class="absolute z-30 touch-none flex items-center justify-center overflow-hidden border-2 text-sm font-semibold transition"
                                :class="
                                    slot.slot_index === selectedSlotIndex
                                        ? isSlotDragging(slot.slot_index)
                                            ? 'z-10 cursor-grabbing border-emerald-500 bg-emerald-100/80 text-emerald-800'
                                            : 'z-10 cursor-grab border-emerald-500 bg-emerald-100/80 text-emerald-800'
                                        : 'cursor-grab border-blue-400 bg-blue-100/70 text-blue-700 hover:border-blue-500'
                                "
                                :style="getSlotPreviewStyle(slot)"
                                @click="selectedSlotIndex = slot.slot_index"
                                @pointerdown="startSlotDrag(slot, $event)"
                                @pointermove="moveSlotDrag($event)"
                                @pointerup="stopSlotDrag($event)"
                                @pointercancel="stopSlotDrag($event)"
                            >
                                <img
                                    v-if="slotPhotoUrls[slot.slot_index]"
                                    :src="slotPhotoUrls[slot.slot_index]"
                                    :alt="`Slot ${slot.slot_index}`"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>
                                    {{ slot.slot_index }}
                                </span>
                                <div
                                    v-if="selectedSlotIndex === slot.slot_index"
                                    class="pointer-events-none absolute inset-0 border border-emerald-300/80"
                                ></div>
                                <button
                                    v-if="selectedSlotIndex === slot.slot_index"
                                    type="button"
                                    class="absolute -left-1.5 -top-1.5 h-3 w-3 rounded-full border border-emerald-600 bg-white shadow-sm"
                                    @pointerdown.stop="
                                        startResizeDrag(slot, 'nw', $event)
                                    "
                                    @pointermove="moveResizeDrag($event)"
                                    @pointerup="stopResizeDrag($event)"
                                    @pointercancel="stopResizeDrag($event)"
                                ></button>
                                <button
                                    v-if="selectedSlotIndex === slot.slot_index"
                                    type="button"
                                    class="absolute -right-1.5 -top-1.5 h-3 w-3 rounded-full border border-emerald-600 bg-white shadow-sm"
                                    @pointerdown.stop="
                                        startResizeDrag(slot, 'ne', $event)
                                    "
                                    @pointermove="moveResizeDrag($event)"
                                    @pointerup="stopResizeDrag($event)"
                                    @pointercancel="stopResizeDrag($event)"
                                ></button>
                                <button
                                    v-if="selectedSlotIndex === slot.slot_index"
                                    type="button"
                                    class="absolute -right-1.5 -bottom-1.5 h-3 w-3 rounded-full border border-emerald-600 bg-white shadow-sm"
                                    @pointerdown.stop="
                                        startResizeDrag(slot, 'se', $event)
                                    "
                                    @pointermove="moveResizeDrag($event)"
                                    @pointerup="stopResizeDrag($event)"
                                    @pointercancel="stopResizeDrag($event)"
                                ></button>
                                <button
                                    v-if="selectedSlotIndex === slot.slot_index"
                                    type="button"
                                    class="absolute -left-1.5 -bottom-1.5 h-3 w-3 rounded-full border border-emerald-600 bg-white shadow-sm"
                                    @pointerdown.stop="
                                        startResizeDrag(slot, 'sw', $event)
                                    "
                                    @pointermove="moveResizeDrag($event)"
                                    @pointerup="stopResizeDrag($event)"
                                    @pointercancel="stopResizeDrag($event)"
                                ></button>
                            </div>

                            <div
                                v-for="layer in dynamicLayerPreview"
                                :key="layer.id"
                                class="absolute z-20 cursor-move"
                                :style="getLayerStyle(layer)"
                                v-show="layer.enabled !== false"
                                @pointerdown.stop="startLayerDrag(layer, $event)"
                                @pointermove="moveLayerDrag($event)"
                                @pointerup="stopLayerDrag($event)"
                                @pointercancel="stopLayerDrag($event)"
                            >
                                <div
                                    v-if="layer.type === 'text'"
                                    class="whitespace-pre-wrap rounded bg-white/80 px-1.5 py-1 text-xs font-semibold text-slate-800 shadow-sm"
                                >
                                    {{ layer.text || layer.label }}
                                </div>
                                <img
                                    v-else-if="qrPreviewUrls[layer.id]"
                                    :src="qrPreviewUrls[layer.id]"
                                    alt="QR Preview"
                                    class="h-full w-full object-contain"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center rounded border border-slate-400 text-xs font-semibold text-slate-700 shadow-sm"
                                >
                                    QR
                                </div>
                            </div>

                            <img
                                v-if="overlayEnabled && overlayImageUrl"
                                :src="overlayImageUrl"
                                alt="Overlay template"
                                class="pointer-events-none absolute inset-0 z-10 h-full w-full object-cover"
                                :style="{ opacity: overlayOpacity / 100 }"
                            />
                        </div>
                    </div>

                    <div
                        class="mt-4 space-y-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-800"
                    >
                        <div v-if="selectedSlot">
                            Slot aktif: {{ selectedSlot.slot_index }} •
                            {{ selectedSlot.width }}x{{ selectedSlot.height }}
                            • X: {{ selectedSlot.x }} • Y: {{ selectedSlot.y }}
                        </div>
                        <div v-else class="text-xs text-emerald-700">
                            Pilih slot untuk melihat detail dan menggeser posisi.
                        </div>

                        <div v-if="selectedSlot" class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                @click="nudgeSelectedSlot('x', -10)"
                            >
                                Geser Kiri
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                @click="nudgeSelectedSlot('x', 10)"
                            >
                                Geser Kanan
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                @click="nudgeSelectedSlot('y', -10)"
                            >
                                Geser Atas
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                @click="nudgeSelectedSlot('y', 10)"
                            >
                                Geser Bawah
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                @click="resizeSelectedSlot(20, 20)"
                            >
                                Perbesar
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-300 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                @click="resizeSelectedSlot(-20, -20)"
                            >
                                Perkecil
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="savingSlot"
                                @click="addTemplateSlot"
                            >
                                Tambah Slot
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-red-300 bg-white px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="savingSlot || sortedSlots.length <= 1 || !selectedSlot"
                                @click="removeSelectedSlot"
                            >
                                Hapus Slot
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs font-semibold text-slate-700">
                            Letak Foto Slot
                        </div>
                        <input
                            v-if="selectedSlot"
                            type="file"
                            accept="image/*"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs"
                            @change="onSlotPhotoSelected(selectedSlot.slot_index, $event)"
                        />
                        <div class="flex items-center gap-2">
                            <button
                                v-if="selectedSlot"
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-white"
                                @click="clearSlotPhoto(selectedSlot.slot_index)"
                            >
                                Hapus Foto Slot Aktif
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-white"
                                @click="clearAllSlotPhotos"
                            >
                                Hapus Semua Foto
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs font-semibold text-slate-700">
                            Overlay Template PNG
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                            <input
                                v-model="overlayEnabled"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300"
                            />
                            Tampilkan overlay
                        </label>
                        <input
                            type="file"
                            accept="image/png"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs"
                            :disabled="uploadingOverlay"
                            @change="setCustomOverlayFile"
                        />
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-slate-600">Opacity</span>
                            <input
                                v-model.number="overlayOpacity"
                                type="range"
                                min="0"
                                max="100"
                                step="5"
                                class="w-full"
                            />
                            <span class="w-10 text-right text-xs font-semibold text-slate-700">
                                {{ overlayOpacity }}%
                            </span>
                        </div>
                        <button
                            type="button"
                            class="w-fit rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-white"
                            @click="clearCustomOverlay"
                        >
                            Reset Overlay Kustom
                        </button>
                    </div>

                    <div class="mt-3 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="text-xs font-semibold text-slate-700">
                                Dynamic Layer (Text/QR)
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-white"
                                    @click="addTextLayer"
                                >
                                    Tambah Text
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-white"
                                    @click="addQrLayer"
                                >
                                    Tambah QR
                                </button>
                            </div>
                        </div>

                        <div v-if="!dynamicLayers.length" class="text-xs text-slate-500">
                            Belum ada layer tambahan.
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="layer in dynamicLayers"
                                :key="layer.id"
                                class="rounded-lg border border-slate-200 bg-white p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="text-xs font-semibold text-slate-700">
                                        {{ layer.type === 'text' ? 'Text Layer' : 'QR Layer' }}
                                    </div>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-red-600 hover:text-red-700"
                                        @click="removeLayer(layer.id)"
                                    >
                                        Hapus
                                    </button>
                                </div>

                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-600 md:col-span-2">
                                        <input
                                            v-model="layer.enabled"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        Aktifkan layer
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Label</span>
                                        <input
                                            v-model="layer.label"
                                            type="text"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                            placeholder="Event Name"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Opacity</span>
                                        <input
                                            v-model.number="layer.opacity"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>X</span>
                                        <input
                                            v-model.number="layer.x"
                                            type="number"
                                            min="0"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Y</span>
                                        <input
                                            v-model.number="layer.y"
                                            type="number"
                                            min="0"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                        />
                                    </label>
                                </div>

                                <div
                                    v-if="layer.type === 'text'"
                                    class="mt-3 grid gap-3 md:grid-cols-2"
                                >
                                    <label class="space-y-1 text-xs text-slate-600 md:col-span-2">
                                        <span>Text</span>
                                        <input
                                            v-model="layer.text"
                                            type="text"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                            placeholder="Happy Wedding"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Font Size</span>
                                        <input
                                            v-model.number="layer.font_size"
                                            type="number"
                                            min="8"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Color</span>
                                        <input
                                            v-model="layer.color"
                                            type="text"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                            placeholder="#111827"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Align</span>
                                        <select
                                            v-model="layer.align"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                        >
                                            <option value="left">Left</option>
                                            <option value="center">Center</option>
                                            <option value="right">Right</option>
                                        </select>
                                    </label>
                                </div>

                                <div
                                    v-else
                                    class="mt-3 grid gap-3 md:grid-cols-2"
                                >
                                    <label class="space-y-1 text-xs text-slate-600 md:col-span-2">
                                        <span>QR Data</span>
                                        <input
                                            v-model="layer.qr_data"
                                            type="text"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                            placeholder="https://photobooth.local/session"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Width</span>
                                        <input
                                            v-model.number="layer.width"
                                            type="number"
                                            min="60"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Height</span>
                                        <input
                                            v-model.number="layer.height"
                                            type="number"
                                            min="60"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>Padding</span>
                                        <input
                                            v-model.number="layer.padding"
                                            type="number"
                                            min="0"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                        />
                                    </label>
                                    <label class="space-y-1 text-xs text-slate-600">
                                        <span>QR Background</span>
                                        <input
                                            v-model="layer.bg_color"
                                            type="text"
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                                            placeholder="#ffffff"
                                        />
                                    </label>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-slate-600">
                                    <span class="font-semibold text-slate-700">Insert token:</span>
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-300 px-2 py-1 hover:bg-slate-50"
                                        @click="insertLayerToken(layer, '{session_code}')"
                                    >
                                        Session Code
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-300 px-2 py-1 hover:bg-slate-50"
                                        @click="insertLayerToken(layer, '{station_code}')"
                                    >
                                        Station Code
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-300 px-2 py-1 hover:bg-slate-50"
                                        @click="insertLayerToken(layer, '{station_name}')"
                                    >
                                        Station Name
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-300 px-2 py-1 hover:bg-slate-50"
                                        @click="insertLayerToken(layer, '{device_name}')"
                                    >
                                        Device Name
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-300 px-2 py-1 hover:bg-slate-50"
                                        @click="insertLayerToken(layer, '{render_date}')"
                                    >
                                        Render Date
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-300 px-2 py-1 hover:bg-slate-50"
                                        @click="insertLayerToken(layer, '{render_time}')"
                                    >
                                        Render Time
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-dashed border-slate-300 bg-white px-3 py-2 text-xs text-slate-500">
                            Variabel cepat: {session_code}, {station_code}, {station_name}, {device_name}, {render_date}, {render_time}
                        </div>

                        <button
                            type="button"
                            class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="savingLayers"
                            @click="saveDynamicLayers"
                        >
                            {{ savingLayers ? 'Menyimpan Layer...' : 'Simpan Layer Template' }}
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">
                        Slot Specification
                    </h3>

                    <div v-if="!sortedSlots.length" class="mt-4">
                        <EmptyState
                            title="Belum ada slot"
                            message="Slot template akan muncul di sini ketika konfigurasi layout tersedia."
                        />
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <div
                            v-for="slot in sortedSlots"
                            :key="slot.slot_index"
                            class="w-full rounded-lg border p-4 text-left transition"
                            :class="
                                slot.slot_index === selectedSlotIndex
                                    ? 'border-emerald-300 bg-emerald-50'
                                    : 'border-slate-200 hover:bg-slate-50'
                            "
                        >
                            <div class="flex items-center justify-between gap-3">
                                <button
                                    type="button"
                                    class="font-medium text-slate-900"
                                    @click="selectedSlotIndex = slot.slot_index"
                                >
                                    Slot {{ slot.slot_index }}
                                </button>
                                <div class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                    {{ slot.width }}x{{ slot.height }}
                                </div>
                            </div>

                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <label class="space-y-1 text-xs text-slate-600">
                                    <span>X</span>
                                    <input
                                        :value="slot.x"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                        @input="
                                            updateSlotDraft(slot.slot_index, {
                                                x: Number(($event.target as HTMLInputElement).value),
                                            })
                                        "
                                    />
                                </label>
                                <label class="space-y-1 text-xs text-slate-600">
                                    <span>Y</span>
                                    <input
                                        :value="slot.y"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                        @input="
                                            updateSlotDraft(slot.slot_index, {
                                                y: Number(($event.target as HTMLInputElement).value),
                                            })
                                        "
                                    />
                                </label>
                                <label class="space-y-1 text-xs text-slate-600">
                                    <span>Width</span>
                                    <input
                                        :value="slot.width"
                                        type="number"
                                        min="1"
                                        class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                        @input="
                                            updateSlotDraft(slot.slot_index, {
                                                width: Number(($event.target as HTMLInputElement).value),
                                            })
                                        "
                                    />
                                </label>
                                <label class="space-y-1 text-xs text-slate-600">
                                    <span>Height</span>
                                    <input
                                        :value="slot.height"
                                        type="number"
                                        min="1"
                                        class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                        @input="
                                            updateSlotDraft(slot.slot_index, {
                                                height: Number(($event.target as HTMLInputElement).value),
                                            })
                                        "
                                    />
                                </label>
                                <label class="space-y-1 text-xs text-slate-600">
                                    <span>Rotation</span>
                                    <input
                                        :value="slot.rotation"
                                        type="number"
                                        step="0.1"
                                        class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                        @input="
                                            updateSlotDraft(slot.slot_index, {
                                                rotation: Number(($event.target as HTMLInputElement).value),
                                            })
                                        "
                                    />
                                </label>
                                <label class="space-y-1 text-xs text-slate-600">
                                    <span>Border Radius</span>
                                    <input
                                        :value="slot.border_radius"
                                        type="number"
                                        min="0"
                                        class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                        @input="
                                            updateSlotDraft(slot.slot_index, {
                                                border_radius: Number(($event.target as HTMLInputElement).value),
                                            })
                                        "
                                    />
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
