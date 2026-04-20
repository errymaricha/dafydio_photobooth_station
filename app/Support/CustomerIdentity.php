<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class CustomerIdentity
{
    public function normalizeWhatsapp(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($input)) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }

    public function resolveOrCreateCustomerByWhatsapp(?string $whatsapp): ?Customer
    {
        $normalizedWhatsapp = $this->normalizeWhatsapp($whatsapp);

        if ($normalizedWhatsapp === null) {
            return null;
        }

        $existingCustomer = Customer::query()
            ->where('customer_whatsapp', $normalizedWhatsapp)
            ->first();

        if ($existingCustomer) {
            return $this->ensureDefaultFlags($existingCustomer);
        }

        try {
            $customer = Customer::query()->create([
                'id' => (string) Str::uuid(),
                'customer_whatsapp' => $normalizedWhatsapp,
                'tier' => 'regular',
                'status' => 'active',
            ]);

            return $this->ensureDefaultFlags($customer);
        } catch (QueryException) {
            $customer = Customer::query()
                ->where('customer_whatsapp', $normalizedWhatsapp)
                ->first();

            return $customer ? $this->ensureDefaultFlags($customer) : null;
        }
    }

    private function ensureDefaultFlags(Customer $customer): Customer
    {
        $updates = [];

        if (! $customer->tier) {
            $updates['tier'] = 'regular';
        }

        if (! $customer->status) {
            $updates['status'] = 'active';
        }

        if ($updates !== []) {
            $customer->fill($updates);
            $customer->save();
        }

        return $customer;
    }
}
