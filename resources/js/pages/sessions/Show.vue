<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { store as storeEditJob } from '@/actions/App/Http/Controllers/Api/Editor/EditJobController';
import { index as listPrinters } from '@/actions/App/Http/Controllers/Api/Editor/PrinterController';
import {
    store as storePrintOrder,
    show as showPrintOrder,
} from '@/actions/App/Http/Controllers/Api/Editor/PrintOrderController';
import { store as storeQueueJob } from '@/actions/App/Http/Controllers/Api/Editor/PrintQueueController';
import { store as renderEditJob } from '@/actions/App/Http/Controllers/Api/Editor/RenderController';
import {
    show as showSession,
    faceFit as faceFitSessionPhoto,
} from '@/actions/App/Http/Controllers/Api/Editor/SessionController';
import {
    approve as approveSessionManualPayment,
    reject as rejectSessionManualPayment,
} from '@/actions/App/Http/Controllers/Api/Editor/SessionManualPaymentController';
import {
    store as storeSessionVoucher,
    revoke as revokeSessionVoucher,
} from '@/actions/App/Http/Controllers/Api/Editor/SessionVoucherController';
import { index as listTemplates } from '@/actions/App/Http/Controllers/Api/Editor/TemplateController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';
import * as printOrderRoutes from '@/routes/print-orders';
import * as printQueueRoutes from '@/routes/print-queue';

type PhotoItem = {
    id: string;
    capture_index: number;
    url: string | null;
    thumbnail_url?: string | null;
    original_url?: string | null;
    width?: number | null;
    height?: number | null;
};

type TemplateSlot = {
    slot_index: number;
    x?: number | null;
    y?: number | null;
    width: number;
    height: number;
    rotation?: number | null;
    border_radius?: number | null;
};

type SessionDetail = {
    id: string;
    session_code: string;
    device_name?: string;
    station_code?: string;
    status?: string;
    payment_status?: string | null;
    payment_method?: string | null;
    payment_ref?: string | null;
    paid_at?: string | null;
    customer_whatsapp?: string | null;
    additional_print_count?: number | null;
    manual_payment_status?: string | null;
    manual_payment_reviewed_at?: string | null;
    manual_payment_reviewer_name?: string | null;
    manual_payment_notes?: string | null;
    photos: PhotoItem[];
    latest_edit_job?: {
        id: string;
        version_no: number;
        status?: string;
        template?: {
            id?: string | null;
            template_name?: string | null;
        };
    } | null;
    active_rendered_output?: {
        id: string;
        version_no: number;
        file_url?: string | null;
    } | null;
    latest_print_order?: {
        id: string;
        order_code: string;
        status?: string;
        total_qty?: number;
        total_amount?: number | string;
        ordered_at?: string;
        printer?: {
            id?: string | null;
            name?: string | null;
        };
    } | null;
    vouchers?: SessionVoucherItem[];
};

type SessionVoucherItem = {
    id: string;
    voucher_code: string;
    voucher_type?: string | null;
    status?: string | null;
    notes?: string | null;
    applied_at?: string | null;
    revoked_at?: string | null;
};

type TemplateItem = {
    id: string;
    template_name: string;
    paper_size?: string | null;
    canvas_width?: number | null;
    canvas_height?: number | null;
    preview_url?: string | null;
    slots: TemplateSlot[];
};

type Assignment = {
    slot_index: number;
    photo: PhotoItem | null;
    face_fit?: boolean;
    crop: {
        zoom: number;
        offset_x: number;
        offset_y: number;
    };
    transform: {
        rotation: number;
    };
};

type AssignmentPatch = Omit<Partial<Assignment>, 'crop' | 'transform'> & {
    crop?: Partial<Assignment['crop']>;
    transform?: Partial<Assignment['transform']>;
};

type PrinterItem = {
    id: string;
    printer_name: string;
    paper_size_default?: string | null;
    is_default?: boolean;
    is_online?: boolean;
    status?: string;
    queue?: {
        pending?: number;
        processing?: number;
        failed?: number;
    };
};

type WorkflowStep = {
    key: string;
    title: string;
    description: string;
    state: 'pending' | 'ready' | 'active' | 'completed';
};

type PrintOrderDetail = {
    id: string;
    order_code: string;
    status?: string;
    total_qty?: number;
    total_amount?: number | string;
    ordered_at?: string;
    printer?: {
        id?: string | null;
        name?: string | null;
    };
};

const props = defineProps<{
    sessionId: string;
}>();

const TEMPLATE_PREFERENCE_KEY = 'photobooth.preferred_template_id';
const SMART_FIT_ENABLED_KEY = 'photobooth.smart_fit_enabled';
const SMART_FIT_BIAS_KEY = 'photobooth.smart_fit_bias';

const { get, post } = useApi();

const session = ref<SessionDetail | null>(null);
const templates = ref<TemplateItem[]>([]);
const printers = ref<PrinterItem[]>([]);
const slotAssignments = ref<Assignment[]>([]);
const loading = ref(true);
const refreshing = ref(false);
const lastSyncedAt = ref<string | null>(null);
const submitting = ref(false);
const creatingPrintOrder = ref(false);
const queueingPrintOrder = ref(false);
const selectedTemplateId = ref<string | null>(null);
const selectedSlotIndex = ref<number | null>(null);
const selectedPrinterId = ref<string | null>(null);
const copies = ref(1);
const paperSize = ref('4R');
const feedback = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const renderedFileUrl = ref<string | null>(null);
const smartFitEnabled = ref(true);
const smartFitBias = ref(-12);
const voucherCode = ref('');
const voucherType = ref('promo');
const voucherNotes = ref('');
const voucherSubmitting = ref(false);
const voucherRevokingId = ref<string | null>(null);
const voucherFilter = ref<'all' | 'applied' | 'revoked'>('all');
const manualPaymentNotes = ref('');
const manualPaymentRejectReason = ref('');
const manualPaymentSubmittingAction = ref<'approve' | 'reject' | null>(null);

const voucherTypeOptions = [
    { key: 'promo', label: 'Promo' },
    { key: 'skip', label: 'Skip' },
    { key: 'override', label: 'Override' },
    { key: 'free', label: 'Free' },
];

const selectedZoomLabel = computed(() => {
    if (!selectedSlotAssignment.value) {
        return '-';
    }

    return `${Math.round(selectedSlotAssignment.value.crop.zoom * 100)}%`;
});

const DEFAULT_ASSIGNMENT_CROP = {
    zoom: 1,
    offset_x: 0,
    offset_y: 0,
} as const;

const DEFAULT_ASSIGNMENT_TRANSFORM = {
    rotation: 0,
} as const;

/**
 * Template yang sedang aktif untuk editor slot.
 * Semua preview canvas dan validasi assignment mengikuti template ini.
 */
const selectedTemplate = computed(() => {
    return (
        templates.value.find(
            (template) => template.id === selectedTemplateId.value,
        ) ?? null
    );
});

const assignedSlotsByPhotoId = computed(() => {
    const map = new Map<string, number[]>();

    slotAssignments.value.forEach((assignment) => {
        if (!assignment.photo) {
            return;
        }

        const list = map.get(assignment.photo.id) ?? [];
        list.push(assignment.slot_index);
        map.set(assignment.photo.id, list);
    });

    return map;
});

/**
 * Lookup assignment per slot agar preview canvas bisa membaca
 * foto yang sedang menempati tiap slot tanpa pencarian berulang.
 */
const assignmentBySlotIndex = computed(() => {
    return new Map(
        slotAssignments.value.map((assignment) => [
            assignment.slot_index,
            assignment,
        ]),
    );
});

const selectedSlotAssignment = computed(() => {
    return (
        slotAssignments.value.find(
            (assignment) => assignment.slot_index === selectedSlotIndex.value,
        ) ?? null
    );
});

const selectedTemplateSlot = computed(() => {
    return (
        sortedTemplateSlots.value.find(
            (slot) => slot.slot_index === selectedSlotIndex.value,
        ) ?? null
    );
});

const selectedPrinter = computed(() => {
    return (
        printers.value.find(
            (printer) => printer.id === selectedPrinterId.value,
        ) ?? null
    );
});

const latestPrintOrder = computed(() => {
    return session.value?.latest_print_order ?? null;
});

const latestEditJob = computed(() => {
    return session.value?.latest_edit_job ?? null;
});

const sessionVouchers = computed(() => {
    return session.value?.vouchers ?? [];
});

const filteredSessionVouchers = computed(() => {
    if (voucherFilter.value === 'all') {
        return sessionVouchers.value;
    }

    return sessionVouchers.value.filter(
        (voucher) => (voucher.status ?? '').toLowerCase() === voucherFilter.value,
    );
});

const voucherSummary = computed(() => {
    const applied = sessionVouchers.value.filter(
        (voucher) => (voucher.status ?? '').toLowerCase() === 'applied',
    ).length;
    const revoked = sessionVouchers.value.filter(
        (voucher) => (voucher.status ?? '').toLowerCase() === 'revoked',
    ).length;

    return {
        total: sessionVouchers.value.length,
        applied,
        revoked,
    };
});

const isManualPaymentSession = computed(() => {
    return (
        (session.value?.payment_method ?? '').toLowerCase() === 'manual' ||
        !!session.value?.manual_payment_status
    );
});

const manualPaymentStatusLabel = computed(() => {
    const status = (session.value?.manual_payment_status ?? '').toLowerCase();

    if (status === 'pending_approval') {
        return 'pending approval';
    }

    if (status === 'approved' || status === 'rejected') {
        return status;
    }

    return 'not requested';
});

const canApproveManualPayment = computed(() => {
    if (!isManualPaymentSession.value || !session.value) {
        return false;
    }

    return (
        session.value.payment_status !== 'paid' &&
        session.value.manual_payment_status !== 'approved' &&
        manualPaymentSubmittingAction.value === null
    );
});

const canRejectManualPayment = computed(() => {
    if (!isManualPaymentSession.value || !session.value) {
        return false;
    }

    return (
        session.value.payment_status !== 'paid' &&
        session.value.manual_payment_status !== 'rejected' &&
        manualPaymentSubmittingAction.value === null
    );
});

const hasRenderedOutput = computed(() => {
    return !!session.value?.active_rendered_output;
});

const activeRenderVersionLabel = computed(() => {
    return session.value?.active_rendered_output?.version_no
        ? `v${session.value.active_rendered_output.version_no}`
        : 'Belum ada render';
});

const latestPrintOrderStatus = computed(() => {
    return latestPrintOrder.value?.status?.toLowerCase() ?? null;
});

const hasReusablePrintOrder = computed(() => {
    return ['created', 'queued', 'printing'].includes(
        latestPrintOrderStatus.value ?? '',
    );
});

const filledSlotCount = computed(() => {
    return slotAssignments.value.filter((assignment) => assignment.photo)
        .length;
});

