<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->foreignUuid('customer_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained('customers')
                ->nullOnDelete();
            $table->index(['customer_id', 'created_at']);
        });

        $customerWhatsappList = DB::table('photo_sessions')
            ->whereNotNull('customer_whatsapp')
            ->where('customer_whatsapp', '!=', '')
            ->distinct()
            ->pluck('customer_whatsapp');

        if ($customerWhatsappList->isEmpty()) {
            return;
        }

        $existingWhatsappMap = DB::table('customers')
            ->whereIn('customer_whatsapp', $customerWhatsappList->all())
            ->pluck('id', 'customer_whatsapp');

        $missingCustomers = $customerWhatsappList
            ->filter(fn (string $whatsapp): bool => ! $existingWhatsappMap->has($whatsapp))
            ->map(fn (string $whatsapp): array => [
                'id' => (string) Str::uuid(),
                'customer_whatsapp' => $whatsapp,
                'tier' => 'regular',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if (! empty($missingCustomers)) {
            DB::table('customers')->insert($missingCustomers);
        }

        $customerMap = DB::table('customers')
            ->whereIn('customer_whatsapp', $customerWhatsappList->all())
            ->pluck('id', 'customer_whatsapp');

        foreach ($customerMap as $whatsapp => $customerId) {
            DB::table('photo_sessions')
                ->where('customer_whatsapp', $whatsapp)
                ->update([
                    'customer_id' => $customerId,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
