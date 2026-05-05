<?php

namespace App\Http\Controllers\Api\PrintAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintAgentSyncDetectedPrintersRequest;
use App\Models\DetectedPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DetectedPrinterController extends Controller
{
    public function sync(PrintAgentSyncDetectedPrintersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $stationId = (string) $validated['station_id'];
        $payloadPrinters = $validated['printers'];
        $now = now();

        $upsertRows = collect($payloadPrinters)
            ->map(function (array $printer) use ($now, $stationId): array {
                return [
                    'id' => (string) Str::uuid(),
                    'station_id' => $stationId,
                    'os_identifier' => (string) $printer['os_identifier'],
                    'printer_name' => (string) $printer['printer_name'],
                    'printer_type' => $printer['printer_type'] ?? 'photo',
                    'connection_type' => $printer['connection_type'] ?? 'network',
                    'ip_address' => $printer['ip_address'] ?? null,
                    'port' => $printer['port'] ?? null,
                    'driver_name' => $printer['driver_name'] ?? null,
                    'paper_size_default' => $printer['paper_size_default'] ?? null,
                    'status' => $printer['status'] ?? 'ready',
                    'is_default' => (bool) ($printer['is_default'] ?? false),
                    'capabilities_json' => $printer['capabilities'] ?? null,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        DB::transaction(function () use ($stationId, $upsertRows): void {
            DetectedPrinter::query()->where('station_id', $stationId)->update([
                'status' => 'offline',
            ]);

            DB::table('detected_printers')->upsert(
                $upsertRows,
                ['station_id', 'os_identifier'],
                [
                    'printer_name',
                    'printer_type',
                    'connection_type',
                    'ip_address',
                    'port',
                    'driver_name',
                    'paper_size_default',
                    'status',
                    'is_default',
                    'capabilities_json',
                    'last_seen_at',
                    'updated_at',
                ]
            );
        });

        return response()->json([
            'message' => 'Detected printers synced.',
            'station_id' => $stationId,
            'synced_count' => count($upsertRows),
        ]);
    }
}