const canCreateAndRender = computed(() => {
    return (
        !!selectedTemplate.value &&
        slotAssignments.value.length > 0 &&
        slotAssignments.value.every((assignment) => assignment.photo)
    );
});

const canCreatePrintOrder = computed(() => {
    return !!session.value?.active_rendered_output && !creatingPrintOrder.value;
});

const canQueuePrintOrder = computed(() => {
    return (
        !!latestPrintOrder.value &&
        latestPrintOrder.value.status === 'created' &&
        !!selectedPrinterId.value &&
        !queueingPrintOrder.value
    );
});

const sortedTemplateSlots = computed(() => {
    if (!selectedTemplate.value) {
        return [];
    }

    return [...selectedTemplate.value.slots].sort(
        (left, right) => left.slot_index - right.slot_index,
    );
});

const selectedSlotPhotoStyle = computed(() => {
    return getAssignmentImageStyle(selectedSlotAssignment.value);
});

const dragState = ref<{
    slotIndex: number;
    pointerId: number;
    startClientX: number;
    startClientY: number;
    startOffsetX: number;
    startOffsetY: number;
    frameWidth: number;
    frameHeight: number;
} | null>(null);

/**
 * Menampilkan progress operasional session secara ringkas agar operator tahu
 * posisi workflow saat ini tanpa harus membaca seluruh panel.
 */
const workflowSteps = computed<WorkflowStep[]>(() => {
    const hasPhotos = (session.value?.photos?.length ?? 0) > 0;
    const hasAssignments =
        slotAssignments.value.length > 0 && filledSlotCount.value > 0;

    return [
        {
            key: 'capture',
            title: 'Capture',
            description: hasPhotos
                ? `${session.value?.photos.length ?? 0} foto siap dipilih`
                : 'Belum ada foto pada session',
            state: hasPhotos ? 'completed' : 'pending',
        },
        {
            key: 'edit',
            title: 'Edit',
            description: canCreateAndRender.value
                ? 'Semua slot terisi dan siap dirender'
                : hasAssignments
                  ? `${filledSlotCount.value}/${slotAssignments.value.length} slot sudah diisi`
                  : 'Pilih template dan pasang foto ke slot',
            state: canCreateAndRender.value
                ? 'ready'
                : hasAssignments
                  ? 'active'
                  : 'pending',
        },
        {
            key: 'render',
            title: 'Render',
            description: hasRenderedOutput.value
                ? `Output aktif ${activeRenderVersionLabel.value}`
                : 'Belum ada output final',
            state: hasRenderedOutput.value
                ? 'completed'
                : canCreateAndRender.value
                  ? 'ready'
                  : 'pending',
        },
        {
            key: 'print-order',
            title: 'Print Order',
            description: latestPrintOrder.value
                ? `${latestPrintOrder.value.order_code} - ${latestPrintOrder.value.status ?? 'unknown'}`
                : 'Belum ada print order aktif',
            state: latestPrintOrder.value
                ? ['completed', 'printed'].includes(
                      latestPrintOrderStatus.value ?? '',
                  )
                    ? 'completed'
                    : 'active'
                : hasRenderedOutput.value
                  ? 'ready'
                  : 'pending',
        },
        {
            key: 'queue',
            title: 'Queue',
            description:
                latestPrintOrderStatus.value === 'queued'
                    ? 'Order sudah masuk antrean printer'
                    : latestPrintOrderStatus.value === 'printing'
                      ? 'Printer sedang memproses order'
                      : 'Kirim order created ke printer queue',
            state:
                latestPrintOrderStatus.value === 'queued' ||
                latestPrintOrderStatus.value === 'printing' ||
                latestPrintOrderStatus.value === 'completed'
                    ? 'completed'
                    : latestPrintOrderStatus.value === 'created'
                      ? 'ready'
                      : 'pending',
        },
    ];
});

const renderActionLabel = computed(() => {
    if (submitting.value) {
        return hasRenderedOutput.value
            ? 'Membuat render versi baru...'
            : 'Processing Render...';
    }

    return hasRenderedOutput.value
        ? 'Create New Render Version'
        : 'Create Edit Job & Render';
});

const renderHelperText = computed(() => {
    if (hasRenderedOutput.value) {
        return 'Render baru akan membuat versi output aktif berikutnya tanpa menghapus riwayat render sebelumnya.';
    }

    return 'Render final hanya aktif jika semua slot template sudah terisi.';
});

const printOrderActionLabel = computed(() => {
    if (creatingPrintOrder.value) {
        return 'Creating Order...';
    }

    if (!hasRenderedOutput.value) {
        return 'Render Final Dulu';
    }

    if (hasReusablePrintOrder.value) {
        return 'Use Existing Print Order';
    }

    return 'Create Print Order';
});

const printOrderHelperText = computed(() => {
    if (!hasRenderedOutput.value) {
        return 'Buat render final terlebih dulu agar session memiliki output yang bisa dicetak.';
    }

    if (hasReusablePrintOrder.value) {
        return 'Session ini sudah punya print order aktif. Menekan tombol akan memakai order yang masih relevan.';
    }

    return 'Buat print order baru dari rendered output aktif dan printer yang dipilih.';
});

const queueHelperText = computed(() => {
    if (!latestPrintOrder.value) {
        return 'Queue baru bisa dilakukan setelah print order berhasil dibuat.';
    }

    if (latestPrintOrderStatus.value === 'queued') {
        return 'Order ini sudah ada di antrean printer.';
    }

    if (latestPrintOrderStatus.value === 'printing') {
        return 'Order sedang diproses printer dan tidak perlu diantrikan ulang.';
    }

    return 'Kirim print order berstatus created ke printer queue aktif.';
});

function buildDefaultAssignments(): Assignment[] {
    return sortedTemplateSlots.value.map((slot, index) => ({
        slot_index: slot.slot_index,
        photo: session.value?.photos[index] ?? null,
        crop: { ...DEFAULT_ASSIGNMENT_CROP },
        transform: { ...DEFAULT_ASSIGNMENT_TRANSFORM },
    }));
}

/**
 * Menginisialisasi assignment slot berdasarkan urutan foto session.
 * Ini dipakai saat template dipilih pertama kali atau saat operator
 * ingin mengembalikan editor ke susunan default.
 */
function initializeAssignments(): void {
    slotAssignments.value = buildDefaultAssignments();
    selectedSlotIndex.value = slotAssignments.value[0]?.slot_index ?? null;
}

function setSelectedSlot(slotIndex: number): void {
    selectedSlotIndex.value = slotIndex;
}

/**
 * Mengganti isi assignment slot secara non-destruktif.
 * Helper ini menjadi fondasi semua perubahan editor: assign foto, swap slot,
 * reset framing, sampai penyesuaian crop dan rotasi.
 */
function updateAssignment(
    slotIndex: number,
    patch: AssignmentPatch,
): void {
    slotAssignments.value = slotAssignments.value.map((assignment) => {
        if (assignment.slot_index !== slotIndex) {
            return assignment;
        }

        return {
            ...assignment,
            ...patch,
            crop: {
                ...assignment.crop,
                ...(patch.crop ?? {}),
            },
            transform: {
                ...assignment.transform,
                ...(patch.transform ?? {}),
            },
        };
    });
}

function buildDefaultAssignmentState(photo: PhotoItem | null): Pick<
    Assignment,
    'photo' | 'crop' | 'transform' | 'face_fit'
> {
    return {
        photo,
        face_fit: false,
        crop: { ...DEFAULT_ASSIGNMENT_CROP },
        transform: { ...DEFAULT_ASSIGNMENT_TRANSFORM },
    };
}

function getTemplateSlot(slotIndex: number): TemplateSlot | null {
    return (
        sortedTemplateSlots.value.find((slot) => slot.slot_index === slotIndex) ??
        null
    );
}

function computeSmartCrop(photo: PhotoItem, slot: TemplateSlot): Assignment['crop'] {
    if (!photo.width || !photo.height) {
        return { ...DEFAULT_ASSIGNMENT_CROP };
    }

    const photoAspect = photo.width / Math.max(photo.height, 1);
    const slotAspect = slot.width / Math.max(slot.height, 1);
    const delta = Math.abs(Math.log(photoAspect / slotAspect));

    let zoom = 1;

    if (delta > 0.5) {
        zoom = 1.25;
    } else if (delta > 0.25) {
        zoom = 1.15;
    } else if (delta > 0.1) {
        zoom = 1.05;
    }

    return {
        zoom,
        offset_x: 0,
        offset_y: Math.min(0, Math.max(-30, smartFitBias.value)),
    };
}

function buildSmartAssignmentState(
    photo: PhotoItem | null,
    slotIndex: number,
): Pick<Assignment, 'photo' | 'crop' | 'transform' | 'face_fit'> {
    if (!photo) {
        return buildDefaultAssignmentState(null);
    }

    const slot = getTemplateSlot(slotIndex);

    if (!smartFitEnabled.value || !slot) {
        return buildDefaultAssignmentState(photo);
    }

    return {
        photo,
        face_fit: false,
        crop: computeSmartCrop(photo, slot),
        transform: { ...DEFAULT_ASSIGNMENT_TRANSFORM },
    };
}

async function requestFaceFit(photo: PhotoItem, slotIndex: number): Promise<void> {
    if (!smartFitEnabled.value) {
        return;
    }

    const slot = getTemplateSlot(slotIndex);

    if (!slot) {
        return;
    }

    try {
        const response = await get<{
            found: boolean;
            crop?: Assignment['crop'];
        }>(
            faceFitSessionPhoto({
                session: props.sessionId,
                photo: photo.id,
            }),
            {
                slot_width: slot.width,
                slot_height: slot.height,
            },
        );

        if (response.found && response.crop) {
            updateAssignment(slotIndex, { crop: response.crop });
        }
    } catch {
        // Fallback to heuristic if face-fit fails.
    }
}

function focusNextSlot(currentSlotIndex: number): void {
    if (!slotAssignments.value.length) {
        selectedSlotIndex.value = null;

        return;
    }

    const currentIndex = slotAssignments.value.findIndex(
        (assignment) => assignment.slot_index === currentSlotIndex,
    );

    if (currentIndex === -1) {
        return;
    }

    const nextEmptyAssignment = slotAssignments.value
        .slice(currentIndex + 1)
        .find((assignment) => !assignment.photo);

    if (nextEmptyAssignment) {
        selectedSlotIndex.value = nextEmptyAssignment.slot_index;

        return;
    }

    const nextAssignment = slotAssignments.value[currentIndex + 1];

    if (nextAssignment) {
        selectedSlotIndex.value = nextAssignment.slot_index;
    }
}

