<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStationPricingRequest;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $station = $this->resolveStation((string) $request->query('station_id', ''));

        if (! $station) {
            return response()->json([
                'message' => 'Station not found.',
            ], 404);
        }

        return response()->json($this->mapStationPricing($station));
    }

    public function update(UpdateStationPricingRequest $request): JsonResponse
    {
        $station = $this->resolveStation((string) $request->query('station_id', ''));

        if (! $station) {
            return response()->json([
                'message' => 'Station not found.',
            ], 404);
        }

        $validated = $request->validated();

        $station->update([
            'photobooth_price' => $validated['photobooth_price'],
            'additional_print_price' => $validated['additional_print_price'],
            'currency_code' => strtoupper(trim($validated['currency_code'])),
        ]);

        return response()->json([
            'message' => 'Pricing updated.',
            ...$this->mapStationPricing($station->fresh()),
        ]);
    }

    private function resolveStation(string $stationId): ?Station
    {
        if ($stationId !== '') {
            return Station::query()->find($stationId);
        }

        return Station::query()->orderBy('created_at')->first();
    }

    /**
     * @return array{
     *     station: array{id: string, station_code: string, station_name: string},
     *     pricing: array{photobooth_price: float, additional_print_price: float, currency_code: string},
     *     updated_at: string|null
     * }
     */
    private function mapStationPricing(Station $station): array
    {
        return [
            'station' => [
                'id' => $station->id,
                'station_code' => $station->station_code,
                'station_name' => $station->station_name,
            ],
            'pricing' => [
                'photobooth_price' => (float) $station->photobooth_price,
                'additional_print_price' => (float) $station->additional_print_price,
                'currency_code' => $station->currency_code,
            ],
            'updated_at' => optional($station->updated_at)->toIso8601String(),
        ];
    }
}
