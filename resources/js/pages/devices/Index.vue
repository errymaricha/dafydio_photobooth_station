<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';

import {
    index as listDevices,
    store as storeDevice,
} from '@/actions/App/Http/Controllers/Api/Editor/DeviceController';
import AppLayout from '@/components/layout/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useApi } from '@/composables/useApi';

type StationOption = {
    id: string;
    station_code: string;
    station_name: string;
};

type Device = {
    id: string;
    device_code: string;
    device_name: string;
    device_type?: string | null;
    local_ip?: string | null;
    app_version?: string | null;
    os_name?: string | null;
    os_version?: string | null;
    battery_percent?: number | null;
    capabilities?: Record<string, boolean>;
    status?: string | null;
    is_online?: boolean;
    last_heartbeat_at?: string | null;
    last_sync_at?: string | null;
    sessions_count?: number;
    station?: {
        id?: string | null;
        station_code?: string | null;
        station_name?: string | null;
    };
};

type DevicesResponse = {
    data: Device[];
    stations: StationOption[];
};

type CreatedDeviceResponse = {
    message: string;
    device: Device & {
        api_key?: string;
        api_key_revealed_once?: boolean;
    };
};

const { get, post } = useApi();

const devices = ref<Device[]>([]);
const stations = ref<StationOption[]>([]);
const loading = ref(true);
const saving = ref(false);
const feedback = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const lastCreatedSecret = ref<{
    device_code: string;
    api_key: string;
} | null>(null);

const form = reactive({
    station_id: '',
    device_type: 'android',
    device_code: '',
    device_name: '',
    api_key: '',
    local_ip: '',
    app_version: '',
    os_name: '',
    os_version: '',
    status: 'active',
    capabilities: {
        camera: true,
        printer: false,
        offline_queue: false,
        local_render: false,
    },
});

const filteredDevices = computed(() => devices.value);

const deviceTypeOptions = [
    { value: 'android', label: 'Android' },
    { value: 'minipc_kiosk', label: 'MiniPC Kiosk' },
    { value: 'print_agent', label: 'Print Agent' },
];

const capabilityLabels: Record<string, string> = {
    camera: 'camera',
    printer: 'printer',
    offline_queue: 'offline queue',
    local_render: 'local render',
};

const capabilityOrder = Object.keys(capabilityLabels);

const capabilityEntries = (capabilities?: Record<string, boolean>) => {
    if (!capabilities || !Object.keys(capabilities).length) {
        return [];
    }

    const orderedKeys = [
        ...capabilityOrder.filter((key) => key in capabilities),
        ...Object.keys(capabilities).filter(
            (key) => !capabilityOrder.includes(key),
        ),
    ];

    return orderedKeys.map((key) => ({
        key,
        label: capabilityLabels[key] ?? key.replaceAll('_', ' '),
        enabled: Boolean(capabilities[key]),
    }));
};

const summary = computed(() => {
    const total = devices.value.length;
    const online = devices.value.filter((device) => device.is_online).length;
    const active = devices.value.filter(
        (device) => (device.status ?? 'active') === 'active',
    ).length;

    return { total, online, active };
});

const normalizeApiError = (error: unknown, fallback: string): string => {
    const response = (error as { response?: { data?: unknown } })?.response;
    const data = response?.data;

    if (data && typeof data === 'object') {
        const message = (data as { message?: unknown }).message;

        if (typeof message === 'string' && message) {
            return message;
        }

        const errors = (data as { errors?: Record<string, string[]> }).errors;
        const firstError = errors ? Object.values(errors)[0]?.[0] : null;

        if (firstError) {
            return firstError;
        }
    }

    return fallback;
};

const resetForm = (): void => {
    form.station_id = stations.value[0]?.id ?? '';
    form.device_type = 'android';
    form.device_code = '';
    form.device_name = '';
    form.api_key = '';
    form.local_ip = '';
    form.app_version = '';
    form.os_name = '';
    form.os_version = '';
    form.status = 'active';
    form.capabilities = {
        camera: true,
        printer: false,
        offline_queue: false,
        local_render: false,
    };
};

const loadDevices = async (): Promise<void> => {
    loading.value = true;
    errorMessage.value = null;

    try {
        const response = await get<DevicesResponse>(listDevices());
        devices.value = response.data;
        stations.value = response.stations;

        if (!form.station_id) {
            form.station_id = response.stations[0]?.id ?? '';
        }
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal memuat daftar device.',
        );
    } finally {
        loading.value = false;
    }
};

