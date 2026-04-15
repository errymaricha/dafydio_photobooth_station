<?php

namespace App\Http\Controllers\Api\PrintAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintAgentFailJobRequest;
use App\Http\Requests\PrintAgentQueueActionRequest;
use App\Models\PrintLog;
use App\Models\PrintQueueJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueueController extends Controller
{
    protected function getAgentPrinterId(Request $request): ?string
    {
        return $request->user()?->printer_id;
    }

    protected function ensureJobBelongsToAgent(Request $request, PrintQueueJob $job): ?\Illuminate\Http\JsonResponse
    {
        $printerId = $this->getAgentPrinterId($request);

        if (!$printerId) {
            return response()->json([
                'message' => 'This print-agent account is not bound to any printer.'
            ], 403);
        }

        if ($job->printer_id !== $printerId) {
            return response()->json([
                'message' => 'This job does not belong to this printer.'
            ], 403);
        }

        return null;
    }



    /**
     * Return the next pending job that belongs to the authenticated print-agent.
     */
    public function next(PrintAgentQueueActionRequest $request)
    {
        $validated = $request->validated();
        $agentPrinterId = $this->getAgentPrinterId($request);

        if (!$agentPrinterId) {
            return response()->json([
                'message' => 'This print-agent account is not bound to any printer.',
            ], 403);
        }

        if (
            isset($validated['printer_id']) &&
            $validated['printer_id'] !== $agentPrinterId
        ) {
            return response()->json([
                'message' => 'This printer does not belong to the authenticated print-agent.',
            ], 403);
        }

        $query = PrintQueueJob::with(['printOrder'])
            ->where('status', 'pending')
            ->where('printer_id', $agentPrinterId)
            ->orderByDesc('priority')
            ->orderBy('queued_at');

        $job = $query->first();

        if (!$job) {
            return response()->json([
                'message' => 'No pending print job.'
            ], 404);
        }

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'printer_id' => $job->printer_id,
            'queued_at' => $job->queued_at,
            'payload' => $job->job_payload,
        ]);
    }

    /**
     * Mark a pending job as being processed by the authenticated print-agent.
     */
    public function ack(PrintAgentQueueActionRequest $request, PrintQueueJob $job)
    {
        if ($response = $this->ensureJobBelongsToAgent($request, $job)) {
            return $response;
        }

        return DB::transaction(function () use ($job) {
            $updated = PrintQueueJob::where('id', $job->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'processing',
                    'processed_at' => now(),
                    'attempt_count' => $job->attempt_count + 1,
                ]);

            if ($updated === 0) {
                return response()->json([
                    'message' => 'Job already taken or no longer pending.'
                ], 409);
            }

            $job->refresh();

            $job->printOrder?->update([
                'status' => 'printing',
            ]);

            if ($job->printOrder?->session) {
                $job->printOrder->session->update([
                    'status' => 'printing',
                ]);
            }

            PrintLog::create([
                'id' => (string) Str::uuid(),
                'print_order_id' => $job->print_order_id,
                'print_queue_job_id' => $job->id,
                'printer_id' => $job->printer_id,
                'log_level' => 'info',
                'message' => 'Print job acknowledged by agent',
                'payload_json' => [
                    'new_status' => 'processing',
                ],
            ]);

            return response()->json([
                'message' => 'Job acknowledged',
                'status' => $job->status,
            ]);
        });
    }

    /**
     * Complete a processing job and propagate the final state to the order
     * and originating session.
     */
    public function complete(PrintAgentQueueActionRequest $request, PrintQueueJob $job)
    {
        if ($response = $this->ensureJobBelongsToAgent($request, $job)) {
            return $response;
        }

        if ($job->status !== 'processing') {
            return response()->json([
                'message' => 'Only processing jobs can be completed.'
            ], 422);
        }

        return DB::transaction(function () use ($request, $job) {
            $job->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);

            $job->printOrder?->update([
                'status' => 'printed',
                'completed_at' => now(),
            ]);

            if ($job->printOrder?->session) {
                $job->printOrder->session->update([
                    'status' => 'printed',
                ]);
            }

            PrintLog::create([
                'id' => (string) Str::uuid(),
                'print_order_id' => $job->print_order_id,
                'print_queue_job_id' => $job->id,
                'printer_id' => $job->printer_id,
                'log_level' => 'info',
                'message' => 'Print job completed',
                'payload_json' => $request->validated(),
            ]);

            return response()->json([
                'message' => 'Job completed',
                'status' => $job->status,
            ]);
        });
    }

    /**
     * Mark a pending or processing job as failed for the authenticated agent.
     */
    public function fail(PrintAgentFailJobRequest $request, PrintQueueJob $job)
    {
        if ($response = $this->ensureJobBelongsToAgent($request, $job)) {
            return $response;
        }

        if (!in_array($job->status, ['pending', 'processing'], true)) {
            return response()->json([
                'message' => 'This job cannot be failed from current status.'
            ], 422);
        }

        return DB::transaction(function () use ($request, $job) {
            $job->update([
                'status' => 'failed',
                'last_error' => $request->input('error'),
                'finished_at' => now(),
            ]);

            $job->printOrder?->update([
                'status' => 'failed',
            ]);

            if ($job->printOrder?->session) {
                $job->printOrder->session->update([
                    'status' => 'failed_print',
                ]);
            }

            PrintLog::create([
                'id' => (string) Str::uuid(),
                'print_order_id' => $job->print_order_id,
                'print_queue_job_id' => $job->id,
                'printer_id' => $job->printer_id,
                'log_level' => 'error',
                'message' => 'Print job failed',
                'payload_json' => ['error' => $request->validated('error')],
            ]);

            return response()->json([
                'message' => 'Job marked as failed',
                'status' => $job->status,
            ]);
        });
    }
}
