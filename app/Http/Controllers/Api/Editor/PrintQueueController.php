<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Models\PrintLog;
use App\Models\PrintOrder;
use App\Models\PrintQueueJob;
use App\Http\Requests\QueuePrintOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrintQueueController extends Controller
{
    public function store(QueuePrintOrderRequest $request, PrintOrder $printOrder)
    {
        $printOrder->load(['items.renderedOutput.file', 'session']);

        $existingPending = PrintQueueJob::where('print_order_id', $printOrder->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existingPending) {
            return response()->json([
                'message' => 'Print order is already queued.',
                'print_queue_job_id' => $existingPending->id,
            ], 200);
        }

        $payload = [
            'print_order_id' => $printOrder->id,
            'order_code' => $printOrder->order_code,
            'copies' => $printOrder->total_qty,
            'items' => $printOrder->items->map(function ($item) {
                return [
                    'print_order_item_id' => $item->id,
                    'copies' => $item->copies,
                    'paper_size' => $item->paper_size,
                    'file_path' => $item->renderedOutput?->file?->file_path,
                    'file_url' => $item->renderedOutput?->file
                        ? url('storage/' . $item->renderedOutput->file->file_path)
                        : null,
                ];
            })->values()->toArray(),
        ];

        $job = DB::transaction(function () use ($payload, $printOrder, $request) {
            $job = PrintQueueJob::create([
                'id' => (string) Str::uuid(),
                'print_order_id' => $printOrder->id,
                'printer_id' => $request->input('printer_id'),
                'queue_name' => 'print',
                'priority' => (int) $request->input('priority', 0),
                'job_payload' => $payload,
                'attempt_count' => 0,
                'max_attempts' => 3,
                'status' => 'pending',
                'queued_at' => now(),
            ]);

            $printOrder->update([
                'printer_id' => $request->input('printer_id'),
                'status' => 'queued',
            ]);

            $printOrder->items()->update([
                'status' => 'queued',
            ]);

            if ($printOrder->session) {
                $printOrder->session->update([
                    'status' => 'queued_print',
                ]);
            }

            PrintLog::create([
                'id' => (string) Str::uuid(),
                'print_order_id' => $printOrder->id,
                'print_queue_job_id' => $job->id,
                'printer_id' => $request->input('printer_id'),
                'log_level' => 'info',
                'message' => 'Print order queued',
                'payload_json' => $payload,
            ]);

            return $job;
        });

        return response()->json([
            'message' => 'Print queue job created',
            'print_queue_job_id' => $job->id,
            'status' => $job->status,
            'order_status' => $printOrder->fresh()->status,
            'session_status' => $printOrder->session?->fresh()?->status,
        ], 201);
    }

    public function retry(PrintQueueJob $job)
    {
        if ($job->status !== 'failed') {
            return response()->json([
                'message' => 'Only failed jobs can be retried.'
            ], 422);
        }

        $printOrder = $job->printOrder;

        if (!$printOrder) {
            return response()->json([
                'message' => 'Print order not found.'
            ], 422);
        }

        $newJob = DB::transaction(function () use ($job, $printOrder) {
            $newJob = PrintQueueJob::create([
                'id' => (string) Str::uuid(),
                'print_order_id' => $job->print_order_id,
                'printer_id' => $job->printer_id,
                'queue_name' => $job->queue_name,
                'priority' => $job->priority,
                'job_payload' => $job->job_payload,
                'attempt_count' => 0,
                'max_attempts' => $job->max_attempts,
                'status' => 'pending',
                'queued_at' => now(),
            ]);

            $printOrder->update([
                'status' => 'queued',
            ]);

            $printOrder->items()->update([
                'status' => 'queued',
            ]);

            if ($printOrder->session) {
                $printOrder->session->update([
                    'status' => 'queued_print',
                ]);
            }

            PrintLog::create([
                'id' => (string) Str::uuid(),
                'print_order_id' => $job->print_order_id,
                'print_queue_job_id' => $newJob->id,
                'printer_id' => $job->printer_id,
                'log_level' => 'info',
                'message' => 'Retry print job created',
                'payload_json' => [
                    'retry_from_job_id' => $job->id,
                ],
            ]);

            return $newJob;
        });

        return response()->json([
            'message' => 'Retry job created',
            'new_print_queue_job_id' => $newJob->id,
            'status' => $newJob->status,
        ], 201);
    }
    public function indexJobs(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'string'],
            'printer_id' => ['nullable', 'uuid', 'exists:printers,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PrintQueueJob::with([
            'printOrder.session',
            'printer',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('printer_id')) {
            $query->where('printer_id', $request->input('printer_id'));
        }

        $limit = (int) $request->input('limit', 20);

        $jobs = $query->limit($limit)->get()->map(function ($job) {
            return [
                'id' => $job->id,
                'status' => $job->status,
                'priority' => $job->priority,
                'attempt_count' => $job->attempt_count,
                'max_attempts' => $job->max_attempts,
                'queued_at' => $job->queued_at,
                'processed_at' => $job->processed_at,
                'finished_at' => $job->finished_at,
                'last_error' => $job->last_error,

                'print_order' => [
                    'id' => $job->printOrder?->id,
                    'order_code' => $job->printOrder?->order_code,
                    'status' => $job->printOrder?->status,
                ],

                'session' => [
                    'id' => $job->printOrder?->session?->id,
                    'session_code' => $job->printOrder?->session?->session_code,
                ],

                'printer' => [
                    'id' => $job->printer?->id,
                    'name' => $job->printer?->printer_name,
                ],
            ];
        });

        return response()->json($jobs);
    }

    public function showJob(\App\Models\PrintQueueJob $job)
    {
        $job->load([
            'printOrder.session',
            'printer',
        ]);

        return response()->json([
            'id' => $job->id,
            'status' => $job->status,
            'queue_name' => $job->queue_name,
            'priority' => $job->priority,
            'attempt_count' => $job->attempt_count,
            'max_attempts' => $job->max_attempts,
            'queued_at' => $job->queued_at,
            'processed_at' => $job->processed_at,
            'finished_at' => $job->finished_at,
            'last_error' => $job->last_error,
            'payload' => $job->job_payload,

            'print_order' => [
                'id' => $job->printOrder?->id,
                'order_code' => $job->printOrder?->order_code,
                'status' => $job->printOrder?->status,
            ],

            'session' => [
                'id' => $job->printOrder?->session?->id,
                'session_code' => $job->printOrder?->session?->session_code,
            ],

            'printer' => [
                'id' => $job->printer?->id,
                'name' => $job->printer?->printer_name,
            ],
        ]);
    }

        public function summary()
        {
            $base = \App\Models\PrintQueueJob::query();

            return response()->json([
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'processing' => (clone $base)->where('status', 'processing')->count(),
                'failed' => (clone $base)->where('status', 'failed')->count(),
                'completed' => (clone $base)->where('status', 'completed')->count(),
                'today_completed' => (clone $base)
                    ->where('status', 'completed')
                    ->whereDate('finished_at', now()->toDateString())
                    ->count(),
            ]);
        }
}