const createDevice = async (): Promise<void> => {
    saving.value = true;
    feedback.value = null;
    errorMessage.value = null;
    lastCreatedSecret.value = null;

    try {
        const response = await post<CreatedDeviceResponse>(storeDevice(), {
            station_id: form.station_id,
            device_type: form.device_type,
            device_code: form.device_code,
            device_name: form.device_name,
            api_key: form.api_key,
            local_ip: form.local_ip || null,
            app_version: form.app_version || null,
            os_name: form.os_name || null,
            os_version: form.os_version || null,
            capabilities: form.capabilities,
            status: form.status,
        });

        await loadDevices();

        if (response.device.api_key) {
            lastCreatedSecret.value = {
                device_code: response.device.device_code,
                api_key: response.device.api_key,
            };
        }

        feedback.value = response.message;
        resetForm();
    } catch (error: unknown) {
        errorMessage.value = normalizeApiError(
            error,
            'Gagal menambahkan device baru.',
        );
    } finally {
        saving.value = false;
    }
};

const copySecret = async (): Promise<void> => {
    if (!lastCreatedSecret.value) {
        return;
    }

    await navigator.clipboard.writeText(
        `device_code=${lastCreatedSecret.value.device_code}\napi_key=${lastCreatedSecret.value.api_key}`,
    );
    feedback.value = 'Credential device disalin.';
};

onMounted(() => {
    void loadDevices();
});
</script>

