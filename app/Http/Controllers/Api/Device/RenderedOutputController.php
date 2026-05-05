<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceUploadRenderedOutputRequest;
use App\Models\AssetFile;
use App\Models\EditJob;
use App\Models\PhotoSession;
use App\Models\RenderedOutput;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RenderedOutputController extends Controller
{
    public function store(
        DeviceUploadRenderedOutputRequest $request,
        PhotoSession $session
    ): JsonResponse {
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

        $validated = $request->validated();
        $editJob = EditJob::query()
            ->where('id', $validated['edit_job_id'])
            ->where('session_id', $session->id)
            ->first();

        if (! $editJob) {
            return response()->json([
                'message' => 'Edit job does not belong to this session.',
            ], 422);
        }

        $existingRendered = RenderedOutput::query()
            ->where('edit_job_id', $editJob->id)
            ->where('is_active', true)
            ->with('file')
            ->first();

        if ($existingRendered && ! $request->boolean('force')) {
            return response()->json([
                'message' => 'Rendered output already exists',
                'rendered_output_id' => $existingRendered->id,
                'file_path' => $existingRendered->file?->file_path,
                'file_url' => $existingRendered->file
                    ? url('storage/'.$existingRendered->file->file_path)
                    : null,
                'status' => 'ready_print',
            ], 200);
        }

        $file = $request->file('rendered_image');

        if (! $file) {
            return response()->json([
                'message' => 'Rendered image file missing.',
            ], 422);
        }

        $session->loadMissing(['station', 'device']);

        if (! $session->station) {
            return response()->json([
                'message' => 'Station not found.',
            ], 500);
        }

        $datePath = now()->format('Y/m/d');
        $renderDir = 'stations/'
            .$session->station->station_code
            .'/sessions/'
            .$datePath
            .'/'
            .$session->session_code
            .'/rendered';
        $fileExt = strtolower($file->getClientOriginalExtension() ?: 'png');
        $fileName = 'ANDROID_FINAL_V'.$editJob->version_no.'.'.$fileExt;
        $storedPath = $file->storeAs($renderDir, $fileName, 'public');

        try {
            $imageSize = @getimagesize($file->getRealPath() ?: '');
            $width = (int) ($validated['width'] ?? ($imageSize[0] ?? 0));
            $height = (int) ($validated['height'] ?? ($imageSize[1] ?? 0));
            $dpi = isset($validated['dpi']) ? (int) $validated['dpi'] : 300;

            $renderedOutput = DB::transaction(function () use (
                $device,
                $dpi,
                $editJob,
                $file,
                $fileExt,
                $fileName,
                $height,
                $session,
                $storedPath,
                $width
            ): RenderedOutput {
                $asset = AssetFile::create([
                    'id' => (string) Str::uuid(),
                    'storage_disk' => 'public',
                    'file_path' => $storedPath,
                    'file_name' => $fileName,
                    'file_ext' => $fileExt,
                    'mime_type' => $file->getMimeType(),
                    'file_size_bytes' => $file->getSize(),
                    'width' => $width > 0 ? $width : null,
                    'height' => $height > 0 ? $height : null,
                    'file_category' => 'rendered',
                    'created_by_type' => 'device',
                    'created_by_id' => $device->id,
                ]);

                RenderedOutput::query()
                    ->where('session_id', $session->id)
                    ->update(['is_active' => false]);

                $renderedOutput = RenderedOutput::create([
                    'id' => (string) Str::uuid(),
                    'session_id' => $session->id,
                    'edit_job_id' => $editJob->id,
                    'file_id' => $asset->id,
                    'version_no' => $editJob->version_no,
                    'render_type' => 'final_print',
                    'width' => $width > 0 ? $width : null,
                    'height' => $height > 0 ? $height : null,
                    'dpi' => $dpi,
                    'is_active' => true,
                    'rendered_at' => now(),
                ]);

                $editJob->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                if (in_array($session->status, ['uploaded', 'editing'], true)) {
                    $session->update([
                        'status' => 'ready_print',
                    ]);
                }

                return $renderedOutput;
            });
        } catch (\Throwable $throwable) {
            Storage::disk('public')->delete($storedPath);

            throw $throwable;
        }

        return response()->json([
            'message' => 'Rendered output uploaded',
            'rendered_output_id' => $renderedOutput->id,
            'file_path' => $storedPath,
            'file_url' => url('storage/'.$storedPath),
            'status' => 'ready_print',
        ], 201);
    }
}