function assignPhotoToSlot(photo: PhotoItem, slotIndex: number): void {
    const targetAssignment = slotAssignments.value.find(
        (assignment) => assignment.slot_index === slotIndex,
    );

    if (!targetAssignment) {
        return;
    }

    if (targetAssignment.photo?.id === photo.id) {
        feedback.value = `Photo #${photo.capture_index} sudah ada di slot ${slotIndex}.`;
        errorMessage.value = null;

        return;
    }

    updateAssignment(slotIndex, buildSmartAssignmentState(photo, slotIndex));
    requestFaceFit(photo, slotIndex);

    feedback.value = `Photo #${photo.capture_index} dipasang ke slot ${slotIndex}.`;
    errorMessage.value = null;

    focusNextSlot(slotIndex);
}

function duplicatePhotoToNextSlot(photo: PhotoItem, slotIndex: number): void {
    const sourceAssignment = slotAssignments.value.find(
        (assignment) => assignment.slot_index === slotIndex,
    );

    if (!sourceAssignment?.photo) {
        return;
    }

    const currentIndex = slotAssignments.value.findIndex(
        (assignment) => assignment.slot_index === slotIndex,
    );

    if (currentIndex === -1) {
        return;
    }

    const nextEmpty = slotAssignments.value
        .slice(currentIndex + 1)
        .find((assignment) => !assignment.photo);

    if (!nextEmpty) {
        feedback.value = 'Tidak ada slot kosong berikutnya untuk duplikat.';
        errorMessage.value = null;

        return;
    }

    updateAssignment(nextEmpty.slot_index, {
        photo,
        crop: { ...sourceAssignment.crop },
        transform: { ...sourceAssignment.transform },
    });

    feedback.value = `Photo #${photo.capture_index} diduplikat ke slot ${nextEmpty.slot_index}.`;
    errorMessage.value = null;
    selectedSlotIndex.value = nextEmpty.slot_index;
}

function handlePhotoClick(photo: PhotoItem): void {
    const targetSlotIndex =
        selectedSlotIndex.value ?? slotAssignments.value[0]?.slot_index ?? null;

    if (targetSlotIndex === null) {
        return;
    }

    assignPhotoToSlot(photo, targetSlotIndex);
}

function clearSlot(slotIndex: number): void {
    updateAssignment(slotIndex, buildDefaultAssignmentState(null));
    selectedSlotIndex.value = slotIndex;
    feedback.value = `Slot ${slotIndex} dikosongkan.`;
    errorMessage.value = null;
}

function clearAllSlots(): void {
    slotAssignments.value = slotAssignments.value.map((assignment) => ({
        ...assignment,
        ...buildDefaultAssignmentState(null),
    }));
    selectedSlotIndex.value = slotAssignments.value[0]?.slot_index ?? null;
    feedback.value = 'Semua slot dikosongkan.';
    errorMessage.value = null;
}

async function approveManualPayment(): Promise<void> {
    if (!session.value || !canApproveManualPayment.value) {
        return;
    }

    manualPaymentSubmittingAction.value = 'approve';
    feedback.value = null;
    errorMessage.value = null;

    try {
        const payload = {
            notes: manualPaymentNotes.value.trim() || null,
        };

        const response = await post<{ message?: string }>(
            approveSessionManualPayment(session.value.id),
            payload,
        );

        manualPaymentRejectReason.value = '';
        feedback.value = response.message ?? 'Manual payment disetujui.';

        await loadData(false);
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal menyetujui manual payment.',
        );
    } finally {
        manualPaymentSubmittingAction.value = null;
    }
}

async function rejectManualPayment(): Promise<void> {
    if (!session.value || !canRejectManualPayment.value) {
        return;
    }

    if (!manualPaymentRejectReason.value.trim()) {
        errorMessage.value = 'Alasan reject wajib diisi.';
        feedback.value = null;

        return;
    }

    manualPaymentSubmittingAction.value = 'reject';
    feedback.value = null;
    errorMessage.value = null;

    try {
        const response = await post<{ message?: string }>(
            rejectSessionManualPayment(session.value.id),
            {
                reason: manualPaymentRejectReason.value.trim(),
            },
        );

        feedback.value = response.message ?? 'Manual payment ditolak.';
        manualPaymentNotes.value = '';

        await loadData(false);
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal menolak manual payment.',
        );
    } finally {
        manualPaymentSubmittingAction.value = null;
    }
}

async function applyVoucher(): Promise<void> {
    if (!session.value) {
        return;
    }

    if (!voucherCode.value.trim()) {
        errorMessage.value = 'Masukkan kode voucher terlebih dulu.';
        feedback.value = null;

        return;
    }

    voucherSubmitting.value = true;
    feedback.value = null;
    errorMessage.value = null;

    try {
        const response = await post<{
            message?: string;
            voucher: SessionVoucherItem;
        }>(storeSessionVoucher(session.value.id), {
            voucher_code: voucherCode.value.trim(),
            voucher_type: voucherType.value,
            notes: voucherNotes.value.trim() || null,
        });

        const vouchers = session.value.vouchers ?? [];
        const index = vouchers.findIndex(
            (voucher) => voucher.id === response.voucher.id,
        );

        if (index >= 0) {
            vouchers.splice(index, 1, response.voucher);
        } else {
            vouchers.unshift(response.voucher);
        }

        session.value.vouchers = vouchers;
        voucherCode.value = '';
        voucherNotes.value = '';

        feedback.value = response.message ?? 'Voucher diterapkan.';
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal menerapkan voucher.',
        );
    } finally {
        voucherSubmitting.value = false;
    }
}

async function revokeVoucher(voucherId: string): Promise<void> {
    if (!session.value) {
        return;
    }

    voucherRevokingId.value = voucherId;
    feedback.value = null;
    errorMessage.value = null;

    try {
        const response = await post<{
            message?: string;
            voucher: SessionVoucherItem;
        }>(revokeSessionVoucher({ session: session.value.id, voucher: voucherId }), {});

        const vouchers = session.value.vouchers ?? [];
        const index = vouchers.findIndex(
            (voucher) => voucher.id === response.voucher.id,
        );

        if (index >= 0) {
            vouchers.splice(index, 1, response.voucher);
        }

        session.value.vouchers = vouchers;
        feedback.value = response.message ?? 'Voucher dicabut.';
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal mencabut voucher.',
        );
    } finally {
        voucherRevokingId.value = null;
    }
}

function resetAssignments(): void {
    initializeAssignments();
    feedback.value = 'Slot assignment dikembalikan ke urutan default.';
    errorMessage.value = null;
}

function autoFillOpenSlots(): void {
    const remainingPhotos = (session.value?.photos ?? []).filter((photo) => {
        return !assignedSlotsByPhotoId.value.has(photo.id);
    });

    let photoIndex = 0;

    slotAssignments.value = slotAssignments.value.map((assignment) => {
        if (assignment.photo || !remainingPhotos[photoIndex]) {
            return assignment;
        }

        const nextPhoto = remainingPhotos[photoIndex];

        photoIndex += 1;

        return {
            ...assignment,
            ...buildSmartAssignmentState(nextPhoto, assignment.slot_index),
        };
    });

    feedback.value = 'Slot kosong diisi otomatis dari foto yang tersisa.';
    errorMessage.value = null;

    slotAssignments.value.forEach((assignment) => {
        if (
            assignment.photo &&
            assignment.crop.zoom === 1 &&
            assignment.crop.offset_x === 0 &&
            assignment.crop.offset_y === 0
        ) {
            requestFaceFit(assignment.photo, assignment.slot_index);
        }
    });
}

function applySmartFitAll(): void {
    slotAssignments.value = slotAssignments.value.map((assignment) => {
        if (!assignment.photo) {
            return assignment;
        }

        return {
            ...assignment,
            ...buildSmartAssignmentState(assignment.photo, assignment.slot_index),
        };
    });

    feedback.value = 'Smart fit diterapkan ke semua slot terisi.';
    errorMessage.value = null;
}

function getPhotoUrl(photo: PhotoItem | null | undefined): string {
    return photo?.url ?? photo?.thumbnail_url ?? photo?.original_url ?? '';
}

/**
 * Menghasilkan style preview untuk assignment aktif sehingga operator bisa
 * melihat framing zoom/pan/rotation langsung di UI sebelum render final.
 */
function getAssignmentImageStyle(
    assignment: Assignment | null | undefined,
): Record<string, string> {
    if (!assignment?.photo) {
        return {};
    }

    return {
        objectPosition: `${50 + assignment.crop.offset_x / 2}% ${50 + assignment.crop.offset_y / 2}%`,
        transform: `scale(${assignment.crop.zoom}) rotate(${assignment.transform.rotation}deg)`,
        transformOrigin: 'center center',
    };
}

function updateSelectedAssignmentCrop(
    patch: Partial<Assignment['crop']>,
): void {
    if (!selectedSlotAssignment.value) {
        return;
    }

    updateAssignment(selectedSlotAssignment.value.slot_index, {
        crop: patch,
    });
}

function updateSelectedAssignmentTransform(
    patch: Partial<Assignment['transform']>,
): void {
    if (!selectedSlotAssignment.value) {
        return;
    }

    updateAssignment(selectedSlotAssignment.value.slot_index, {
        transform: patch,
    });
}

function nudgeSelectedOffset(axis: 'x' | 'y', delta: number): void {
    if (!selectedSlotAssignment.value?.photo) {
        return;
    }

    if (axis === 'x') {
        updateSelectedAssignmentCrop({
            offset_x: Math.max(
                -100,
                Math.min(
                    100,
                    selectedSlotAssignment.value.crop.offset_x + delta,
                ),
            ),
        });

        return;
    }

    updateSelectedAssignmentCrop({
        offset_y: Math.max(
            -100,
            Math.min(100, selectedSlotAssignment.value.crop.offset_y + delta),
        ),
    });
}

function stepSelectedZoom(delta: number): void {
    if (!selectedSlotAssignment.value?.photo) {
        return;
    }

    updateSelectedAssignmentCrop({
        zoom: Math.max(
            1,
            Math.min(
                3,
                Number(
                    (selectedSlotAssignment.value.crop.zoom + delta).toFixed(2),
                ),
            ),
        ),
    });
}

function rotateSelectedPhoto(delta: number): void {
    if (!selectedSlotAssignment.value?.photo) {
        return;
    }

    const currentRotation = selectedSlotAssignment.value.transform.rotation;
    const normalizedRotation = ((currentRotation + delta) % 360 + 360) % 360;

    updateSelectedAssignmentTransform({
        rotation: normalizedRotation,
    });
}

function clampOffset(value: number): number {
    return Math.max(-100, Math.min(100, value));
}

function isDraggingSlot(slotIndex: number): boolean {
    return dragState.value?.slotIndex === slotIndex;
}

/**
 * Drag langsung pada preview slot agar operator bisa memindahkan framing foto
 * secara visual, tanpa harus mengandalkan slider pan horizontal/vertical.
 */
