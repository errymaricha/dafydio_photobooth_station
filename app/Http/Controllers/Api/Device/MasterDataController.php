<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MasterDataController extends Controller
{
    private const DEVICE_CONTRACT_VERSION = '2026-04-17';

    public function index(Request $request): JsonResponse
    {
        $device = $request->user();
        $station = $device->station()->first();

        if (! $station) {
            return response()->json([
                'message' => 'Device station not found.',
            ], 422);
        }

        $templates = Template::query()
            ->with(['slots', 'assets.file'])
            ->where('status', 'active')
            ->orderBy('template_name')
            ->get()
            ->map(function (Template $template): array {
                $overlayAsset = $template->assets->firstWhere('asset_type', 'overlay_png');
                $overlayUrl = null;

                if ($overlayAsset && $overlayAsset->file) {
                    $overlayUrl = Storage::disk($overlayAsset->file->storage_disk)
                        ->url($overlayAsset->file->file_path);
                }

                return [
                    'id' => $template->id,
                    'template_code' => $template->template_code,
                    'template_name' => $template->template_name,
                    'category' => $template->category,
                    'paper_size' => $template->paper_size,
                    'canvas_width' => $template->canvas_width,
                    'canvas_height' => $template->canvas_height,
                    'preview_url' => $template->preview_url,
                    'overlay_url' => $overlayUrl,
                    'config' => $template->config_json,
                    'slots' => $template->slots
                        ->sortBy('slot_index')
                        ->values()
                        ->map(static function ($slot): array {
                            return [
                                'slot_index' => $slot->slot_index,
                                'x' => $slot->x,
                                'y' => $slot->y,
                                'width' => $slot->width,
                                'height' => $slot->height,
                                'rotation' => $slot->rotation,
                                'border_radius' => $slot->border_radius,
                                'metadata' => $slot->metadata_json,
                            ];
                        })->values(),
                ];
            })->values();

        return response()->json([
            'contract_version' => self::DEVICE_CONTRACT_VERSION,
            'station' => [
                'id' => $station->id,
                'station_code' => $station->station_code,
                'station_name' => $station->station_name,
                'location_name' => $station->location_name,
                'timezone' => $station->timezone,
                'local_ip' => $station->local_ip,
                'status' => $station->status,
            ],
            'pricing' => [
                'photobooth_price' => (float) $station->photobooth_price,
                'additional_print_price' => (float) $station->additional_print_price,
                'currency_code' => $station->currency_code,
            ],
            'templates' => $templates,
        ]);
    }
}
