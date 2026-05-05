<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Api\Editor\EditJobController as EditorEditJobController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEditJobRequest;
use App\Models\PhotoSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class EditJobController extends Controller
{
    public function store(StoreEditJobRequest $request, PhotoSession $session): JsonResponse
    {
        $device = $request->user();

        if (! $device) {
            return response()->json([
                'message' => 'Unauthenticated device.',
            ], 401);
        }

        if ($session->device_id !== $device->id) {
            return response()->json([
                'message' => 'This session does not belong to this device.',
            ], 403);
        }

        $editor = User::query()->oldest('created_at')->first();

        if (! $editor) {
            return response()->json([
                'message' => 'No editor account is available for device rendering.',
            ], 422);
        }

        $request->setUserResolver(static fn () => $editor);

        return app(EditorEditJobController::class)->store($request, $session);
    }
}
