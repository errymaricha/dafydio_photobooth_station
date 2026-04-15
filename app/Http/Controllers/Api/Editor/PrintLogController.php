<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Models\PrintLog;
use App\Models\PrintOrder;
use Illuminate\Http\Request;

class PrintLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'log_level' => ['nullable', 'string'],
            'printer_id' => ['nullable', 'uuid', 'exists:printers,id'],
            'print_order_id' => ['nullable', 'uuid', 'exists:print_orders,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PrintLog::with([
            'printOrder.session',
            'printQueueJob',
            'printer',
        ])->latest();

        if ($request->filled('log_level')) {
            $query->where('log_level', $request->input('log_level'));
        }

        if ($request->filled('printer_id')) {
            $query->where('printer_id', $request->input('printer_id'));
        }

        if ($request->filled('print_order_id')) {
            $query->where('print_order_id', $request->input('print_order_id'));
        }

        $limit = (int) $request->input('limit', 20);

        $logs = $query->limit($limit)->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'log_level' => $log->log_level,
                'message' => $log->message,
                'payload' => $log->payload_json,
                'created_at' => $log->created_at,

                'print_order' => [
                    'id' => $log->printOrder?->id,
                    'order_code' => $log->printOrder?->order_code,
                ],

                'session' => [
                    'id' => $log->printOrder?->session?->id,
                    'session_code' => $log->printOrder?->session?->session_code,
                ],

                'queue_job' => [
                    'id' => $log->printQueueJob?->id,
                    'status' => $log->printQueueJob?->status,
                ],

                'printer' => [
                    'id' => $log->printer?->id,
                    'name' => $log->printer?->printer_name,
                ],
            ];
        });

        return response()->json($logs);
    }

    public function byOrder(PrintOrder $printOrder)
    {
        $logs = PrintLog::with([
            'printQueueJob',
            'printer',
        ])
            ->where('print_order_id', $printOrder->id)
            ->latest()
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'log_level' => $log->log_level,
                    'message' => $log->message,
                    'payload' => $log->payload_json,
                    'created_at' => $log->created_at,

                    'queue_job' => [
                        'id' => $log->printQueueJob?->id,
                        'status' => $log->printQueueJob?->status,
                    ],

                    'printer' => [
                        'id' => $log->printer?->id,
                        'name' => $log->printer?->printer_name,
                    ],
                ];
            });

        return response()->json([
            'print_order' => [
                'id' => $printOrder->id,
                'order_code' => $printOrder->order_code,
                'status' => $printOrder->status,
            ],
            'logs' => $logs,
        ]);
    }
}