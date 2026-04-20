<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\DowngradeCustomerSubscriptionRequest;
use App\Http\Requests\UpgradeCustomerSubscriptionRequest;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPackage;
use App\Support\CustomerIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerSubscriptionController extends Controller
{
    public function __construct(
        private CustomerIdentity $customerIdentity
    ) {}

    public function upgrade(
        UpgradeCustomerSubscriptionRequest $request,
        string $customerWhatsapp
    ): JsonResponse {
        $validated = $request->validated();
        $customer = $this->resolveCustomer($customerWhatsapp);

        $package = SubscriptionPackage::query()
            ->whereRaw('lower(package_code) = ?', [mb_strtolower($validated['package_code'])])
            ->where('is_active', true)
            ->first();

        if (! $package) {
            return response()->json([
                'message' => 'Subscription package tidak ditemukan atau nonaktif.',
            ], 422);
        }

        $actorId = (string) ($request->user()?->id ?? '');
        $durationDays = (int) ($validated['duration_days'] ?? $package->duration_days ?? 30);
        $durationDays = max(1, $durationDays);
        $startAt = now();
        $endAt = $startAt->copy()->addDays($durationDays);

        $subscription = DB::transaction(function () use (
            $actorId,
            $customer,
            $endAt,
            $package,
            $startAt,
            $validated
        ): CustomerSubscription {
            $latestActiveSubscription = CustomerSubscription::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->latest('start_at')
                ->lockForUpdate()
                ->first();

            CustomerSubscription::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'upgraded',
                    'end_at' => now(),
                    'updated_at' => now(),
                ]);

            $subscription = CustomerSubscription::query()->create([
                'id' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'status' => 'active',
                'start_at' => $startAt,
                'end_at' => $endAt,
                'auto_renew' => false,
                'upgraded_from_id' => $latestActiveSubscription?->id,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $actorId !== '' ? $actorId : null,
            ]);

            $customer->update([
                'tier' => 'premium',
            ]);

            return $subscription;
        });

        return response()->json([
            'message' => 'Customer berhasil di-upgrade ke premium.',
            'customer_id' => $customer->id,
            'customer_whatsapp' => $customer->customer_whatsapp,
            'customer_tier' => $customer->fresh()->tier,
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'start_at' => $subscription->start_at,
                'end_at' => $subscription->end_at,
                'duration_days' => $durationDays,
                'package_code' => $package->package_code,
                'package_name' => $package->package_name,
            ],
        ]);
    }

    public function downgrade(
        DowngradeCustomerSubscriptionRequest $request,
        string $customerWhatsapp
    ): JsonResponse {
        $validated = $request->validated();
        $customer = $this->resolveCustomer($customerWhatsapp);

        $cancelled = DB::transaction(function () use ($customer, $validated): int {
            $count = CustomerSubscription::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'end_at' => now(),
                    'notes' => $validated['reason'] ?? null,
                    'updated_at' => now(),
                ]);

            $customer->update([
                'tier' => 'regular',
            ]);

            return $count;
        });

        return response()->json([
            'message' => 'Customer berhasil diturunkan ke regular.',
            'customer_id' => $customer->id,
            'customer_whatsapp' => $customer->customer_whatsapp,
            'customer_tier' => $customer->fresh()->tier,
            'cancelled_subscriptions' => $cancelled,
        ]);
    }

    private function resolveCustomer(string $inputWhatsapp): Customer
    {
        $customer = $this->customerIdentity->resolveOrCreateCustomerByWhatsapp($inputWhatsapp);

        if (! $customer) {
            abort(422, 'Invalid customer WhatsApp identifier.');
        }

        return $customer;
    }
}
