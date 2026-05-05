<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\PrintQueueJob;
use Illuminate\Http\Request;

class PrinterController extends Controller
{
    public function index(Request $request)
    {
        $printers = Printer::with('station')
            ->withCount([
                'queueJobs as pending_queue_jobs_count' => fn ($query) => $query->where('status', 'pending'),
                'queueJobs as processing_queue_jobs_count' => fn ($query) => $query->where('status', 'processing'),
                'queueJobs as failed_queue_jobs_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->get()
            ->map(function ($printer) {
                $isOnline = $printer->last_seen_at
                    ? $printer->last_seen_at->gt(now()->subMinutes(2))
                    : false;

                return [
                    'id' => $printer->id,
                    'printer_code' => $printer->printer_code,
                    'printer_name' => $printer->printer_name,
                    'printer_type' => $printer->printer_type,
                    'connection_type' => $printer->connection_type,
                    'ip_address' => $printer->ip_address,
                    'port' => $printer->port,
                    'driver_name' => $printer->driver_name,
                    'paper_size_default' => $printer->paper_size_default,
                    'is_default' => (bool) $printer->is_default,

                    'status' => $printer->status,
                    'is_online' => $isOnline,
                    'last_seen_at' => $printer->last_seen_at,
                    'last_error' => $printer->last_error ?? null,
                    'meta' => $printer->meta_json ?? null,

                    'station' => [
                        'id' => $printer->station?->id,
                        'code' => $printer->station?->station_code,
                    ],

                    'queue' => [
                        'pending' => (int) $printer->pending_queue_jobs_count,
                        'processing' => (int) $printer->processing_queue_jobs_count,
                        'failed' => (int) $printer->failed_queue_jobs_count,
                    ],
                ];
            })
            ->values();

        return response()->json($printers);
    }

    public function show(Printer $printer)
    {
        $printer->load('station');

        $recentJobs = PrintQueueJob::with('printOrder.session')
            ->where('printer_id', $printer->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'status' => $job->status,
                    'priority' => $job->priority,
                    'attempt_count' => $job->attempt_count,
                    'queued_at' => $job->queued_at,
                    'processed_at' => $job->processed_at,
                    'finished_at' => $job->finished_at,
                    'last_error' => $job->last_error,
                    'print_order' => [
                        'id' => $job->printOrder?->id,
                        'order_code' => $job->printOrder?->order_code,
                    ],
                    'session' => [
                        'id' => $job->printOrder?->session?->id,
                        'session_code' => $job->printOrder?->session?->session_code,
                    ],
                ];
            })
            ->values();

        $isOnline = $printer->last_seen_at
            ? $printer->last_seen_at->gt(now()->subMinutes(2))
            : false;

        return response()->json([
            'id' => $printer->id,
            'printer_code' => $printer->printer_code,
            'printer_name' => $printer->printer_name,
            'printer_type' => $printer->printer_type,
            'connection_type' => $printer->connection_type,
            'ip_address' => $printer->ip_address,
            'port' => $printer->port,
            'driver_name' => $printer->driver_name,
            'paper_size_default' => $printer->paper_size_default,
            'is_default' => (bool) $printer->is_default,

            'status' => $printer->status,
            'is_online' => $isOnline,
            'last_seen_at' => $printer->last_seen_at,
            'last_error' => $printer->last_error ?? null,
            'meta' => $printer->meta_json ?? null,

            'station' => [
                'id' => $printer->station?->id,
                'code' => $printer->station?->station_code,
            ],

            'queue' => [
                'pending' => PrintQueueJob::where('printer_id', $printer->id)
                    ->where('status', 'pending')
                    ->count(),
                'processing' => PrintQueueJob::where('printer_id', $printer->id)
                    ->where('status', 'processing')
                    ->count(),
                'failed' => PrintQueueJob::where('printer_id', $printer->id)
                    ->where('status', 'failed')
                    ->count(),
            ],

            'recent_jobs' => $recentJobs,
        ]);
    }
}
