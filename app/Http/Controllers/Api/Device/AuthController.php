<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceLoginRequest;
use App\Models\AndroidDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate a device and rotate any existing Sanctum tokens so each
     * device effectively keeps a single active API session.
     *
     * Request example:
     * {"device_code":"DV-AB12CD","api_key":"top-secret-device-key"}
     *
     * Success response example:
     * {"token":"1|token","device_id":"uuid","station_id":"uuid","device_code":"DV-AB12CD"}
     *
     * Error response example (401):
     * {"message":"Invalid device credentials"}
     *
     * @response array{token: string, device_id: string, station_id: string, device_code: string}
     */
    public function login(DeviceLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $device = AndroidDevice::query()
            ->where('device_code', $validated['device_code'])
            ->first();

        if (!$device || !Hash::check($validated['api_key'], $device->api_key_hash)) {
            return response()->json([
                'message' => 'Invalid device credentials',
            ], 401);
        }

        $device->tokens()->delete();

        $token = $device->createToken('device-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'device_id' => $device->id,
            'station_id' => $device->station_id,
            'device_code' => $device->device_code,
        ]);
    }
}
