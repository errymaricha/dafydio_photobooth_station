<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Models\AssetFile;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class MasterDataController extends Controller
{
    private const DEVICE_CONTRACT_VERSION = '2026-04-17';

    private const TEMPLATE_ASSET_URL_TTL_DAYS = 30;

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
            ->map(function (Template $template) use ($device): array {
                $overlayAsset = $template->assets->firstWhere('asset_type', 'overlay_png');
                $thumbnailAsset = $template->assets->firstWhere('asset_type', 'thumbnail_image');
                $overlayAssetId = $overlayAsset?->file?->id;
                $thumbnailAssetId = $thumbnailAsset?->file?->id;
                $overlayUrl = null;
                $thumbnailUrl = null;

                if ($overlayAsset && $overlayAsset->file) {
                    $overlayUrl = $this->signedTemplateAssetUrl(
                        $overlayAsset->file,
                        is_string($device?->id) ? $device->id : null
                    );
                }
                if ($thumbnailAsset && $thumbnailAsset->file) {
                    $thumbnailUrl = $this->signedTemplateAssetUrl(
                        $thumbnailAsset->file,
                        is_string($device?->id) ? $device->id : null
                    );
                }

                return [
                    'id' => $template->id,
                    'template_code' => $template->template_code,
                    'template_name' => $template->template_name,
                    'category' => $template->category,
                    'paper_size' => $template->paper_size,
                    'canvas_width' => $template->canvas_width,
                    'canvas_height' => $template->canvas_height,
                    'updated_at' => $template->updated_at?->toISOString(),
                    'overlay_asset_id' => $overlayAssetId,
                    'thumbnail_asset_id' => $thumbnailAssetId,
                    'thumbnail_url' => $thumbnailUrl,
                    'preview_url' => $thumbnailUrl ?? $template->preview_url,
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

    private function signedTemplateAssetUrl(AssetFile $assetFile, ?string $deviceId = null): string
    {
        $relativeUrl = URL::temporarySignedRoute(
            'api.device.template-assets.show',
            now()->addDays(self::TEMPLATE_ASSET_URL_TTL_DAYS),
            [
                'assetFile' => $assetFile->id,
                'device_id' => $deviceId,
            ],
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').$relativeUrl;
    }
}
