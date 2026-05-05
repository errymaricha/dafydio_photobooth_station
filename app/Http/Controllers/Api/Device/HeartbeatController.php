<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceHeartbeatRequest;
use App\Models\AndroidDevice;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HeartbeatController extends Controller
{
    public function store(DeviceHeartbeatRequest $request): JsonResponse
    {
        /** @var AndroidDevice|null $device */
        $device = $request->user();

        if (! $device) {
            return response()->json([
                'message' => 'Unauthenticated device.',
            ], 401);
        }

        $validated = $request->validated();
        $serverTime = now();
        $deviceIp = $request->ip() ?: ($validated['local_ip'] ?? $device->local_ip);
        $heartbeatAt = isset($validated['last_heartbeat_at'])
            ? CarbonImmutable::parse($validated['last_heartbeat_at'])
            : $serverTime;
        $syncAt = isset($validated['last_sync_at'])
            ? CarbonImmutable::parse($validated['last_sync_at'])
            : null;

        DB::transaction(function () use ($device, $validated, $deviceIp, $heartbeatAt, $syncAt): void {
            $device->update([
                'device_type' => $validated['device_type'] ?? $device->device_type ?? 'android',
                'local_ip' => $deviceIp,
                'battery_percent' => $validated['battery_percent'] ?? $device->battery_percent,
                'app_version' => $validated['app_version'] ?? $device->app_version,
                'os_name' => $validated['os_name'] ?? $device->os_name,
                'os_version' => $validated['os_version'] ?? $device->os_version,
                'capabilities_json' => $validated['capabilities'] ?? $device->capabilities_json,
                'last_heartbeat_at' => $heartbeatAt,
                'last_sync_at' => $syncAt ?? $device->last_sync_at,
                'status' => 'active',
            ]);

            DB::table('device_heartbeats')->insert([
                'id' => (string) Str::uuid(),
                'device_id' => $device->id,
                'device_type' => $validated['device_type'] ?? $device->device_type ?? 'android',
                'local_ip' => $deviceIp,
                'battery_percent' => $validated['battery_percent'] ?? null,
                'network_strength' => $validated['network_strength'] ?? null,
                'app_version' => $validated['app_version'] ?? $device->app_version,
                'os_name' => $validated['os_name'] ?? $device->os_name,
                'os_version' => $validated['os_version'] ?? $device->os_version,
                'capabilities_json' => isset($validated['capabilities'])
                    ? json_encode($validated['capabilities'])
                    : null,
                'metrics_json' => isset($validated['metrics'])
                    ? json_encode($validated['metrics'])
                    : null,
                'heartbeat_at' => $heartbeatAt,
                'sync_at' => $syncAt,
                'created_at' => $heartbeatAt,
                'updated_at' => $heartbeatAt,
            ]);
        });

        $device->refresh();

        return response()->json([
            'status' => 'ok',
            'server_time' => $serverTime->toIso8601String(),
            'message' => 'Heartbeat received',
            'device' => [
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
                'last_heartbeat_at' => optional($device->last_heartbeat_at)->toIso8601String(),
                'last_sync_at' => optional($device->last_sync_at)->toIso8601String(),
                'status' => $device->status,
            ],
        ]);
    }
}