function startSlotPhotoDrag(slotIndex: number, event: PointerEvent): void {
    setSelectedSlot(slotIndex);

    const assignment = assignmentBySlotIndex.value.get(slotIndex);
    const target = event.currentTarget as HTMLElement | null;

    if (!assignment?.photo || !target) {
        return;
    }

    target.setPointerCapture(event.pointerId);

    const frameRect = target.getBoundingClientRect();

    dragState.value = {
        slotIndex,
        pointerId: event.pointerId,
        startClientX: event.clientX,
        startClientY: event.clientY,
        startOffsetX: assignment.crop.offset_x,
        startOffsetY: assignment.crop.offset_y,
        frameWidth: Math.max(frameRect.width, 1),
        frameHeight: Math.max(frameRect.height, 1),
    };
}

function continueSlotPhotoDrag(event: PointerEvent): void {
    if (!dragState.value || dragState.value.pointerId !== event.pointerId) {
        return;
    }

    const deltaX = event.clientX - dragState.value.startClientX;
    const deltaY = event.clientY - dragState.value.startClientY;

    updateAssignment(dragState.value.slotIndex, {
        crop: {
            offset_x: Number(
                clampOffset(
                    dragState.value.startOffsetX +
                        (deltaX / dragState.value.frameWidth) * 200,
                ).toFixed(1),
            ),
            offset_y: Number(
                clampOffset(
                    dragState.value.startOffsetY +
                        (deltaY / dragState.value.frameHeight) * 200,
                ).toFixed(1),
            ),
        },
    });
}

function stopSlotPhotoDrag(event: PointerEvent): void {
    if (!dragState.value || dragState.value.pointerId !== event.pointerId) {
        return;
    }

    const target = event.currentTarget as HTMLElement | null;

    if (target?.hasPointerCapture(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }

    dragState.value = null;
}

/**
 * Preset cepat untuk operator agar framing umum bisa diterapkan
 * tanpa perlu menggeser seluruh kontrol manual satu per satu.
 */
function applySelectedFramingPreset(
    preset: 'center' | 'close-up' | 'show-more' | 'left' | 'right',
): void {
    if (!selectedSlotAssignment.value?.photo) {
        return;
    }

    if (preset === 'center') {
        updateAssignment(selectedSlotAssignment.value.slot_index, {
            crop: {
                zoom: 1,
                offset_x: 0,
                offset_y: 0,
            },
        });

        return;
    }

    if (preset === 'close-up') {
        updateAssignment(selectedSlotAssignment.value.slot_index, {
            crop: {
                zoom: 1.45,
                offset_x: 0,
                offset_y: 0,
            },
        });

        return;
    }

    if (preset === 'show-more') {
        updateAssignment(selectedSlotAssignment.value.slot_index, {
            crop: {
                zoom: 1,
                offset_x: 0,
                offset_y: -10,
            },
        });

        return;
    }

    updateAssignment(selectedSlotAssignment.value.slot_index, {
        crop: {
            zoom: Math.max(1.15, selectedSlotAssignment.value.crop.zoom),
            offset_x: preset === 'left' ? -35 : 35,
            offset_y: selectedSlotAssignment.value.crop.offset_y,
        },
    });
}

function resetSelectedAdjustment(): void {
    if (!selectedSlotAssignment.value) {
        return;
    }

    updateAssignment(selectedSlotAssignment.value.slot_index, {
        crop: { ...DEFAULT_ASSIGNMENT_CROP },
        transform: { ...DEFAULT_ASSIGNMENT_TRANSFORM },
    });

    feedback.value = `Framing slot ${selectedSlotAssignment.value.slot_index} dikembalikan ke posisi default.`;
    errorMessage.value = null;
}

/**
 * Mengubah koordinat absolut slot template menjadi persentase canvas agar
 * preview tetap proporsional di berbagai ukuran layar.
 */
function getTemplateSlotStyle(slot: TemplateSlot): Record<string, string> {
    const canvasWidth = Math.max(selectedTemplate.value?.canvas_width ?? 1, 1);
    const canvasHeight = Math.max(
        selectedTemplate.value?.canvas_height ?? 1,
        1,
    );

    return {
        left: `${(((slot.x ?? 0) as number) / canvasWidth) * 100}%`,
        top: `${(((slot.y ?? 0) as number) / canvasHeight) * 100}%`,
        width: `${(slot.width / canvasWidth) * 100}%`,
        height: `${(slot.height / canvasHeight) * 100}%`,
        borderRadius: `${slot.border_radius ?? 16}px`,
        transform: `rotate(${slot.rotation ?? 0}deg)`,
    };
}

function getSlotPreviewImageStyle(slotIndex: number): Record<string, string> {
    return getAssignmentImageStyle(assignmentBySlotIndex.value.get(slotIndex));
}

function getDefaultPrinterId(printerList: PrinterItem[]): string | null {
    return (
        session.value?.latest_print_order?.printer?.id ??
        printerList.find((printer) => printer.is_default)?.id ??
        printerList.find((printer) => printer.is_online)?.id ??
        printerList[0]?.id ??
        null
    );
}

function syncPrinterDefaults(printerList: PrinterItem[]): void {
    const nextPrinterId =
        selectedPrinterId.value ?? getDefaultPrinterId(printerList);

    selectedPrinterId.value = nextPrinterId;

    const printer = printerList.find((item) => item.id === nextPrinterId);

    if (printer?.paper_size_default) {
        paperSize.value = printer.paper_size_default;
    }
}

function normalizeApiError(error: unknown, fallbackMessage: string): string {
    return (
        (error as { response?: { data?: { message?: string } } })?.response
            ?.data?.message ?? fallbackMessage
    );
}

function resolvePreferredTemplateId(templateList: TemplateItem[]): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const urlTemplateId = new URLSearchParams(window.location.search).get(
        'template_id',
    );
    const storedTemplateId = window.localStorage.getItem(
        TEMPLATE_PREFERENCE_KEY,
    );
    const candidateTemplateId = urlTemplateId ?? storedTemplateId;

    if (!candidateTemplateId) {
        return null;
    }

    return templateList.some((template) => template.id === candidateTemplateId)
        ? candidateTemplateId
        : null;
}

/**
 * Memuat ulang seluruh workspace session: detail session, template aktif,
 * printer, rendered output, dan print order terbaru.
 *
 * `showLoader` dipakai agar kita bisa membedakan full-page loading pertama
 * dengan refresh ringan setelah operator sudah berada di halaman.
 */
async function loadData(showLoader = true): Promise<void> {
    if (typeof window !== 'undefined') {
        const savedEnabled = window.localStorage.getItem(SMART_FIT_ENABLED_KEY);
        const savedBias = window.localStorage.getItem(SMART_FIT_BIAS_KEY);

        if (savedEnabled !== null) {
            smartFitEnabled.value = savedEnabled === 'true';
        }

        if (savedBias !== null && !Number.isNaN(Number(savedBias))) {
            smartFitBias.value = Number(savedBias);
        }
    }

    if (showLoader) {
        loading.value = true;
    } else {
        refreshing.value = true;
    }

    errorMessage.value = null;

    try {
        const [sessionData, templateData, printerData] = await Promise.all([
            get<SessionDetail>(showSession(props.sessionId)),
            get<TemplateItem[]>(listTemplates()),
            get<PrinterItem[]>(listPrinters()),
        ]);

        const currentTemplateId = selectedTemplateId.value;
        const hasCurrentTemplate = templateData.some(
            (template) => template.id === currentTemplateId,
        );
        const preferredTemplateId = resolvePreferredTemplateId(templateData);

        session.value = sessionData;
        templates.value = templateData;
        printers.value = printerData;
        renderedFileUrl.value =
            sessionData.active_rendered_output?.file_url ?? null;
        manualPaymentNotes.value = sessionData.manual_payment_notes ?? '';
        manualPaymentRejectReason.value = '';
        lastSyncedAt.value = new Date().toLocaleTimeString('id-ID');

        selectedTemplateId.value =
            (hasCurrentTemplate ? currentTemplateId : null) ??
            sessionData.latest_edit_job?.template?.id ??
            preferredTemplateId ??
            templateData[0]?.id ??
            null;

        copies.value =
            sessionData.latest_print_order?.total_qty ?? copies.value;
        syncPrinterDefaults(printerData);
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal memuat workspace session.',
        );
    } finally {
        if (showLoader) {
            loading.value = false;
        } else {
            refreshing.value = false;
        }
    }
}

/**
 * Refresh ringan yang dipakai operator saat ingin menyelaraskan tampilan
 * dengan perubahan backend tanpa keluar dari halaman.
 */
async function refreshWorkspace(): Promise<void> {
    await loadData(false);
    feedback.value = 'Workspace session berhasil diperbarui.';
}

async function refreshLatestPrintOrder(printOrderId: string): Promise<void> {
    const order = await get<PrintOrderDetail>(showPrintOrder(printOrderId));

    if (!session.value) {
        return;
    }

    session.value.latest_print_order = {
        id: order.id,
        order_code: order.order_code,
        status: order.status,
        total_qty: order.total_qty,
        total_amount: order.total_amount,
        ordered_at: order.ordered_at,
        printer: order.printer,
    };
}

async function createEditJobAndRender(): Promise<void> {
    if (!session.value || !selectedTemplate.value) {
        return;
    }

    if (!canCreateAndRender.value) {
        errorMessage.value =
            'Semua slot template harus terisi sebelum membuat render final.';

        return;
    }

    feedback.value = null;
    errorMessage.value = null;
    submitting.value = true;

    try {
        const items = slotAssignments.value.map((assignment) => ({
            session_photo_id: assignment.photo!.id,
            slot_index: assignment.slot_index,
            crop_json: assignment.crop,
            transform_json: assignment.transform,
        }));

        const editJobResponse = await post<{
            message?: string;
            edit_job_id: string;
            version_no: number;
        }>(storeEditJob(session.value.id), {
            template_id: selectedTemplate.value.id,
            items,
        });

        const renderResponse = await post<{
            message?: string;
            rendered_output_id?: string;
            file_url?: string | null;
            status?: string;
        }>(renderEditJob(editJobResponse.edit_job_id));

        renderedFileUrl.value = renderResponse.file_url ?? null;

        session.value.status = renderResponse.status ?? session.value.status;
        session.value.latest_edit_job = {
            id: editJobResponse.edit_job_id,
            version_no: editJobResponse.version_no,
            status: 'completed',
            template: {
                id: selectedTemplate.value.id,
                template_name: selectedTemplate.value.template_name,
            },
        };
        session.value.active_rendered_output = renderResponse.rendered_output_id
            ? {
                  id: renderResponse.rendered_output_id,
                  version_no: editJobResponse.version_no,
                  file_url: renderResponse.file_url ?? null,
              }
            : session.value.active_rendered_output;

        feedback.value =
            renderResponse.message ??
            'Edit job berhasil dibuat. Render final siap dilanjutkan ke print order.';
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal membuat edit job atau render.',
        );
    } finally {
        submitting.value = false;
    }
}

