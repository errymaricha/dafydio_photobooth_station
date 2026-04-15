<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Models\PhotoSession;
use App\Models\PrintLog;
use App\Models\PrintOrder;
use App\Models\PrintQueueJob;
use App\Models\Printer;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $recentSessions = PhotoSession::with(['station', 'device'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'session_code' => $session->session_code,
                    'status' => $session->status,
                    'captured_count' => $session->captured_count,
                    'completed_at' => $session->completed_at,
                    'station' => [
                        'id' => $session->station?->id,
                        'code' => $session->station?->station_code,
                    ],
                    'device' => [
                        'id' => $session->device?->id,
                        'code' => $session->device?->device_code,
                    ],
                ];
            })
            ->values();

        $recentPrintOrders = PrintOrder::with(['session', 'printer'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'status' => $order->status,
                    'total_qty' => $order->total_qty,
                    'total_amount' => $order->total_amount,
                    'ordered_at' => $order->ordered_at,
                    'session' => [
                        'id' => $order->session?->id,
                        'session_code' => $order->session?->session_code,
                    ],
                    'printer' => [
                        'id' => $order->printer?->id,
                        'name' => $order->printer?->printer_name,
                    ],
                ];
            })
            ->values();

        $recentFailedJobs = PrintQueueJob::with(['printOrder.session', 'printer'])
            ->where('status', 'failed')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'last_error' => $job->last_error,
                    'finished_at' => $job->finished_at,
                    'print_order' => [
                        'id' => $job->printOrder?->id,
                        'order_code' => $job->printOrder?->order_code,
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
            })
            ->values();

        $recentLogs = PrintLog::with(['printOrder', 'printer'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'log_level' => $log->log_level,
                    'message' => $log->message,
                    'created_at' => $log->created_at,
                    'print_order' => [
                        'id' => $log->printOrder?->id,
                        'order_code' => $log->printOrder?->order_code,
                    ],
                    'printer' => [
                        'id' => $log->printer?->id,
                        'name' => $log->printer?->printer_name,
                    ],
                ];
            })
            ->values();

        $printerBase = Printer::query();

        return response()->json([
            'sessions' => [
                'uploaded_today' => PhotoSession::where('status', 'uploaded')
                    ->whereDate('completed_at', $today)
                    ->count(),
                'editing' => PhotoSession::where('status', 'editing')->count(),
                'ready_print' => PhotoSession::where('status', 'ready_print')->count(),
                'queued_print' => PhotoSession::where('status', 'queued_print')->count(),
                'failed_print' => PhotoSession::where('status', 'failed_print')->count(),
                'printed_today' => PhotoSession::where('status', 'printed')
                    ->whereDate('updated_at', $today)
                    ->count(),
            ],

            'print_orders' => [
                'today' => PrintOrder::whereDate('ordered_at', $today)->count(),
                'queued' => PrintOrder::where('status', 'queued')->count(),
                'printing' => PrintOrder::where('status', 'printing')->count(),
                'failed' => PrintOrder::where('status', 'failed')->count(),
                'printed_today' => PrintOrder::where('status', 'printed')
                    ->whereDate('completed_at', $today)
                    ->count(),
            ],

            'print_queue' => [
                'pending' => PrintQueueJob::where('status', 'pending')->count(),
                'processing' => PrintQueueJob::where('status', 'processing')->count(),
                'failed' => PrintQueueJob::where('status', 'failed')->count(),
                'completed_today' => PrintQueueJob::where('status', 'completed')
                    ->whereDate('finished_at', $today)
                    ->count(),
            ],

            'printers' => [
                'total' => (clone $printerBase)->count(),
                'online' => (clone $printerBase)
                    ->whereNotNull('last_seen_at')
                    ->where('last_seen_at', '>', now()->subMinutes(2))
                    ->count(),
                'offline' => (clone $printerBase)
                    ->where(function ($query) {
                        $query->whereNull('last_seen_at')
                            ->orWhere('last_seen_at', '<=', now()->subMinutes(2));
                    })
                    ->count(),
                'ready' => (clone $printerBase)->where('status', 'ready')->count(),
                'printing' => (clone $printerBase)->where('status', 'printing')->count(),
                'error' => (clone $printerBase)->where('status', 'error')->count(),
            ],

            'recent_sessions' => $recentSessions,
            'recent_print_orders' => $recentPrintOrders,
            'recent_failed_jobs' => $recentFailedJobs,
            'recent_logs' => $recentLogs,
        ]);
    }
}