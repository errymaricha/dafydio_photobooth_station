<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Models\AndroidDevice;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class DeviceController extends Controller
{
    public function index(): JsonResponse
    {
        $devices = AndroidDevice::query()
            ->with('station')
            ->withCount('sessions')
            ->orderBy('device_code')
            ->get()
            ->map(fn (AndroidDevice $device): array => $this->mapDevice($device))
            ->values();

        $stations = Station::query()
            ->orderBy('station_code')
            ->get(['id', 'station_code', 'station_name'])
            ->map(fn (Station $station): array => [
                'id' => $station->id,
                'station_code' => $station->station_code,
                'station_name' => $station->station_name,
            ])
            ->values();

        return response()->json([
            'data' => $devices,
            'stations' => $stations,
        ]);
    }

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $device = AndroidDevice::query()->create([
            'station_id' => $validated['station_id'],
            'device_type' => $validated['device_type'],
            'device_code' => strtoupper(trim($validated['device_code'])),
            'device_name' => $validated['device_name'],
            'api_key_hash' => Hash::make($validated['api_key']),
            'local_ip' => $validated['local_ip'] ?? null,
            'app_version' => $validated['app_version'] ?? null,
            'os_name' => $validated['os_name'] ?? null,
            'os_version' => $validated['os_version'] ?? null,
            'capabilities_json' => $validated['capabilities'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        $device->load('station')->loadCount('sessions');

        return response()->json([
            'message' => 'Device berhasil ditambahkan.',
            'device' => [
                ...$this->mapDevice($device),
                'api_key' => $validated['api_key'],
                'api_key_revealed_once' => true,
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapDevice(AndroidDevice $device): array
    {
        $isOnline = $device->last_heartbeat_at
            ? $device->last_heartbeat_at->gt(now()->subMinutes(2))
            : false;

        return [
            'id' => $device->id,
            'device_code' => $device->device_code,
            'device_name' => $device->device_name,
            'device_type' => $device->device_type ?? 'android',
            'local_ip' => $device->local_ip,
            'app_version' => $device->app_version,
            'os_name' => $device->os_name,
            'os_version' => $device->os_version,
            'battery_percent' => $device->battery_percent,
            'capabilities' => $device->capabilities_json ?? [],
            'config' => $device->config_json ?? [],
            'status' => $device->status,
            'is_online' => $isOnline,
            'last_heartbeat_at' => optional($device->last_heartbeat_at)->toIso8601String(),
            'last_sync_at' => optional($device->last_sync_at)->toIso8601String(),
            'sessions_count' => (int) ($device->sessions_count ?? 0),
            'station' => [
                'id' => $device->station?->id,
                'station_code' => $device->station?->station_code,
                'station_name' => $device->station?->station_name,
            ],
        ];
    }
}
