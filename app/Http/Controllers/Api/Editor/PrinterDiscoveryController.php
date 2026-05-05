<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrinterFromDetectionRequest;
use App\Models\DetectedPrinter;
use App\Models\Printer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrinterDiscoveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $stationId = $request->string('station_id')->value();

        $detections = DetectedPrinter::query()
            ->with(['station', 'linkedPrinter'])
            ->when(
                $stationId,
                fn ($query) => $query->where('station_id', $stationId)
            )
            ->orderByDesc('last_seen_at')
            ->orderBy('printer_name')
            ->get()
            ->map(function (DetectedPrinter $detection): array {
                return [
                    'id' => $detection->id,
                    'os_identifier' => $detection->os_identifier,
                    'printer_name' => $detection->printer_name,
                    'printer_type' => $detection->printer_type,
                    'connection_type' => $detection->connection_type,
                    'ip_address' => $detection->ip_address,
                    'port' => $detection->port,
                    'driver_name' => $detection->driver_name,
                    'paper_size_default' => $detection->paper_size_default,
                    'status' => $detection->status,
                    'is_default' => $detection->is_default,
                    'last_seen_at' => $detection->last_seen_at,
                    'station' => [
                        'id' => $detection->station?->id,
                        'code' => $detection->station?->station_code,
                    ],
                    'linked_printer' => $detection->linkedPrinter ? [
                        'id' => $detection->linkedPrinter->id,
                        'printer_code' => $detection->linkedPrinter->printer_code,
                        'printer_name' => $detection->linkedPrinter->printer_name,
                    ] : null,
                ];
            })
            ->values();

        return response()->json($detections);
    }

    public function store(
        StorePrinterFromDetectionRequest $request,
        DetectedPrinter $detection
    ): JsonResponse {
        if ($detection->linked_printer_id) {
            $detection->load('linkedPrinter');

            return response()->json([
                'message' => 'Detection is already linked.',
                'printer' => $this->transformPrinter($detection->linkedPrinter),
            ]);
        }

        $validated = $request->validated();

        $printer = DB::transaction(function () use ($detection, $validated): Printer {
            $existingPrinter = Printer::query()
                ->where('station_id', $detection->station_id)
                ->where('printer_name', $detection->printer_name)
                ->first();

            if ($existingPrinter) {
                $detection->update([
                    'linked_printer_id' => $existingPrinter->id,
                ]);

                return $existingPrinter;
            }

            if ((bool) ($validated['is_default'] ?? $detection->is_default)) {
                Printer::query()
                    ->where('station_id', $detection->station_id)
                    ->update(['is_default' => false]);
            }

            $printer = Printer::create([
                'id' => (string) Str::uuid(),
                'station_id' => $detection->station_id,
                'printer_code' => $this->generatePrinterCode(),
                'printer_name' => $validated['printer_name'] ?? $detection->printer_name,
                'printer_type' => $detection->printer_type ?: 'photo',
                'connection_type' => $detection->connection_type ?: 'network',
                'ip_address' => $detection->ip_address,
                'port' => $detection->port,
                'driver_name' => $detection->driver_name,
                'paper_size_default' => $validated['paper_size_default']
                    ?? $detection->paper_size_default,
                'is_default' => (bool) ($validated['is_default'] ?? $detection->is_default),
                'status' => $validated['status'] ?? $detection->status ?? 'ready',
                'last_seen_at' => $detection->last_seen_at,
                'meta_json' => [
                    'source' => 'os_discovery',
                    'os_identifier' => $detection->os_identifier,
                ],
            ]);

            $detection->update([
                'linked_printer_id' => $printer->id,
            ]);

            return $printer;
        });

        return response()->json([
            'message' => 'Printer created from detection.',
            'printer' => $this->transformPrinter($printer),
        ], 201);
    }

    private function transformPrinter(?Printer $printer): ?array
    {
        if (! $printer) {
            return null;
        }

        $printer->loadMissing('station');

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
            'station' => [
                'id' => $printer->station?->id,
                'code' => $printer->station?->station_code,
            ],
        ];
    }

    private function generatePrinterCode(): string
    {
        do {
            $code = 'PRN-'.Str::upper(Str::random(6));
        } while (Printer::query()->where('printer_code', $code)->exists());

        return $code;
    }
}
