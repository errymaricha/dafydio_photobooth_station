<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceTemplateIndexRequest;
use App\Models\AndroidDevice;
use App\Models\AssetFile;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class TemplateController extends Controller
{
    private const DEVICE_CONTRACT_VERSION = '2026-04-17';

    private const TEMPLATE_ASSET_URL_TTL_DAYS = 30;

    public function index(DeviceTemplateIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $includeSlots = $request->boolean('include_slots', true);
        $limit = (int) ($validated['limit'] ?? 100);

        $query = Template::query()
            ->with(['assets.file'])
            ->where('status', 'active')
            ->when(
                ! empty($validated['category']),
                fn ($builder) => $builder->where('category', $validated['category'])
            )
            ->when(
                ! empty($validated['paper_size']),
                fn ($builder) => $builder->where('paper_size', $validated['paper_size'])
            )
            ->when(
                ! empty($validated['q']),
                function ($builder) use ($validated) {
                    $search = (string) $validated['q'];
                    $builder->where(function ($searchQuery) use ($search): void {
                        $searchQuery
                            ->where('template_code', 'ilike', "%{$search}%")
                            ->orWhere('template_name', 'ilike', "%{$search}%");
                    });
                }
            )
            ->when(
                ! empty($validated['updated_since']),
                fn ($builder) => $builder->where('updated_at', '>=', Carbon::parse((string) $validated['updated_since']))
            )
            ->when(
                $includeSlots,
                fn ($builder) => $builder->with(['slots'])
            )
            ->orderByDesc('updated_at')
            ->orderBy('template_name')
            ->limit($limit);

        /** @var AndroidDevice|null $device */
        $device = $request->user();

        $templates = $query
            ->get()
            ->map(fn (Template $template): array => $this->transformTemplate($template, $includeSlots, $device?->id))
            ->values();

        return response()->json([
            'contract_version' => self::DEVICE_CONTRACT_VERSION,
            'filters' => [
                'category' => $validated['category'] ?? null,
                'paper_size' => $validated['paper_size'] ?? null,
                'q' => $validated['q'] ?? null,
                'updated_since' => $validated['updated_since'] ?? null,
                'limit' => $limit,
                'include_slots' => $includeSlots,
            ],
            'count' => $templates->count(),
            'templates' => $templates,
        ]);
    }

    public function show(Template $template): JsonResponse
    {
        if ($template->status !== 'active') {
            return response()->json([
                'message' => 'Template not found.',
            ], 404);
        }

        $includeSlots = request()->boolean('include_slots', true);
        $template->load(['assets.file']);
        if ($includeSlots) {
            $template->load(['slots']);
        }

        /** @var AndroidDevice|null $device */
        $device = request()->user();

        return response()->json([
            'contract_version' => self::DEVICE_CONTRACT_VERSION,
            'template' => $this->transformTemplate($template, $includeSlots, $device?->id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformTemplate(
        Template $template,
        bool $includeSlots = true,
        ?string $deviceId = null
    ): array {
        $overlayAsset = $template->assets->firstWhere('asset_type', 'overlay_png');
        $thumbnailAsset = $template->assets->firstWhere('asset_type', 'thumbnail_image');
        $overlayAssetId = $overlayAsset?->file?->id;
        $thumbnailAssetId = $thumbnailAsset?->file?->id;
        $overlayUrl = null;
        $thumbnailUrl = null;

        if ($overlayAsset && $overlayAsset->file) {
            $overlayUrl = $this->signedTemplateAssetUrl($overlayAsset->file, $deviceId);
        }
        if ($thumbnailAsset && $thumbnailAsset->file) {
            $thumbnailUrl = $this->signedTemplateAssetUrl($thumbnailAsset->file, $deviceId);
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
            'slots' => $includeSlots
                ? $template->slots
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
                    })->values()
                : [],
        ];
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