/**
 * Membuat print order baru dari rendered output aktif, atau memakai
 * order yang masih aktif bila backend mendeteksi order yang sama sudah ada.
 */
async function createPrintOrder(): Promise<void> {
    if (!session.value?.active_rendered_output) {
        errorMessage.value =
            'Render final belum tersedia untuk dibuatkan print order.';

        return;
    }

    feedback.value = null;
    errorMessage.value = null;
    creatingPrintOrder.value = true;

    try {
        const response = await post<{
            message?: string;
            print_order_id: string;
            order_code: string;
            status?: string;
            session_status?: string;
        }>(storePrintOrder(session.value.active_rendered_output.id), {
            printer_id: selectedPrinterId.value,
            copies: copies.value,
            paper_size: paperSize.value,
        });

        await refreshLatestPrintOrder(response.print_order_id);

        if (session.value) {
            session.value.status =
                response.session_status ?? session.value.status;
        }

        feedback.value =
            response.message ??
            `Print order ${response.order_code} siap. Lanjutkan kirim ke queue printer.`;
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal membuat print order.',
        );
    } finally {
        creatingPrintOrder.value = false;
    }
}

/**
 * Mengantrikan print order terbaru ke printer yang dipilih.
 * Setelah sukses, state session dan order langsung diselaraskan di frontend.
 */
async function queueLatestPrintOrder(): Promise<void> {
    if (!latestPrintOrder.value || !selectedPrinterId.value || !session.value) {
        errorMessage.value =
            'Pilih printer dan buat print order terlebih dulu.';

        return;
    }

    feedback.value = null;
    errorMessage.value = null;
    queueingPrintOrder.value = true;

    try {
        const response = await post<{
            message?: string;
            status?: string;
            order_status?: string;
            session_status?: string;
        }>(storeQueueJob(latestPrintOrder.value.id), {
            printer_id: selectedPrinterId.value,
            priority: 0,
        });

        if (session.value.latest_print_order) {
            session.value.latest_print_order.status =
                response.order_status ??
                session.value.latest_print_order.status;
            session.value.latest_print_order.printer = selectedPrinter.value
                ? {
                      id: selectedPrinter.value.id,
                      name: selectedPrinter.value.printer_name,
                  }
                : session.value.latest_print_order.printer;
        }

        session.value.status = response.session_status ?? session.value.status;

        feedback.value =
            response.message ??
            'Print order berhasil masuk queue. Pantau prosesnya dari halaman print queue.';
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal mengirim print order ke queue.',
        );
    } finally {
        queueingPrintOrder.value = false;
    }
}

watch(selectedTemplateId, () => {
    initializeAssignments();

    if (
        !selectedPrinter.value?.paper_size_default &&
        selectedTemplate.value?.paper_size
    ) {
        paperSize.value = selectedTemplate.value.paper_size;
    }
});

watch(selectedPrinterId, (printerId) => {
    const printer = printers.value.find((item) => item.id === printerId);

    if (printer?.paper_size_default) {
        paperSize.value = printer.paper_size_default;
    }
});

watch([smartFitEnabled, smartFitBias], ([enabled, bias]) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(SMART_FIT_ENABLED_KEY, String(enabled));
    window.localStorage.setItem(SMART_FIT_BIAS_KEY, String(bias));
});

onBeforeUnmount(() => {
    dragState.value = null;
});

onMounted(loadData);
</script>