<template>
    <AppLayout
        title="Devices"
        subtitle="Tambah dan monitor perangkat Android, MiniPC kiosk, dan print agent yang terhubung ke station."
    >
        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-[#e8e6ef] bg-white p-4">
                <div class="text-xs font-semibold text-[#6d6b77]">Total</div>
                <div class="mt-1 text-2xl font-semibold text-[#2f2b3dcc]">
                    {{ summary.total }}
                </div>
            </div>
            <div class="rounded-lg border border-[#e8e6ef] bg-white p-4">
                <div class="text-xs font-semibold text-[#6d6b77]">Active</div>
                <div class="mt-1 text-2xl font-semibold text-[#2f2b3dcc]">
                    {{ summary.active }}
                </div>
            </div>
            <div class="rounded-lg border border-[#e8e6ef] bg-white p-4">
                <div class="text-xs font-semibold text-[#6d6b77]">Online</div>
                <div class="mt-1 text-2xl font-semibold text-[#2f2b3dcc]">
                    {{ summary.online }}
                </div>
            </div>
        </div>

        <div
            v-if="feedback"
            class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
        >
            {{ feedback }}
        </div>

        <div
            v-if="errorMessage"
            class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            {{ errorMessage }}
        </div>

        <div
            v-if="lastCreatedSecret"
            class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="font-semibold">Credential device baru</div>
                    <div class="mt-1 font-mono text-xs">
                        {{ lastCreatedSecret.device_code }} /
                        {{ lastCreatedSecret.api_key }}
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-100"
                    @click="copySecret"
                >
                    Copy Credential
                </button>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]">
            <h2 class="mb-4 text-base font-semibold text-[#2f2b3dcc]">
                Tambah Device
            </h2>

            <form class="grid gap-4 lg:grid-cols-4" @submit.prevent="createDevice">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">Station</span>
                    <select
                        v-model="form.station_id"
                        required
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2"
                    >
                        <option value="" disabled>Pilih station</option>
                        <option
                            v-for="station in stations"
                            :key="station.id"
                            :value="station.id"
                        >
                            {{ station.station_code }} - {{ station.station_name }}
                        </option>
                    </select>
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">Device Type</span>
                    <select
                        v-model="form.device_type"
                        required
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2"
                    >
                        <option
                            v-for="option in deviceTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">Device Code</span>
                    <input
                        v-model="form.device_code"
                        required
                        type="text"
                        maxlength="50"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2 uppercase"
                        placeholder="PB-DEVICE-02"
                    />
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">Nama Device</span>
                    <input
                        v-model="form.device_name"
                        required
                        type="text"
                        maxlength="100"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2"
                        placeholder="Tablet Booth 2"
                    />
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">API Key</span>
                    <input
                        v-model="form.api_key"
                        required
                        type="text"
                        minlength="8"
                        maxlength="120"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2"
                        placeholder="secret-device-key-2"
                    />
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">Local IP</span>
                    <input
                        v-model="form.local_ip"
                        type="text"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2"
                        placeholder="192.168.88.25"
                    />
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">App Version</span>
                    <input
                        v-model="form.app_version"
                        type="text"
                        maxlength="30"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2"
                        placeholder="1.0.0"
                    />
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">OS Version</span>
                    <input
                        v-model="form.os_version"
                        type="text"
                        maxlength="30"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2"
                        placeholder="Android 14"
                    />
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">OS Name</span>
                    <input
                        v-model="form.os_name"
                        type="text"
                        maxlength="50"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2"
                        placeholder="Android / Windows"
                    />
                </label>

                <label class="grid gap-1 text-sm">
                    <span class="font-medium text-[#2f2b3dcc]">Status</span>
                    <select
                        v-model="form.status"
                        class="rounded-lg border border-[#d8d4e7] px-3 py-2"
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </label>

                <div class="grid gap-2 text-sm lg:col-span-4">
                    <span class="font-medium text-[#2f2b3dcc]">Capabilities</span>
                    <div class="flex flex-wrap gap-3">
                        <label class="flex items-center gap-2 rounded-lg border border-[#e8e6ef] px-3 py-2">
                            <input v-model="form.capabilities.camera" type="checkbox" />
                            <span>Camera</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-[#e8e6ef] px-3 py-2">
                            <input v-model="form.capabilities.printer" type="checkbox" />
                            <span>Printer</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-[#e8e6ef] px-3 py-2">
                            <input v-model="form.capabilities.offline_queue" type="checkbox" />
                            <span>Offline Queue</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-lg border border-[#e8e6ef] px-3 py-2">
                            <input v-model="form.capabilities.local_render" type="checkbox" />
                            <span>Local Render</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-end lg:col-span-4">
                    <button
                        type="submit"
                        class="rounded-lg bg-[#7367f0] px-4 py-2 text-sm font-semibold text-white hover:bg-[#685dd8] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="saving || !stations.length"
                    >
                        {{ saving ? 'Menyimpan...' : 'Tambah Device' }}
                    </button>
                </div>
            </form>
        </div>

        <div v-if="loading" class="text-sm text-[#6d6b77]">
            Loading devices...
        </div>

        <div v-else-if="!filteredDevices.length">
            <EmptyState
                title="Belum ada device"
                message="Device Android yang ditambahkan akan muncul di sini."
            />
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="device in filteredDevices"
                :key="device.id"
                class="rounded-xl border border-[#e8e6ef] bg-white p-5 shadow-[0_2px_10px_rgba(47,43,61,0.06)]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-[#2f2b3dcc]">
                            {{ device.device_name }}
                        </h2>
                        <p class="mt-1 font-mono text-xs text-[#6d6b77]">
                            {{ device.device_code }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-[#7367f0]">
                            {{ device.device_type?.replaceAll('_', ' ') ?? 'android' }}
                        </p>
                    </div>
                    <StatusBadge
                        :status="device.is_online ? 'online' : (device.status ?? 'offline')"
                    />
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-[#f5f5f9] p-3">
                        <div class="text-xs text-[#6d6b77]">Station</div>
                        <div class="mt-1 font-semibold text-[#2f2b3dcc]">
                            {{ device.station?.station_code ?? '-' }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-[#f5f5f9] p-3">
                        <div class="text-xs text-[#6d6b77]">Sessions</div>
                        <div class="mt-1 font-semibold text-[#2f2b3dcc]">
                            {{ device.sessions_count ?? 0 }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 space-y-1 text-xs text-[#6d6b77]">
                    <p>IP: {{ device.local_ip ?? '-' }}</p>
                    <p>App: {{ device.app_version ?? '-' }}</p>
                    <p>OS: {{ device.os_name ?? '-' }} {{ device.os_version ?? '' }}</p>
                    <div class="grid gap-2">
                        <div>Capabilities:</div>
                        <div
                            v-if="capabilityEntries(device.capabilities).length"
                            class="flex flex-wrap gap-2"
                        >
                            <span
                                v-for="capability in capabilityEntries(
                                    device.capabilities,
                                )"
                                :key="capability.key"
                                class="rounded-md border px-2 py-1 text-[11px] font-medium"
                                :class="
                                    capability.enabled
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                        : 'border-[#e8e6ef] bg-[#f5f5f9] text-[#8a8794]'
                                "
                            >
                                {{ capability.label }}:
                                {{ capability.enabled ? 'true' : 'false' }}
                            </span>
                        </div>
                        <div v-else>-</div>
                    </div>
                    <p>Last heartbeat: {{ device.last_heartbeat_at ?? '-' }}</p>
                    <p>Last sync: {{ device.last_sync_at ?? '-' }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
