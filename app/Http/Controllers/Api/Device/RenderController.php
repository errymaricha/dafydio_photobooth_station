<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Api\Editor\RenderController as EditorRenderController;
use App\Http\Controllers\Controller;
use App\Http\Requests\RenderEditJobRequest;
use App\Models\EditJob;
use Illuminate\Http\JsonResponse;

class RenderController extends Controller
{
    public function store(RenderEditJobRequest $request, EditJob $editJob): JsonResponse
    {
        $device = $request->user();

        if (! $device) {
            return response()->json([
                'message' => 'Unauthenticated device.',
            ], 401);
        }

        $editJob->loadMissing('session');

        if (! $editJob->session || $editJob->session->device_id !== $device->id) {
            return response()->json([
                'message' => 'This edit job does not belong to this device session.',
            ], 403);
        }

        return app(EditorRenderController::class)->store($request, $editJob);
    }
}