<template>
    <AppLayout
        title="Session Detail"
        subtitle="Atur slot template, pilih foto, lalu render hasil akhir."
    >
        <div v-if="loading" class="text-sm text-[#6d6b77]">
            Loading session detail...
        </div>

        <div v-else-if="!session">
            <EmptyState
                title="Session tidak ditemukan"
                message="Periksa kembali data session yang dipilih."
            />
        </div>

        <div v-else class="grid gap-6 2xl:grid-cols-[1.6fr_1fr]">
            <div class="space-y-6">
                <div
                    class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <h2
                                    class="text-xl font-semibold text-[#2f2b3dcc]"
                                >
                                    Session #{{ session.id }}
                                </h2>
                                <StatusBadge
                                    :status="session.status ?? 'unknown'"
                                />
                            </div>

                            <div
                                class="grid gap-2 text-sm text-[#6d6b77] md:grid-cols-3"
                            >
                                <p>Device: {{ session.device_name ?? '-' }}</p>
                                <p>Code: {{ session.session_code }}</p>
                                <p>
                                    Station: {{ session.station_code ?? '-' }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <button
                                type="button"
                                class="w-full rounded-xl border border-[#e8e6ef] bg-white px-4 py-2 text-sm font-medium text-[#2f2b3dcc] transition hover:border-[#d8d4e7] hover:bg-[#f5f5f9] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="refreshing"
                                @click="refreshWorkspace"
                            >
                                {{
                                    refreshing
                                        ? 'Refreshing Workspace...'
                                        : 'Refresh Workspace'
                                }}
                            </button>

                            <div class="text-xs text-[#6d6b77]">
                                Synced: {{ lastSyncedAt ?? '-' }}
                            </div>

                            <div
                                class="grid grid-cols-3 gap-3 rounded-xl bg-[#f5f5f9] p-3 text-sm text-[#6d6b77]"
                            >
                                <div>
                                    <div
                                        class="text-xs tracking-wide text-[#b3b1bb] uppercase"
                                    >
                                        Slots Filled
                                    </div>
                                    <div
                                        class="mt-1 text-lg font-semibold text-[#2f2b3dcc]"
                                    >
                                        {{ filledSlotCount }}/{{
                                            slotAssignments.length
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-xs tracking-wide text-[#b3b1bb] uppercase"
                                    >
                                        Last Render
                                    </div>
                                    <div
                                        class="mt-1 text-lg font-semibold text-[#2f2b3dcc]"
                                    >
                                        {{ activeRenderVersionLabel }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-xs tracking-wide text-[#b3b1bb] uppercase"
                                    >
                                        Print Order
                                    </div>
                                    <div
                                        class="mt-1 text-sm font-semibold text-[#2f2b3dcc]"
                                    >
                                        {{
                                            latestPrintOrder?.order_code ?? '-'
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                >
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                                Manual Payment Approval
                            </h3>
                            <p class="text-sm text-[#6d6b77]">
                                Approve atau reject request pembayaran manual dari Android.
                            </p>
                        </div>

                        <div
                            class="grid grid-cols-2 gap-3 rounded-xl border border-[#e8e6ef] bg-[#f5f5f9] p-3 text-sm"
                        >
                            <div>
                                <div class="text-xs uppercase text-[#6d6b77]">Session</div>
                                <div class="font-semibold text-[#2f2b3dcc]">
                                    {{ session.session_code }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-[#6d6b77]">Customer ID (WA)</div>
                                <div class="font-semibold text-[#2f2b3dcc]">
                                    {{ session.customer_whatsapp ?? '-' }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-[#6d6b77]">Payment Status</div>
                                <div class="mt-1">
                                    <StatusBadge :status="session.payment_status ?? 'pending'" />
                                </div>
                            </div>
                            <div>
                                <div class="text-xs uppercase text-[#6d6b77]">Manual Status</div>
                                <div class="mt-1">
                                    <StatusBadge :status="manualPaymentStatusLabel" />
                                </div>
                            </div>
                        </div>

                        <div v-if="!isManualPaymentSession" class="rounded-xl border border-[#e8e6ef] bg-[#f5f5f9] p-3 text-sm text-[#6d6b77]">
                            Session ini belum request pembayaran manual dari Android.
                        </div>

                        <template v-else>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="space-y-2">
                                    <span class="text-sm font-medium text-[#2f2b3dcc]">
                                        Catatan Approve (opsional)
                                    </span>
                                    <textarea
                                        v-model="manualPaymentNotes"
                                        rows="2"
                                        class="w-full rounded-xl border border-[#d8d4e7] px-3 py-2.5 text-sm"
                                        placeholder="Contoh: Sudah terima pembayaran tunai"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-sm font-medium text-[#2f2b3dcc]">
                                        Alasan Reject
                                    </span>
                                    <textarea
                                        v-model="manualPaymentRejectReason"
                                        rows="2"
                                        class="w-full rounded-xl border border-[#d8d4e7] px-3 py-2.5 text-sm"
                                        placeholder="Wajib diisi jika reject"
                                    />
                                </label>
                            </div>

                            <div class="grid gap-2 sm:grid-cols-2">
                                <button
                                    type="button"
                                    class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canApproveManualPayment"
                                    @click="approveManualPayment"
                                >
                                    {{
                                        manualPaymentSubmittingAction === 'approve'
                                            ? 'Approving...'
                                            : 'Approve Manual Payment'
                                    }}
                                </button>

                                <button
                                    type="button"
                                    class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canRejectManualPayment"
                                    @click="rejectManualPayment"
                                >
                                    {{
                                        manualPaymentSubmittingAction === 'reject'
                                            ? 'Rejecting...'
                                            : 'Reject Manual Payment'
                                    }}
                                </button>
                            </div>

                            <div
                                v-if="
                                    session.manual_payment_reviewed_at ||
                                    session.manual_payment_reviewer_name ||
                                    session.manual_payment_notes
                                "
                                class="rounded-xl border border-[#e8e6ef] bg-[#f5f5f9] p-3 text-sm text-[#6d6b77]"
                            >
                                <div>
                                    Reviewed:
                                    {{
                                        session.manual_payment_reviewed_at ?? '-'
                                    }}
                                </div>
                                <div>
                                    Reviewer:
                                    {{
                                        session.manual_payment_reviewer_name ?? '-'
                                    }}
                                </div>
                                <div>
                                    Notes:
                                    {{ session.manual_payment_notes ?? '-' }}
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                >
                    <div
                        class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div>
                            <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                                Photo Library
                            </h3>
                            <p class="text-sm text-[#6d6b77]">
                                Pilih slot aktif di kanan, lalu klik foto untuk
                                assign atau swap.
                            </p>
                        </div>

                        <div
                            class="rounded-full bg-[#edeafd] px-3 py-2 text-sm font-medium text-[#685dd8]"
                        >
                            {{
                                selectedSlotIndex
                                    ? `Slot aktif: ${selectedSlotIndex}`
                                    : 'Pilih slot terlebih dulu'
                            }}
                        </div>

                        <div
                            class="rounded-full bg-[#f1f0f5] px-3 py-2 text-sm font-medium text-[#6d6b77]"
                        >
                            {{ session.photos.length }} foto
                        </div>
                    </div>

                    <div v-if="!session.photos.length">
                        <EmptyState
                            title="Belum ada foto"
                            message="Foto session akan muncul di sini."
                        />
                    </div>

                    <div
                        v-else
                        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <button
                            v-for="photo in session.photos"
                            :key="photo.id"
                            type="button"
                            class="group overflow-hidden rounded-2xl border bg-white text-left shadow-[0_2px_10px_rgba(47,43,61,0.06)] transition hover:-translate-y-0.5 hover:shadow-md"
                            :class="
                                assignedSlotsByPhotoId.has(photo.id)
                                    ? 'border-[#9f96f5] ring-2 ring-[#d9d4fb]'
                                    : 'border-[#e8e6ef]'
                            "
                            @click="handlePhotoClick(photo)"
                        >
                            <div class="relative">
                                <img
                                    :src="getPhotoUrl(photo)"
                                    :alt="`Photo ${photo.capture_index}`"
                                    class="h-52 w-full object-cover"
                                />

                                <div
                                    class="absolute top-3 left-3 rounded-full bg-[#7367f0]/80 px-2.5 py-1 text-xs font-medium text-white"
                                >
                                    Photo #{{ photo.capture_index }}
                                </div>

                                <div
                                    v-if="assignedSlotsByPhotoId.has(photo.id)"
                                    class="absolute bottom-3 left-3 rounded-full bg-[#7367f0] px-2.5 py-1 text-xs font-medium text-white"
                                >
                                    Slots
                                    {{
                                        assignedSlotsByPhotoId
                                            .get(photo.id)
                                            ?.join(', ')
                                    }}
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between gap-3 p-3"
                            >
                                <div>
                                    <p
                                        class="text-sm font-medium text-[#2f2b3dcc]"
                                    >
                                        Assign ke slot aktif
                                    </p>
                                    <p class="text-xs text-[#6d6b77]">
                                        Klik ulang di slot lain untuk swap cepat
                                    </p>
                                </div>

                                <span
                                    class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77] transition group-hover:bg-[#edeafd] group-hover:text-[#685dd8]"
                                >
                                    Use
                                </span>
                            </div>
                        </button>
                    </div>
                </div>

                <div
                    v-if="renderedFileUrl"
                    class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                >
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                            Rendered Output
                        </h3>

                        <div class="text-sm text-[#6d6b77]">
                            Preview hasil final terbaru
                        </div>
                    </div>

                    <img
                        :src="renderedFileUrl"
                        alt="rendered output"
                        class="w-full rounded-xl border border-[#e8e6ef] object-cover"
                    />
                </div>
            </div>

            <div class="space-y-6">
                <div
                    class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                                Workflow Tracker
                            </h3>
                            <p class="text-sm text-[#6d6b77]">
                                Ringkasan cepat progress session dari capture
                                sampai queue printer.
                            </p>
                        </div>

                        <div
                            class="rounded-full bg-[#f1f0f5] px-3 py-1 text-xs font-medium text-[#6d6b77]"
                        >
                            {{ session.status ?? 'unknown' }}
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="step in workflowSteps"
                            :key="step.key"
                            class="rounded-2xl border p-4"
                            :class="
                                step.state === 'completed'
                                    ? 'border-emerald-200 bg-emerald-50/70'
                                    : step.state === 'ready'
                                      ? 'border-[#d9d4fb] bg-[#edeafd]/80'
                                      : step.state === 'active'
                                        ? 'border-amber-200 bg-amber-50/80'
                                        : 'border-[#e8e6ef] bg-[#f5f5f9]'
                            "
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-medium text-[#2f2b3dcc]">
                                    {{ step.title }}
                                </div>
                                <div
                                    class="rounded-full px-2.5 py-1 text-[11px] font-medium uppercase tracking-wide"
                                    :class="
                                        step.state === 'completed'
                                            ? 'bg-[#e8f7ef] text-[#28c76f]'
                                            : step.state === 'ready'
                                              ? 'bg-[#edeafd] text-[#685dd8]'
                                              : step.state === 'active'
                                                ? 'bg-[#fff1e3] text-[#ff9f43]'
                                                : 'bg-[#e6e3f3] text-[#6d6b77]'
                                    "
                                >
                                    {{ step.state }}
                                </div>
                            </div>

                            <p class="mt-2 text-sm text-[#6d6b77]">
                                {{ step.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                >
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                                Voucher / Skip
                            </h3>
                            <p class="text-sm text-[#6d6b77]">
                                Terapkan voucher atau alasan skip pada session
                                Android.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-2">
                                <span
                                    class="text-sm font-medium text-[#2f2b3dcc]"
                                >
                                    Voucher Code
                                </span>
                                <input
                                    v-model="voucherCode"
                                    type="text"
                                    class="w-full rounded-xl border border-[#d8d4e7] px-3 py-2.5 text-sm"
                                    placeholder="VCHR-XXXX"
                                />
                            </label>

                            <label class="space-y-2">
                                <span
                                    class="text-sm font-medium text-[#2f2b3dcc]"
                                >
                                    Voucher Type
                                </span>
                                <select
                                    v-model="voucherType"
                                    class="w-full rounded-xl border border-[#d8d4e7] px-3 py-2.5 text-sm"
                                >
                                    <option
                                        v-for="option in voucherTypeOptions"
                                        :key="option.key"
                                        :value="option.key"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <label class="space-y-2">
                            <span class="text-sm font-medium text-[#2f2b3dcc]">
                                Catatan
                            </span>
                            <textarea
                                v-model="voucherNotes"
                                rows="2"
                                class="w-full rounded-xl border border-[#d8d4e7] px-3 py-2.5 text-sm"
                                placeholder="Tambahkan alasan / catatan"
                            />
                        </label>

                        <button
                            type="button"
                            class="w-full rounded-xl bg-[#7367f0] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#685dd8] disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="voucherSubmitting"
                            @click="applyVoucher"
                        >
                            {{
                                voucherSubmitting
                                    ? 'Menerapkan...'
                                    : 'Apply Voucher'
                            }}
                        </button>

                        <div
                            class="grid grid-cols-3 gap-2 rounded-2xl border border-[#e8e6ef] bg-[#f5f5f9] p-3 text-center"
                        >
                            <div>
                                <div class="text-[11px] uppercase text-[#6d6b77]">
                                    Total
                                </div>
                                <div class="mt-1 text-sm font-semibold text-[#2f2b3dcc]">
                                    {{ voucherSummary.total }}
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] uppercase text-[#6d6b77]">
                                    Applied
                                </div>
                                <div class="mt-1 text-sm font-semibold text-[#2f2b3dcc]">
                                    {{ voucherSummary.applied }}
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] uppercase text-[#6d6b77]">
                                    Revoked
                                </div>
                                <div class="mt-1 text-sm font-semibold text-[#2f2b3dcc]">
                                    {{ voucherSummary.revoked }}
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    voucherFilter === 'all'
                                        ? 'bg-[#7367f0] text-white'
                                        : 'bg-[#f1f0f5] text-[#6d6b77] hover:bg-[#e6e3f3]'
                                "
                                @click="voucherFilter = 'all'"
                            >
                                All
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    voucherFilter === 'applied'
                                        ? 'bg-[#7367f0] text-white'
                                        : 'bg-[#f1f0f5] text-[#6d6b77] hover:bg-[#e6e3f3]'
                                "
                                @click="voucherFilter = 'applied'"
                            >
                                Applied
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    voucherFilter === 'revoked'
                                        ? 'bg-[#7367f0] text-white'
                                        : 'bg-[#f1f0f5] text-[#6d6b77] hover:bg-[#e6e3f3]'
                                "
                                @click="voucherFilter = 'revoked'"
                            >
                                Revoked
                            </button>
                        </div>

                        <div v-if="!filteredSessionVouchers.length">
                            <EmptyState
                                title="Belum ada voucher"
                                message="Voucher yang diterapkan akan muncul di sini."
                            />
                        </div>

                        <div v-else class="flex flex-col gap-3">
                            <div
                                v-for="voucher in filteredSessionVouchers"
                                :key="voucher.id"
                                class="rounded-2xl border border-[#e8e6ef] p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-semibold text-[#2f2b3dcc]">
                                            {{ voucher.voucher_code }}
                                        </div>
                                        <div class="text-xs text-[#6d6b77]">
                                            {{ voucher.voucher_type ?? '-' }}
                                            - {{ voucher.applied_at ?? '-' }}
                                        </div>
                                        <div
                                            v-if="voucher.notes"
                                            class="mt-1 text-xs text-[#6d6b77]"
                                        >
                                            {{ voucher.notes }}
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end gap-2">
                                        <StatusBadge
                                            :status="voucher.status ?? 'applied'"
                                        />
                                        <button
                                            v-if="
                                                (voucher.status ?? '') ===
                                                'applied'
                                            "
                                            type="button"
                                            class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77] transition hover:bg-[#e6e3f3]"
                                            :disabled="
                                                voucherRevokingId ===
                                                voucher.id
                                            "
                                            @click="revokeVoucher(voucher.id)"
                                        >
                                            {{
                                                voucherRevokingId ===
                                                voucher.id
                                                    ? 'Revoking...'
                                                    : 'Revoke'
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                >
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                                Template & Slot Editor
                            </h3>
                            <p class="text-sm text-[#6d6b77]">
                                Pilih template, lalu atur foto yang masuk ke
                                tiap slot.
                            </p>
                        </div>

                        <div
                            v-if="feedback"
                            class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"
                        >
                            {{ feedback }}
                        </div>

                        <div
                            v-if="errorMessage"
                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                        >
                            {{ errorMessage }}
                        </div>

                        <div class="space-y-3">
                            <label
                                v-for="template in templates"
                                :key="template.id"
                                class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                :class="
                                    selectedTemplateId === template.id
                                        ? 'border-[#9f96f5] bg-[#edeafd]/80'
                                        : 'border-[#e8e6ef] hover:border-[#d8d4e7]'
                                "
                            >
                                <input
                                    v-model="selectedTemplateId"
                                    :value="template.id"
                                    type="radio"
                                    class="mt-1"
                                />

                                <div class="min-w-0">
                                    <div class="font-medium text-[#2f2b3dcc]">
                                        {{ template.template_name }}
                                    </div>
                                    <div class="mt-1 text-xs text-[#6d6b77]">
                                        {{ template.canvas_width ?? '-' }} x
                                        {{ template.canvas_height ?? '-' }}
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-2 text-xs text-[#6d6b77]"
                                    >
                                        <span>{{ template.slots.length }} slot</span>
                                        <span>-</span>
                                        <span>{{ template.paper_size ?? '-' }}</span>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <div
                            v-if="selectedTemplate"
                            class="space-y-4 rounded-2xl border border-[#e8e6ef] bg-[#f5f5f9] p-4"
                        >
                            <div
                                class="overflow-hidden rounded-2xl border border-[#e8e6ef] bg-white"
                            >
                                <div
                                    class="flex items-center justify-between gap-3 border-b border-[#e8e6ef] px-4 py-3"
                                >
                                    <div>
                                        <div class="font-medium text-[#2f2b3dcc]">
                                            Canvas Preview
                                        </div>
                                        <p class="text-xs text-[#6d6b77]">
                                            Klik slot di preview untuk memilih
                                            area kerja yang aktif.
                                        </p>
                                        <p class="mt-1 text-xs text-[#7367f0]">
                                            Drag foto langsung di slot untuk
                                            custom letak foto.
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77]"
                                    >
                                        {{ selectedTemplate.paper_size ?? '-' }}
                                    </div>
                                </div>

                                <div class="p-4">
                                    <div
                                        class="relative overflow-hidden rounded-2xl border border-dashed border-[#d8d4e7] bg-[#f1f0f5]"
                                        :style="{
                                            aspectRatio: `${selectedTemplate.canvas_width ?? 4} / ${selectedTemplate.canvas_height ?? 3}`,
                                        }"
                                    >
                                        <img
                                            v-if="selectedTemplate.preview_url"
                                            :src="selectedTemplate.preview_url"
                                            :alt="selectedTemplate.template_name"
                                            class="absolute inset-0 h-full w-full object-cover opacity-15"
                                        />

                                        <button
                                            v-for="slot in sortedTemplateSlots"
                                            :key="slot.slot_index"
                                            type="button"
                                            class="absolute touch-none overflow-hidden border-2 transition"
                                            :class="
                                                selectedSlotIndex ===
                                                slot.slot_index
                                                    ? isDraggingSlot(slot.slot_index)
                                                        ? 'z-10 cursor-grabbing border-[#7367f0] ring-2 ring-[#d9d4fb]'
                                                        : 'z-10 cursor-grab border-[#7367f0] ring-2 ring-[#d9d4fb]'
                                                    : assignmentBySlotIndex.get(slot.slot_index)?.photo
                                                      ? 'cursor-grab border-[#d8d4e7] hover:border-[#9f96f5]'
                                                      : 'cursor-pointer border-[#d8d4e7] hover:border-[#9f96f5]'
                                            "
                                            :style="getTemplateSlotStyle(slot)"
                                            @click="setSelectedSlot(slot.slot_index)"
                                            @pointerdown="
                                                startSlotPhotoDrag(
                                                    slot.slot_index,
                                                    $event,
                                                )
                                            "
                                            @pointermove="
                                                continueSlotPhotoDrag($event)
                                            "
                                            @pointerup="
                                                stopSlotPhotoDrag($event)
                                            "
                                            @pointercancel="
                                                stopSlotPhotoDrag($event)
                                            "
                                        >
                                            <img
                                                v-if="
                                                    assignmentBySlotIndex.get(
                                                        slot.slot_index,
                                                    )?.photo
                                                "
                                                :src="
                                                    getPhotoUrl(
                                                        assignmentBySlotIndex.get(
                                                            slot.slot_index,
                                                        )?.photo,
                                                    )
                                                "
                                                :alt="`Slot ${slot.slot_index}`"
                                                class="h-full w-full object-cover transition"
                                                :style="
                                                    getSlotPreviewImageStyle(
                                                        slot.slot_index,
                                                    )
                                                "
                                            />

                                            <div
                                                v-else
                                                class="flex h-full w-full items-center justify-center bg-white/70 text-[11px] font-semibold text-[#6d6b77]"
                                            >
                                                Empty
                                            </div>

                                            <div
                                                class="absolute top-2 left-2 rounded-full bg-[#7367f0]/80 px-2 py-0.5 text-[10px] font-medium text-white"
                                            >
                                                {{ slot.slot_index }}
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <label class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d8d4e7]">
                                    <input
                                        v-model="smartFitEnabled"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-[#d8d4e7]"
                                    />
                                    Smart Fit
                                </label>
                                <div class="flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d8d4e7]">
                                    <span>Bias</span>
                                    <input
                                        v-model.number="smartFitBias"
                                        type="range"
                                        min="-30"
                                        max="0"
                                        step="1"
                                        class="w-24"
                                    />
                                    <span class="w-8 text-right">{{ smartFitBias }}</span>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d8d4e7] transition hover:bg-[#f1f0f5]"
                                    @click="autoFillOpenSlots"
                                >
                                    Auto Fill Empty
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d8d4e7] transition hover:bg-[#f1f0f5]"
                                    @click="applySmartFitAll"
                                >
                                    Smart Fit All
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d8d4e7] transition hover:bg-[#f1f0f5]"
                                    @click="resetAssignments"
                                >
                                    Reset Default
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d8d4e7] transition hover:bg-[#f1f0f5]"
                                    @click="clearAllSlots"
                                >
                                    Clear All
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="assignment in slotAssignments"
                                    :key="assignment.slot_index"
                                    class="w-full rounded-2xl border p-3 text-left transition"
                                    :class="
                                        selectedSlotIndex ===
                                        assignment.slot_index
                                            ? 'border-[#9f96f5] bg-white ring-2 ring-[#d9d4fb]'
                                            : 'border-[#e8e6ef] bg-white hover:border-[#d8d4e7]'
                                    "
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                class="flex items-center gap-2 text-sm font-semibold text-[#2f2b3dcc]"
                                            >
                                                <span>
                                                    Slot
                                                    {{ assignment.slot_index }}
                                                </span>
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                                    :class="
                                                        assignment.photo
                                                            ? 'bg-[#e8f7ef] text-[#28c76f]'
                                                            : 'bg-[#fff1e3] text-[#ff9f43]'
                                                    "
                                                >
                                                    {{
                                                        assignment.photo
                                                            ? 'Assigned'
                                                            : 'Empty'
                                                    }}
                                                </span>
                                            </div>
                                            <p
                                                class="mt-1 text-xs text-[#6d6b77]"
                                            >
                                                {{
                                                    assignment.photo
                                                        ? `Photo #${assignment.photo.capture_index}`
                                                        : 'Klik slot ini, lalu pilih foto di kiri.'
                                                }}
                                            </p>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#edeafd] px-2.5 py-1 text-xs font-medium text-[#685dd8] transition hover:bg-[#d9d4fb]"
                                                @click="
                                                    setSelectedSlot(
                                                        assignment.slot_index,
                                                    )
                                                "
                                            >
                                                Select
                                            </button>

                                            <button
                                                v-if="assignment.photo"
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77] transition hover:bg-[#e6e3f3]"
                                                @click="
                                                    duplicatePhotoToNextSlot(
                                                        assignment.photo,
                                                        assignment.slot_index,
                                                    )
                                                "
                                            >
                                                Duplicate Next
                                            </button>

                                            <button
                                                v-if="assignment.photo"
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-2.5 py-1 text-xs font-medium text-[#6d6b77] transition hover:bg-[#e6e3f3]"
                                                @click="
                                                    clearSlot(
                                                        assignment.slot_index,
                                                    )
                                                "
                                            >
                                                Clear
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-if="assignment.photo"
                                        class="mt-3 overflow-hidden rounded-xl border border-[#e8e6ef]"
                                    >
                                        <img
                                            :src="getPhotoUrl(assignment.photo)"
                                            :alt="`Slot ${assignment.slot_index}`"
                                            class="h-28 w-full object-cover transition"
                                            :style="
                                                getAssignmentImageStyle(
                                                    assignment,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="selectedSlotAssignment"
                            class="rounded-2xl border border-[#d9d4fb] bg-[#edeafd]/80 p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div
                                        class="text-sm font-semibold text-[#2f2b3dcc]"
                                    >
                                        Slot aktif
                                        {{ selectedSlotAssignment.slot_index }}
                                    </div>
                                    <p class="text-xs text-[#685dd8]">
                                        {{
                                            selectedSlotAssignment.photo
                                                ? `Terisi Photo #${selectedSlotAssignment.photo.capture_index}`
                                                : 'Belum ada foto terpasang'
                                        }}
                                    </p>
                                    <p
                                        v-if="selectedTemplateSlot"
                                        class="mt-1 text-xs text-[#685dd8]/80"
                                    >
                                        Area slot:
                                        {{ selectedTemplateSlot.width }} x
                                        {{ selectedTemplateSlot.height }}
                                    </p>
                                </div>

                                <button
                                    v-if="selectedSlotAssignment.photo"
                                    type="button"
                                    class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                    @click="
                                        clearSlot(
                                            selectedSlotAssignment.slot_index,
                                        )
                                    "
                                >
                                    Clear Slot
                                </button>

                                <button
                                    v-if="selectedSlotAssignment.photo"
                                    type="button"
                                    class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                    @click="
                                        duplicatePhotoToNextSlot(
                                            selectedSlotAssignment.photo,
                                            selectedSlotAssignment.slot_index,
                                        )
                                    "
                                >
                                    Duplicate Next
                                </button>
                            </div>

                            <div
                                v-if="selectedSlotAssignment.photo"
                                class="mt-4 space-y-4"
                            >
                                <div
                                    class="overflow-hidden rounded-2xl border border-[#d9d4fb] bg-white"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3 border-b border-[#edeafd] px-4 py-3"
                                    >
                                        <div>
                                            <div
                                                class="text-sm font-medium text-[#2f2b3dcc]"
                                            >
                                                Framing Preview
                                            </div>
                                            <p class="text-xs text-[#6d6b77]">
                                                Preview non-destruktif untuk
                                                posisi foto di slot aktif.
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-full bg-[#edeafd] px-2.5 py-1 text-xs font-medium text-[#685dd8]"
                                        >
                                            Photo
                                            #{{ selectedSlotAssignment.photo.capture_index }}
                                            - Zoom {{ selectedZoomLabel }}
                                        </div>
                                    </div>

                                    <div class="p-4">
                                        <div
                                            class="overflow-hidden rounded-2xl border border-[#e8e6ef] bg-[#f1f0f5]"
                                            :style="{
                                                aspectRatio: selectedTemplateSlot
                                                    ? `${selectedTemplateSlot.width} / ${selectedTemplateSlot.height}`
                                                    : '4 / 3',
                                            }"
                                        >
                                            <img
                                                :src="
                                                    getPhotoUrl(
                                                        selectedSlotAssignment.photo,
                                                    )
                                                "
                                                :alt="`Selected slot ${selectedSlotAssignment.slot_index}`"
                                                class="h-full w-full object-cover transition"
                                                :style="selectedSlotPhotoStyle"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div
                                        class="rounded-2xl border border-[#edeafd] bg-white p-4"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div
                                                class="text-sm font-medium text-[#2f2b3dcc]"
                                            >
                                                Zoom
                                            </div>
                                            <div class="text-xs text-[#6d6b77]">
                                                {{
                                                    Math.round(
                                                        selectedSlotAssignment.crop
                                                            .zoom * 100,
                                                    )
                                                }}%
                                            </div>
                                        </div>

                                        <input
                                            :value="
                                                selectedSlotAssignment.crop.zoom
                                            "
                                            type="range"
                                            min="1"
                                            max="3"
                                            step="0.05"
                                            class="mt-3 w-full"
                                            @input="
                                                updateSelectedAssignmentCrop({
                                                    zoom: Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ),
                                                })
                                            "
                                        />

                                        <div
                                            class="mt-3 flex items-center gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] transition hover:bg-[#e6e3f3]"
                                                @click="stepSelectedZoom(-0.1)"
                                            >
                                                Zoom -
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] transition hover:bg-[#e6e3f3]"
                                                @click="stepSelectedZoom(0.1)"
                                            >
                                                Zoom +
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-2xl border border-[#edeafd] bg-white p-4"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div
                                                class="text-sm font-medium text-[#2f2b3dcc]"
                                            >
                                                Rotation
                                            </div>
                                            <div class="text-xs text-[#6d6b77]">
                                                {{
                                                    selectedSlotAssignment
                                                        .transform.rotation
                                                }}deg
                                            </div>
                                        </div>

                                        <div
                                            class="mt-3 flex flex-wrap items-center gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] transition hover:bg-[#e6e3f3]"
                                                @click="
                                                    rotateSelectedPhoto(-90)
                                                "
                                            >
                                                Rotate Left
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] transition hover:bg-[#e6e3f3]"
                                                @click="
                                                    rotateSelectedPhoto(90)
                                                "
                                            >
                                                Rotate Right
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] transition hover:bg-[#e6e3f3]"
                                                @click="
                                                    updateSelectedAssignmentTransform({
                                                        rotation: 0,
                                                    })
                                                "
                                            >
                                                Reset Rotation
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div
                                        class="rounded-2xl border border-[#edeafd] bg-white p-4"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div
                                                class="text-sm font-medium text-[#2f2b3dcc]"
                                            >
                                                Letak Foto X
                                            </div>
                                            <div class="text-xs text-[#6d6b77]">
                                                {{
                                                    selectedSlotAssignment.crop
                                                        .offset_x
                                                }}
                                            </div>
                                        </div>

                                        <input
                                            :value="
                                                selectedSlotAssignment.crop
                                                    .offset_x
                                            "
                                            type="range"
                                            min="-100"
                                            max="100"
                                            step="1"
                                            class="mt-3 w-full"
                                            @input="
                                                updateSelectedAssignmentCrop({
                                                    offset_x: Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ),
                                                })
                                            "
                                        />

                                        <div
                                            class="mt-3 flex items-center gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] transition hover:bg-[#e6e3f3]"
                                                @click="nudgeSelectedOffset('x', -10)"
                                            >
                                                Left
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] transition hover:bg-[#e6e3f3]"
                                                @click="nudgeSelectedOffset('x', 10)"
                                            >
                                                Right
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-2xl border border-[#edeafd] bg-white p-4"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div
                                                class="text-sm font-medium text-[#2f2b3dcc]"
                                            >
                                                Letak Foto Y
                                            </div>
                                            <div class="text-xs text-[#6d6b77]">
                                                {{
                                                    selectedSlotAssignment.crop
                                                        .offset_y
                                                }}
                                            </div>
                                        </div>

                                        <input
                                            :value="
                                                selectedSlotAssignment.crop
                                                    .offset_y
                                            "
                                            type="range"
                                            min="-100"
                                            max="100"
                                            step="1"
                                            class="mt-3 w-full"
                                            @input="
                                                updateSelectedAssignmentCrop({
                                                    offset_y: Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ),
                                                })
                                            "
                                        />

                                        <div
                                            class="mt-3 flex items-center gap-2"
                                        >
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] transition hover:bg-[#e6e3f3]"
                                                @click="nudgeSelectedOffset('y', -10)"
                                            >
                                                Up
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-full bg-[#f1f0f5] px-3 py-1.5 text-xs font-medium text-[#2f2b3dcc] transition hover:bg-[#e6e3f3]"
                                                @click="nudgeSelectedOffset('y', 10)"
                                            >
                                                Down
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <button
                                        type="button"
                                        class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                        @click="
                                            applySelectedFramingPreset(
                                                'center',
                                            )
                                        "
                                    >
                                        Center
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                        @click="
                                            applySelectedFramingPreset(
                                                'close-up',
                                            )
                                        "
                                    >
                                        Close-up
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                        @click="
                                            applySelectedFramingPreset(
                                                'show-more',
                                            )
                                        "
                                    >
                                        Show More
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                        @click="
                                            applySelectedFramingPreset('left')
                                        "
                                    >
                                        Focus Left
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                        @click="
                                            applySelectedFramingPreset('right')
                                        "
                                    >
                                        Focus Right
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                        @click="resetSelectedAdjustment"
                                    >
                                        Reset Framing
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-[#e8e6ef] bg-[#f5f5f9] p-4 text-sm text-[#6d6b77]"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-medium text-[#2f2b3dcc]">
                                    Render Status
                                </div>
                                <StatusBadge
                                    :status="
                                        latestEditJob?.status ??
                                        (hasRenderedOutput
                                            ? 'completed'
                                            : 'pending')
                                    "
                                />
                            </div>

                            <div class="mt-2 text-xs text-[#6d6b77]">
                                Edit Job:
                                {{
                                    latestEditJob?.version_no
                                        ? `v${latestEditJob.version_no}`
                                        : '-'
                                }}
                                -
                                {{
                                    latestEditJob?.template?.template_name ??
                                    'Template belum dipilih'
                                }}
                            </div>
                            <div class="mt-1 text-xs text-[#6d6b77]">
                                Active Render: {{ activeRenderVersionLabel }}
                            </div>
                            <div class="mt-1 text-xs text-[#6d6b77]">
                                Slot terisi: {{ filledSlotCount }}/{{
                                    slotAssignments.length
                                }}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="w-full rounded-xl bg-[#7367f0] px-4 py-3 font-medium text-white transition hover:bg-[#685dd8] disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!canCreateAndRender || submitting"
                            @click="createEditJobAndRender"
                        >
                            {{ renderActionLabel }}
                        </button>

                        <p class="text-xs leading-5 text-[#6d6b77]">
                            {{ renderHelperText }}
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
                >
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2f2b3dcc]">
                                Print Production
                            </h3>
                            <p class="text-sm text-[#6d6b77]">
                                Setelah render final siap, buat print order lalu
                                kirim ke printer queue.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-2">
                                <span
                                    class="text-sm font-medium text-[#2f2b3dcc]"
                                >
                                    Printer
                                </span>
                                <select
                                    v-model="selectedPrinterId"
                                    class="w-full rounded-xl border border-[#d8d4e7] px-3 py-2.5 text-sm"
                                >
                                    <option
                                        v-for="printer in printers"
                                        :key="printer.id"
                                        :value="printer.id"
                                    >
                                        {{ printer.printer_name }} -
                                        {{
                                            printer.is_online
                                                ? 'Online'
                                                : 'Offline'
                                        }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-2">
                                <span
                                    class="text-sm font-medium text-[#2f2b3dcc]"
                                >
                                    Copies
                                </span>
                                <input
                                    v-model.number="copies"
                                    type="number"
                                    min="1"
                                    class="w-full rounded-xl border border-[#d8d4e7] px-3 py-2.5 text-sm"
                                />
                            </label>
                        </div>

                        <label class="space-y-2">
                            <span class="text-sm font-medium text-[#2f2b3dcc]">
                                Paper Size
                            </span>
                            <input
                                v-model="paperSize"
                                type="text"
                                class="w-full rounded-xl border border-[#d8d4e7] px-3 py-2.5 text-sm"
                                placeholder="4R"
                            />
                        </label>

                        <div
                            v-if="selectedPrinter"
                            class="rounded-2xl border border-[#e8e6ef] bg-[#f5f5f9] p-4 text-sm text-[#6d6b77]"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div class="font-medium text-[#2f2b3dcc]">
                                        {{ selectedPrinter.printer_name }}
                                    </div>
                                    <div class="mt-1 flex items-center gap-2">
                                        <StatusBadge
                                            :status="
                                                selectedPrinter.is_online
                                                    ? 'online'
                                                    : (selectedPrinter.status ??
                                                      'offline')
                                            "
                                        />
                                        <span>
                                            Pending queue:
                                            {{
                                                selectedPrinter.queue
                                                    ?.pending ?? 0
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div class="text-right text-xs text-[#6d6b77]">
                                    Default paper:
                                    {{
                                        selectedPrinter.paper_size_default ??
                                        '-'
                                    }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="latestPrintOrder"
                            class="rounded-2xl border border-[#d9d4fb] bg-[#edeafd]/80 p-4"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <div
                                        class="text-sm font-semibold text-[#2f2b3dcc]"
                                    >
                                        Latest Print Order
                                    </div>
                                    <p class="mt-1 text-sm text-[#6d6b77]">
                                        {{ latestPrintOrder.order_code }}
                                    </p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <StatusBadge
                                            :status="
                                                latestPrintOrder.status ??
                                                'unknown'
                                            "
                                        />
                                        <span class="text-xs text-[#685dd8]">
                                            {{
                                                latestPrintOrder.printer
                                                    ?.name ??
                                                'Printer belum ditentukan'
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <Link
                                        :href="
                                            printOrderRoutes.show.url(
                                                latestPrintOrder.id,
                                            )
                                        "
                                        class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                    >
                                        Open Order
                                    </Link>
                                    <Link
                                        :href="printQueueRoutes.index.url()"
                                        class="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#685dd8] shadow-[0_2px_10px_rgba(47,43,61,0.06)] ring-1 ring-[#d9d4fb] transition hover:bg-[#d9d4fb]"
                                    >
                                        Open Queue
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                class="rounded-xl bg-[#7367f0] px-4 py-3 font-medium text-white transition hover:bg-[#685dd8] disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!canCreatePrintOrder"
                                @click="createPrintOrder"
                            >
                                {{ printOrderActionLabel }}
                            </button>

                            <button
                                type="button"
                                class="rounded-xl bg-[#7367f0] px-4 py-3 font-medium text-white transition hover:bg-[#685dd8] disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!canQueuePrintOrder"
                                @click="queueLatestPrintOrder"
                            >
                                {{
                                    queueingPrintOrder
                                        ? 'Queueing...'
                                        : 'Queue To Printer'
                                }}
                            </button>
                        </div>

                        <p class="text-xs leading-5 text-[#6d6b77]">
                            {{ printOrderHelperText }}
                        </p>

                        <p class="text-xs leading-5 text-[#6d6b77]">
                            {{ queueHelperText }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>




