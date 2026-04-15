<?php

namespace App\Http\Controllers\Api\PrintAgent;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use Illuminate\Http\Request;

use App\Http\Requests\PrintAgentHeartbeatRequest;

class HeartbeatController extends Controller
{
    public function store(PrintAgentHeartbeatRequest $request)
    {
        $printerId = $request->user()?->printer_id;

        if (!$printerId) {
            return response()->json([
                'message' => 'This print-agent account is not bound to any printer.'
            ], 403);
        }

        $printer = Printer::findOrFail($printerId);

        $printer->update([
            'status' => $request->input('status'),
            'last_error' => $request->input('last_error'),
            'ip_address' => $request->input('ip_address', $printer->ip_address),
            'port' => $request->input('port', $printer->port),
            'last_seen_at' => now(),
            'meta_json' => $request->input('meta'),
        ]);

        return response()->json([
            'message' => 'Heartbeat received',
            'printer' => [
                'id' => $printer->id,
                'printer_code' => $printer->printer_code,
                'printer_name' => $printer->printer_name,
                'status' => $printer->status,
                'last_seen_at' => $printer->last_seen_at,
                'last_error' => $printer->last_error,
                'ip_address' => $printer->ip_address,
                'port' => $printer->port,
                'meta' => $printer->meta_json,
            ],
        ]);
    }
}