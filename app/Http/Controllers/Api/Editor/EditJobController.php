<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEditJobRequest;
use App\Models\EditJob;
use App\Models\EditJobItem;
use App\Models\PhotoSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EditJobController extends Controller
{
    public function store(StoreEditJobRequest $request, PhotoSession $session)
    {
        $validated = $request->validated();
        $editorId = $request->user()?->id;

        $editJob = DB::transaction(function () use ($editorId, $session, $validated) {
            $lastVersion = EditJob::where('session_id', $session->id)->max('version_no') ?? 0;
            $nextVersion = $lastVersion + 1;

            $editJob = EditJob::create([
                'id' => (string) Str::uuid(),
                'session_id' => $session->id,
                'editor_id' => $editorId,
                'template_id' => $validated['template_id'],
                'version_no' => $nextVersion,
                'edit_state_json' => $validated['edit_state_json'] ?? null,
                'status' => 'draft',
                'started_at' => now(),
            ]);

            foreach ($validated['items'] as $item) {
                EditJobItem::create([
                    'id' => (string) Str::uuid(),
                    'edit_job_id' => $editJob->id,
                    'session_photo_id' => $item['session_photo_id'],
                    'slot_index' => $item['slot_index'],
                    'crop_json' => $item['crop_json'] ?? null,
                    'transform_json' => $item['transform_json'] ?? null,
                    'filter_json' => $item['filter_json'] ?? null,
                ]);
            }

            $sessionUpdate = [
                'template_id' => $validated['template_id'],
            ];

            if ($session->status === 'uploaded') {
                $sessionUpdate['status'] = 'editing';
            }

            $session->update($sessionUpdate);

            return $editJob;
        });

        return response()->json([
            'message' => 'Edit job created',
            'edit_job_id' => $editJob->id,
            'session_id' => $session->id,
            'version_no' => $editJob->version_no,
            'status' => $editJob->status,
            'session_status' => $session->fresh()->status,
        ], 201);
    }
}
