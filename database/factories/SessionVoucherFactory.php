<?php

namespace Database\Factories;

use App\Models\PhotoSession;
use App\Models\Station;
use App\Models\SessionVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SessionVoucher>
 */
class SessionVoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $station = Station::create([
            'id' => (string) Str::uuid(),
            'station_code' => 'ST-' . strtoupper(Str::random(6)),
            'station_name' => $this->faker->company(),
            'status' => 'online',
        ]);

        $session = PhotoSession::create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-' . strtoupper(Str::random(8)),
            'station_id' => $station->id,
            'status' => 'uploaded',
            'source_type' => 'android',
            'total_expected_photos' => 2,
            'captured_count' => 2,
        ]);

        return [
            'session_id' => $session->id,
            'voucher_code' => strtoupper($this->faker->bothify('VCHR-####')),
            'voucher_type' => 'promo',
            'status' => 'applied',
            'applied_at' => now(),
            'notes' => $this->faker->sentence(),
        ];
    }
}
