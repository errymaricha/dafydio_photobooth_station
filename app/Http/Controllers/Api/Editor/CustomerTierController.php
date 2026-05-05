<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetCustomerTierRequest;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Support\CustomerIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CustomerTierController extends Controller
{
    public function __construct(
        private CustomerIdentity $customerIdentity
    ) {}

    public function update(SetCustomerTierRequest $request, string $customerWhatsapp): JsonResponse
    {
        $validated = $request->validated();
        $customer = $this->customerIdentity->resolveOrCreateCustomerByWhatsapp($customerWhatsapp);

        if (! $customer) {
            abort(422, 'Invalid customer WhatsApp identifier.');
        }

        $tier = $validated['tier'];
        $cancelledSubscriptions = 0;

        DB::transaction(function () use ($customer, &$cancelledSubscriptions, $tier, $validated): void {
            if ($tier === 'regular') {
                $cancelledSubscriptions = CustomerSubscription::query()
                    ->where('customer_id', $customer->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'cancelled',
                        'end_at' => now(),
                        'notes' => $validated['notes'] ?? 'Downgrade tier lokal station.',
                        'updated_at' => now(),
                    ]);
            }

            $customer->update([
                'tier' => $tier,
                'status' => $customer->status ?: 'active',
            ]);
        });

        /** @var Customer $freshCustomer */
        $freshCustomer = $customer->fresh();

        return response()->json([
            'message' => $tier === 'premium'
                ? 'Customer berhasil dijadikan premium lokal.'
                : 'Customer berhasil dikembalikan ke regular.',
            'customer_id' => $freshCustomer->id,
            'customer_whatsapp' => $freshCustomer->customer_whatsapp,
            'customer_tier' => $freshCustomer->tier,
            'cancelled_subscriptions' => $cancelledSubscriptions,
        ]);
    }
}
